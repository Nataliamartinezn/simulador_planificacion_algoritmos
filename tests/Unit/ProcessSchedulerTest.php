<?php

namespace Tests\Unit;

use App\Services\Scheduling\ProcessScheduler;
use PHPUnit\Framework\TestCase;

class ProcessSchedulerTest extends TestCase
{
    private ProcessScheduler $scheduler;

    protected function setUp(): void
    {
        $this->scheduler = new ProcessScheduler();
    }

    public function test_fcfs_calculates_expected_waiting_times(): void
    {
        $result = $this->scheduler->simulate('fcfs', $this->sampleProcesses());

        $this->assertSame(11.2, $result['averages']['waiting']);
        $this->assertSame(28, $result['summary']['finish_time']);
        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'P5'], array_column($result['timeline'], 'process'));
    }

    public function test_sjf_is_non_preemptive_and_selects_shortest_ready_job(): void
    {
        $result = $this->scheduler->simulate('sjf', $this->sampleProcesses());

        $this->assertSame(8.0, $result['averages']['waiting']);
        $this->assertSame(['P1', 'P5', 'P2', 'P4', 'P3'], array_column($result['timeline'], 'process'));
    }

    public function test_round_robin_accepts_configurable_quantum(): void
    {
        $result = $this->scheduler->simulate('rr', $this->sampleProcesses(), ['quantum' => 3]);

        $this->assertSame(3, $result['parameters']['quantum']);
        $this->assertSame(28, $result['summary']['finish_time']);
        $this->assertGreaterThan(5, count($result['timeline']));
    }

    public function test_priority_order_is_configurable(): void
    {
        $lower = $this->scheduler->simulate('priority', $this->sampleProcesses(), ['priority_order' => 'lower']);
        $higher = $this->scheduler->simulate('priority', $this->sampleProcesses(), ['priority_order' => 'higher']);

        $this->assertNotSame(array_column($lower['timeline'], 'process'), array_column($higher['timeline'], 'process'));
    }

    public function test_mlfq_accepts_variable_number_of_levels(): void
    {
        $result = $this->scheduler->simulate('mlfq', $this->sampleProcesses(), [
            'mlfq_quantums' => '1,2,4,8',
            'boost_interval' => 10,
        ]);

        $this->assertSame([1, 2, 4, 8], $result['parameters']['quantums']);
        $this->assertSame(10, $result['parameters']['boost_interval']);
        $this->assertSame(28, $result['summary']['finish_time']);
        $this->assertCount(5, $result['processes']);
    }

    public function test_it_can_simulate_ten_processes_without_code_changes(): void
    {
        $processes = [];
        for ($i = 1; $i <= 10; $i++) {
            $processes[] = [
                'id' => 'P'.$i,
                'arrival' => $i - 1,
                'burst' => ($i % 5) + 1,
                'priority' => ($i % 3) + 1,
            ];
        }

        foreach (['fcfs', 'sjf', 'rr', 'priority', 'mlfq'] as $algorithm) {
            $result = $this->scheduler->simulate($algorithm, $processes, [
                'quantum' => 2,
                'mlfq_quantums' => '2,4,8',
                'boost_interval' => 12,
            ]);

            $this->assertCount(10, $result['processes']);
            foreach ($result['processes'] as $row) {
                $this->assertGreaterThanOrEqual(0, $row['waiting']);
                $this->assertGreaterThanOrEqual(0, $row['response']);
            }
        }
    }

    private function sampleProcesses(): array
    {
        return [
            ['id' => 'P1', 'arrival' => 0, 'burst' => 8, 'priority' => 2],
            ['id' => 'P2', 'arrival' => 1, 'burst' => 4, 'priority' => 1],
            ['id' => 'P3', 'arrival' => 2, 'burst' => 9, 'priority' => 3],
            ['id' => 'P4', 'arrival' => 3, 'burst' => 5, 'priority' => 2],
            ['id' => 'P5', 'arrival' => 5, 'burst' => 2, 'priority' => 1],
        ];
    }
}
