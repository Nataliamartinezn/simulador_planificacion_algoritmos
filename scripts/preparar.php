<?php

$id = isset($argv[1]) ? (int) $argv[1] : 0;
$nombre = isset($argv[2]) ? (string) $argv[2] : 'Preparacion desconocida';
$duracion = isset($argv[3]) ? (int) $argv[3] : 0;

$inicio = microtime(true);

if ($id < 1 || $duracion < 1) {
    echo json_encode([
        'proceso' => $id,
        'nombre' => $nombre,
        'duracion' => $duracion,
        'tiempo_real' => 0,
        'estado' => 'ERROR',
        'mensaje' => 'Argumentos invalidos para la preparacion.',
    ]);
    exit(1);
}

sleep($duracion);

$fin = microtime(true);

echo json_encode([
    'proceso' => $id,
    'nombre' => $nombre,
    'duracion' => $duracion,
    'tiempo_real' => round($fin - $inicio, 2),
    'estado' => 'LISTO',
]);
