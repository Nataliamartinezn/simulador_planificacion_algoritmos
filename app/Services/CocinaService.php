<?php

namespace App\Services;

use App\Models\Preparacion;
use Symfony\Component\Process\Process;

class CocinaService
{
    /**
     * En la ejecucion secuencial cada proceso termina antes de iniciar el siguiente.
     * Se usa el mismo script trabajador que en paralelo para comparar de forma justa.
     *
     * @param  Preparacion[]  $preparaciones
     * @return array{modo:string,tiempo_total:float,resultados:array<int,array<string,mixed>>}
     */
    public function ejecutarSecuencial(array $preparaciones): array
    {
        $inicio = microtime(true);
        $resultados = [];

        foreach ($preparaciones as $indice => $preparacion) {
            $process = $this->crearProceso($indice + 1, $preparacion);
            $process->run();

            $resultados[] = $this->leerResultado($process, $indice + 1, $preparacion);
        }

        return [
            'modo' => 'Secuencial',
            'tiempo_total' => round(microtime(true) - $inicio, 2),
            'resultados' => $resultados,
        ];
    }

    /**
     * En paralelo se crean todos los procesos, se inician con start() y recien despues
     * se esperan con wait(). Esa espera es la sincronizacion entre Laravel y los hijos.
     *
     * @param  Preparacion[]  $preparaciones
     * @return array{modo:string,tiempo_total:float,resultados:array<int,array<string,mixed>>}
     */
    public function ejecutarParalelo(array $preparaciones): array
    {
        $inicio = microtime(true);
        $procesos = [];
        $resultados = [];

        foreach ($preparaciones as $indice => $preparacion) {
            $process = $this->crearProceso($indice + 1, $preparacion);
            $process->start();
            $procesos[] = [
                'id' => $indice + 1,
                'preparacion' => $preparacion,
                'process' => $process,
            ];
        }

        foreach ($procesos as $item) {
            /** @var Process $process */
            $process = $item['process'];

            // wait() sincroniza el proceso principal con cada proceso hijo antes de cerrar el pedido.
            $process->wait();

            $resultados[] = $this->leerResultado($process, $item['id'], $item['preparacion']);
        }

        return [
            'modo' => 'Paralelo',
            'tiempo_total' => round(microtime(true) - $inicio, 2),
            'resultados' => $resultados,
        ];
    }

    private function crearProceso(int $id, Preparacion $preparacion): Process
    {
        return new Process([
            PHP_BINARY,
            base_path('scripts/preparar.php'),
            (string) $id,
            $preparacion->getNombre(),
            (string) $preparacion->getDuracion(),
        ]);
    }

    private function leerResultado(Process $process, int $id, Preparacion $preparacion): array
    {
        if (! $process->isSuccessful()) {
            return $this->resultadoError($id, $preparacion, trim($process->getErrorOutput()) ?: 'El proceso finalizo con error.');
        }

        // Comunicacion entre procesos: el hijo escribe JSON en stdout y Laravel lo recupera con getOutput().
        $resultado = json_decode($process->getOutput(), true);

        if (! is_array($resultado)) {
            return $this->resultadoError($id, $preparacion, 'El proceso no devolvio un JSON valido.');
        }

        return $resultado;
    }

    private function resultadoError(int $id, Preparacion $preparacion, string $mensaje): array
    {
        return [
            'proceso' => $id,
            'nombre' => $preparacion->getNombre(),
            'duracion' => $preparacion->getDuracion(),
            'tiempo_real' => 0,
            'estado' => 'ERROR',
            'mensaje' => $mensaje,
        ];
    }
}
