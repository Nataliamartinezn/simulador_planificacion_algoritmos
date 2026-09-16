<?php

require __DIR__.'/../app/Services/Scheduling/ProcessScheduler.php';

use App\Services\Scheduling\ProcessScheduler;

$scheduler = new ProcessScheduler();
$sample = [
    ['id' => 'P1', 'arrival' => 0, 'burst' => 8, 'priority' => 2],
    ['id' => 'P2', 'arrival' => 1, 'burst' => 4, 'priority' => 1],
    ['id' => 'P3', 'arrival' => 2, 'burst' => 9, 'priority' => 3],
    ['id' => 'P4', 'arrival' => 3, 'burst' => 5, 'priority' => 2],
    ['id' => 'P5', 'arrival' => 5, 'burst' => 2, 'priority' => 1],
];

$cases = [
    ['fcfs', []],
    ['sjf', []],
    ['rr', ['quantum' => 3]],
    ['priority', ['priority_order' => 'lower']],
    ['mlfq', ['mlfq_quantums' => '1,2,4,8', 'boost_interval' => 10]],
];

foreach ($cases as [$algorithm, $options]) {
    $result = $scheduler->simulate($algorithm, $sample, $options);
    if (count($result['processes']) !== 5 || $result['summary']['finish_time'] !== 28) {
        throw new RuntimeException("Falló la prueba de {$algorithm}");
    }
    foreach ($result['processes'] as $row) {
        if ($row['waiting'] < 0 || $row['response'] < 0) {
            throw new RuntimeException("Métrica inválida en {$algorithm}");
        }
    }
    echo strtoupper($algorithm).": OK | espera promedio = {$result['averages']['waiting']}\n";
}

$ten = [];
for ($i = 1; $i <= 10; $i++) {
    $ten[] = ['id' => 'P'.$i, 'arrival' => $i - 1, 'burst' => ($i % 5) + 1, 'priority' => ($i % 3) + 1];
}
$result = $scheduler->simulate('mlfq', $ten, ['mlfq_quantums' => '2,4,8', 'boost_interval' => 12]);
if (count($result['processes']) !== 10) {
    throw new RuntimeException('La prueba de 10 procesos falló.');
}
echo "MLFQ con 10 procesos: OK\n";
