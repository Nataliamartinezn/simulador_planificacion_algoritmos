<?php

namespace App\Services\Scheduling;

use InvalidArgumentException;

class ProcessScheduler
{
    /**
     * @param array<int, array{id:string, arrival:int, burst:int, priority:int}> $processes
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function simulate(string $algorithm, array $processes, array $options = []): array
    {
        $processes = $this->normalizeProcesses($processes);
        $algorithm = strtolower(trim($algorithm));

        return match ($algorithm) {
            'fcfs', 'fifo' => $this->fcfs($processes),
            'sjf' => $this->sjf($processes),
            'rr', 'round_robin' => $this->roundRobin($processes, (int) ($options['quantum'] ?? 2)),
            'priority', 'prioridad' => $this->priority($processes, (string) ($options['priority_order'] ?? 'lower')),
            'mlfq' => $this->mlfq(
                $processes,
                $this->parseQuantums($options['mlfq_quantums'] ?? [2, 4, 8]),
                (int) ($options['boost_interval'] ?? 0)
            ),
            default => throw new InvalidArgumentException('Algoritmo no soportado.'),
        };
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function normalizeProcesses(array $processes): array
    {
        if ($processes === []) {
            throw new InvalidArgumentException('Debe existir al menos un proceso.');
        }

        $seen = [];
        $normalized = [];

        foreach (array_values($processes) as $index => $process) {
            $id = trim((string) ($process['id'] ?? ''));
            $arrival = (int) ($process['arrival'] ?? -1);
            $burst = (int) ($process['burst'] ?? 0);
            $priority = (int) ($process['priority'] ?? 0);

            if ($id === '') {
                throw new InvalidArgumentException('Todos los procesos deben tener un identificador.');
            }
            if (isset($seen[$id])) {
                throw new InvalidArgumentException("El identificador {$id} está repetido.");
            }
            if ($arrival < 0) {
                throw new InvalidArgumentException("El tiempo de llegada de {$id} no puede ser negativo.");
            }
            if ($burst <= 0) {
                throw new InvalidArgumentException("La ráfaga de {$id} debe ser mayor que cero.");
            }

            $seen[$id] = true;
            $normalized[] = [
                'id' => $id,
                'arrival' => $arrival,
                'burst' => $burst,
                'priority' => $priority,
                '_index' => $index,
            ];
        }

        return $normalized;
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function fcfs(array $processes): array
    {
        usort($processes, fn ($a, $b) => [$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);

        $time = 0;
        $timeline = [];
        $state = $this->initState($processes);

        foreach ($processes as $p) {
            if ($time < $p['arrival']) {
                $this->addSegment($timeline, 'IDLE', $time, $p['arrival']);
                $time = $p['arrival'];
            }

            $this->markStart($state, $p['id'], $time);
            $start = $time;
            $time += $p['burst'];
            $this->addSegment($timeline, $p['id'], $start, $time);
            $state[$p['id']]['completion'] = $time;
        }

        return $this->buildResult('FCFS / FIFO', $processes, $state, $timeline);
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function sjf(array $processes): array
    {
        $state = $this->initState($processes);
        $timeline = [];
        $done = [];
        $time = 0;
        $count = count($processes);

        while (count($done) < $count) {
            $ready = array_values(array_filter($processes, function ($p) use ($done, $time) {
                return !isset($done[$p['id']]) && $p['arrival'] <= $time;
            }));

            if ($ready === []) {
                $future = array_values(array_filter($processes, fn ($p) => !isset($done[$p['id']])));
                usort($future, fn ($a, $b) => [$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);
                $next = $future[0]['arrival'];
                if ($time < $next) {
                    $this->addSegment($timeline, 'IDLE', $time, $next);
                    $time = $next;
                }
                continue;
            }

            usort($ready, fn ($a, $b) => [$a['burst'], $a['arrival'], $a['_index']] <=> [$b['burst'], $b['arrival'], $b['_index']]);
            $p = $ready[0];
            $this->markStart($state, $p['id'], $time);
            $start = $time;
            $time += $p['burst'];
            $this->addSegment($timeline, $p['id'], $start, $time);
            $state[$p['id']]['completion'] = $time;
            $done[$p['id']] = true;
        }

        return $this->buildResult('SJF (no expropiativo)', $processes, $state, $timeline);
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function priority(array $processes, string $order): array
    {
        $order = strtolower($order) === 'higher' ? 'higher' : 'lower';
        $state = $this->initState($processes);
        $timeline = [];
        $done = [];
        $time = 0;
        $count = count($processes);

        while (count($done) < $count) {
            $ready = array_values(array_filter($processes, function ($p) use ($done, $time) {
                return !isset($done[$p['id']]) && $p['arrival'] <= $time;
            }));

            if ($ready === []) {
                $future = array_values(array_filter($processes, fn ($p) => !isset($done[$p['id']])));
                usort($future, fn ($a, $b) => [$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);
                $next = $future[0]['arrival'];
                if ($time < $next) {
                    $this->addSegment($timeline, 'IDLE', $time, $next);
                    $time = $next;
                }
                continue;
            }

            usort($ready, function ($a, $b) use ($order) {
                $priorityCmp = $order === 'higher'
                    ? ($b['priority'] <=> $a['priority'])
                    : ($a['priority'] <=> $b['priority']);

                return $priorityCmp !== 0
                    ? $priorityCmp
                    : ([$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);
            });

            $p = $ready[0];
            $this->markStart($state, $p['id'], $time);
            $start = $time;
            $time += $p['burst'];
            $this->addSegment($timeline, $p['id'], $start, $time);
            $state[$p['id']]['completion'] = $time;
            $done[$p['id']] = true;
        }

        $label = $order === 'higher'
            ? 'Prioridad (no expropiativo, número mayor = mayor prioridad)'
            : 'Prioridad (no expropiativo, número menor = mayor prioridad)';

        return $this->buildResult($label, $processes, $state, $timeline);
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function roundRobin(array $processes, int $quantum): array
    {
        if ($quantum <= 0) {
            throw new InvalidArgumentException('El quantum de Round Robin debe ser mayor que cero.');
        }

        $sorted = $processes;
        usort($sorted, fn ($a, $b) => [$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);

        $state = $this->initState($processes);
        $remaining = [];
        foreach ($processes as $p) {
            $remaining[$p['id']] = $p['burst'];
        }

        $timeline = [];
        $queue = [];
        $next = 0;
        $time = 0;
        $completed = 0;
        $count = count($processes);

        while ($completed < $count) {
            while ($next < $count && $sorted[$next]['arrival'] <= $time) {
                $queue[] = $sorted[$next];
                $next++;
            }

            if ($queue === []) {
                $nextArrival = $sorted[$next]['arrival'];
                if ($time < $nextArrival) {
                    $this->addSegment($timeline, 'IDLE', $time, $nextArrival);
                    $time = $nextArrival;
                }
                continue;
            }

            $p = array_shift($queue);
            $id = $p['id'];
            $this->markStart($state, $id, $time);
            $run = min($quantum, $remaining[$id]);
            $start = $time;
            $time += $run;
            $remaining[$id] -= $run;
            $this->addSegment($timeline, $id, $start, $time, ['queue' => 1]);

            while ($next < $count && $sorted[$next]['arrival'] <= $time) {
                $queue[] = $sorted[$next];
                $next++;
            }

            if ($remaining[$id] > 0) {
                $queue[] = $p;
            } else {
                $state[$id]['completion'] = $time;
                $completed++;
            }
        }

        $result = $this->buildResult('Round Robin', $processes, $state, $timeline);
        $result['parameters'] = ['quantum' => $quantum];
        return $result;
    }

    /**
     * MLFQ: nuevos procesos entran a Q1. Un proceso que consume todo su quantum se degrada un nivel.
     * Si llega un proceso nuevo mientras corre una cola inferior, el proceso actual es expropiado y
     * permanece en su nivel. El priority boost, si está habilitado, mueve todos los listos a Q1.
     *
     * @param array<int, array<string, mixed>> $processes
     * @param array<int, int> $quantums
     */
    private function mlfq(array $processes, array $quantums, int $boostInterval): array
    {
        if ($quantums === []) {
            throw new InvalidArgumentException('MLFQ requiere al menos una cola.');
        }
        foreach ($quantums as $q) {
            if ($q <= 0) {
                throw new InvalidArgumentException('Todos los quantum de MLFQ deben ser mayores que cero.');
            }
        }
        if ($boostInterval < 0) {
            throw new InvalidArgumentException('El intervalo de priority boost no puede ser negativo.');
        }

        $sorted = $processes;
        usort($sorted, fn ($a, $b) => [$a['arrival'], $a['_index']] <=> [$b['arrival'], $b['_index']]);

        $levels = count($quantums);
        $queues = array_fill(0, $levels, []);
        $remaining = [];
        $state = $this->initState($processes);
        foreach ($processes as $p) {
            $remaining[$p['id']] = $p['burst'];
        }

        $timeline = [];
        $time = 0;
        $nextArrival = 0;
        $completed = 0;
        $count = count($processes);
        $nextBoost = $boostInterval > 0 ? $boostInterval : PHP_INT_MAX;

        while ($completed < $count) {
            while ($boostInterval > 0 && $time >= $nextBoost) {
                $this->boostQueues($queues);
                $nextBoost += $boostInterval;
            }

            while ($nextArrival < $count && $sorted[$nextArrival]['arrival'] <= $time) {
                $queues[0][] = $sorted[$nextArrival];
                $nextArrival++;
            }

            $level = $this->firstReadyLevel($queues);
            if ($level === null) {
                if ($nextArrival >= $count) {
                    break;
                }
                $jump = $sorted[$nextArrival]['arrival'];
                if ($time < $jump) {
                    $this->addSegment($timeline, 'IDLE', $time, $jump);
                    $time = $jump;
                }
                continue;
            }

            $p = array_shift($queues[$level]);
            $id = $p['id'];
            $this->markStart($state, $id, $time);

            $run = min($quantums[$level], $remaining[$id]);
            $preemptedByArrival = false;
            $preemptedByBoost = false;

            if ($level > 0 && $nextArrival < $count) {
                $arrivalDelta = $sorted[$nextArrival]['arrival'] - $time;
                if ($arrivalDelta > 0 && $arrivalDelta < $run) {
                    $run = $arrivalDelta;
                    $preemptedByArrival = true;
                }
            }

            if ($boostInterval > 0) {
                $boostDelta = $nextBoost - $time;
                if ($boostDelta > 0 && $boostDelta < $run) {
                    $run = $boostDelta;
                    $preemptedByBoost = true;
                }
            }

            $start = $time;
            $time += $run;
            $remaining[$id] -= $run;
            $this->addSegment($timeline, $id, $start, $time, ['queue' => $level + 1]);

            while ($nextArrival < $count && $sorted[$nextArrival]['arrival'] <= $time) {
                $queues[0][] = $sorted[$nextArrival];
                $nextArrival++;
            }

            if ($remaining[$id] <= 0) {
                $state[$id]['completion'] = $time;
                $completed++;
                continue;
            }

            $consumedFullQuantum = $run >= $quantums[$level];

            if ($preemptedByBoost || ($boostInterval > 0 && $time >= $nextBoost)) {
                // Se reencola antes del boost para que también sea promovido a Q1.
                $queues[$level][] = $p;
                continue;
            }

            if ($preemptedByArrival) {
                $queues[$level][] = $p;
                continue;
            }

            if ($consumedFullQuantum) {
                $newLevel = min($level + 1, $levels - 1);
                $queues[$newLevel][] = $p;
            } else {
                $queues[$level][] = $p;
            }
        }

        $result = $this->buildResult('MLFQ - Colas multinivel con retroalimentación', $processes, $state, $timeline);
        $result['parameters'] = [
            'quantums' => $quantums,
            'boost_interval' => $boostInterval,
        ];
        return $result;
    }

    /** @param array<int, array<string, mixed>> $processes */
    private function initState(array $processes): array
    {
        $state = [];
        foreach ($processes as $p) {
            $state[$p['id']] = [
                'first_start' => null,
                'completion' => null,
            ];
        }
        return $state;
    }

    private function markStart(array &$state, string $id, int $time): void
    {
        if ($state[$id]['first_start'] === null) {
            $state[$id]['first_start'] = $time;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $timeline
     * @param array<string, mixed> $extra
     */
    private function addSegment(array &$timeline, string $process, int $start, int $end, array $extra = []): void
    {
        if ($end <= $start) {
            return;
        }

        $timeline[] = array_merge([
            'process' => $process,
            'start' => $start,
            'end' => $end,
            'duration' => $end - $start,
        ], $extra);
    }

    /**
     * @param array<int, array<string, mixed>> $processes
     * @param array<string, array<string, int|null>> $state
     * @param array<int, array<string, mixed>> $timeline
     */
    private function buildResult(string $algorithm, array $processes, array $state, array $timeline): array
    {
        usort($processes, fn ($a, $b) => $a['_index'] <=> $b['_index']);

        $rows = [];
        $sumWaiting = 0;
        $sumTurnaround = 0;
        $sumResponse = 0;

        foreach ($processes as $p) {
            $completion = (int) $state[$p['id']]['completion'];
            $firstStart = (int) $state[$p['id']]['first_start'];
            $turnaround = $completion - $p['arrival'];
            $waiting = $turnaround - $p['burst'];
            $response = $firstStart - $p['arrival'];

            $rows[] = [
                'id' => $p['id'],
                'arrival' => $p['arrival'],
                'burst' => $p['burst'],
                'priority' => $p['priority'],
                'first_start' => $firstStart,
                'completion' => $completion,
                'turnaround' => $turnaround,
                'waiting' => $waiting,
                'response' => $response,
            ];

            $sumWaiting += $waiting;
            $sumTurnaround += $turnaround;
            $sumResponse += $response;
        }

        $n = count($rows);
        $finish = $timeline === [] ? 0 : max(array_column($timeline, 'end'));
        $busy = array_sum(array_map(fn ($s) => $s['process'] === 'IDLE' ? 0 : $s['duration'], $timeline));

        return [
            'algorithm' => $algorithm,
            'processes' => $rows,
            'timeline' => $timeline,
            'averages' => [
                'waiting' => round($sumWaiting / $n, 2),
                'turnaround' => round($sumTurnaround / $n, 2),
                'response' => round($sumResponse / $n, 2),
            ],
            'summary' => [
                'finish_time' => $finish,
                'cpu_utilization' => $finish > 0 ? round(($busy / $finish) * 100, 2) : 0,
                'throughput' => $finish > 0 ? round($n / $finish, 4) : 0,
            ],
        ];
    }

    /** @param mixed $value @return array<int, int> */
    private function parseQuantums(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== '');
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Los quantum de MLFQ deben enviarse como lista.');
        }
        return array_values(array_map('intval', $value));
    }

    /** @param array<int, array<int, array<string, mixed>>> $queues */
    private function firstReadyLevel(array $queues): ?int
    {
        foreach ($queues as $level => $queue) {
            if ($queue !== []) {
                return $level;
            }
        }
        return null;
    }

    /** @param array<int, array<int, array<string, mixed>>> $queues */
    private function boostQueues(array &$queues): void
    {
        if (count($queues) <= 1) {
            return;
        }

        for ($level = 1; $level < count($queues); $level++) {
            foreach ($queues[$level] as $process) {
                $queues[0][] = $process;
            }
            $queues[$level] = [];
        }
    }
}
