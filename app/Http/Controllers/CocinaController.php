<?php

namespace App\Http\Controllers;

use App\Models\Preparacion;
use App\Services\CocinaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CocinaController extends Controller
{
    public function index(Request $request): View
    {
        $cicloEjecucion = (string) Str::uuid();

        $request->session()->put('cocina_ciclo_actual', $cicloEjecucion);
        $request->session()->forget([
            'ultimo_tiempo_secuencial',
            'ultimo_tiempo_paralelo',
            'ultimo_resultado_secuencial',
            'ultimo_resultado_paralelo',
        ]);

        return view('cocina.index', [
            'preparaciones' => $this->preparacionesDesdeOld($request) ?: $this->preparacionesIniciales(),
            'cicloEjecucion' => $cicloEjecucion,
            'resultadosGuardados' => [],
            'comparacion' => null,
        ]);
    }

    public function ejecutar(Request $request, CocinaService $cocinaService): View|RedirectResponse
    {
        $validated = $request->validate([
            'nombres' => ['required', 'array', 'min:1'],
            'nombres.*' => ['required', 'string', 'max:80'],
            'duraciones' => ['required', 'array', 'min:1'],
            'duraciones.*' => ['required', 'integer', 'min:1', 'max:20'],
            'modo' => ['required', 'in:secuencial,paralelo'],
            'ciclo_ejecucion' => ['required', 'string'],
        ], [
            'nombres.required' => 'Agrega al menos una preparacion.',
            'nombres.*.required' => 'Cada preparacion necesita un nombre.',
            'duraciones.*.required' => 'Cada preparacion necesita una duracion.',
            'duraciones.*.integer' => 'La duracion debe ser un numero entero.',
            'duraciones.*.min' => 'La duracion minima es de 1 segundo.',
            'duraciones.*.max' => 'La duracion maxima es de 20 segundos.',
            'modo.in' => 'El modo debe ser secuencial o paralelo.',
        ]);

        if (count($validated['nombres']) !== count($validated['duraciones'])) {
            return back()
                ->withErrors(['preparaciones' => 'Cada nombre debe tener una duracion asociada.'])
                ->withInput();
        }

        $preparaciones = $this->construirPreparaciones($validated['nombres'], $validated['duraciones']);
        $cicloEjecucion = $validated['ciclo_ejecucion'];
        $firmaPedido = $this->firmaPedido($preparaciones);

        if ($request->session()->get('cocina_ciclo_actual') !== $cicloEjecucion) {
            $request->session()->put('cocina_ciclo_actual', $cicloEjecucion);
            $request->session()->forget([
                'ultimo_tiempo_secuencial',
                'ultimo_tiempo_paralelo',
                'ultimo_resultado_secuencial',
                'ultimo_resultado_paralelo',
            ]);
        }

        $resultado = $validated['modo'] === 'secuencial'
            ? $cocinaService->ejecutarSecuencial($preparaciones)
            : $cocinaService->ejecutarParalelo($preparaciones);

        $resultado['ciclo_ejecucion'] = $cicloEjecucion;
        $resultado['firma_pedido'] = $firmaPedido;

        $request->session()->put('ultimo_tiempo_'.$validated['modo'], $resultado['tiempo_total']);
        $request->session()->put('ultimo_resultado_'.$validated['modo'], $resultado);

        return view('cocina.index', [
            'preparaciones' => $preparaciones,
            'cicloEjecucion' => $cicloEjecucion,
            'resultadosGuardados' => $this->obtenerResultadosGuardados($cicloEjecucion, $firmaPedido),
            'comparacion' => $this->obtenerComparacion($cicloEjecucion, $firmaPedido),
        ]);
    }

    /**
     * @return Preparacion[]
     */
    private function preparacionesIniciales(): array
    {
        return [
            new Preparacion('Hamburguesa', 5),
            new Preparacion('Papas', 3),
            new Preparacion('Bebida', 2),
        ];
    }

    /**
     * @param  string[]  $nombres
     * @param  int[]  $duraciones
     * @return Preparacion[]
     */
    private function construirPreparaciones(array $nombres, array $duraciones): array
    {
        $preparaciones = [];

        foreach ($nombres as $indice => $nombre) {
            $preparaciones[] = new Preparacion(trim($nombre), (int) $duraciones[$indice]);
        }

        return $preparaciones;
    }

    /**
     * @return Preparacion[]
     */
    private function preparacionesDesdeOld(Request $request): array
    {
        $nombres = $request->old('nombres', []);
        $duraciones = $request->old('duraciones', []);

        if (! is_array($nombres) || ! is_array($duraciones) || count($nombres) === 0) {
            return [];
        }

        $preparaciones = [];

        foreach ($nombres as $indice => $nombre) {
            $preparaciones[] = new Preparacion((string) $nombre, (int) ($duraciones[$indice] ?? 1));
        }

        return $preparaciones;
    }

    private function obtenerComparacion(string $cicloEjecucion, string $firmaPedido): ?array
    {
        $resultadoSecuencial = session('ultimo_resultado_secuencial');
        $resultadoParalelo = session('ultimo_resultado_paralelo');

        if (! $this->resultadoComparable($resultadoSecuencial, $cicloEjecucion, $firmaPedido)
            || ! $this->resultadoComparable($resultadoParalelo, $cicloEjecucion, $firmaPedido)) {
            return null;
        }

        $secuencial = (float) $resultadoSecuencial['tiempo_total'];
        $paralelo = (float) $resultadoParalelo['tiempo_total'];

        $diferencia = round($secuencial - $paralelo, 2);
        $mejora = $secuencial > 0 ? round(($diferencia / $secuencial) * 100, 2) : 0;

        return [
            'secuencial' => round((float) $secuencial, 2),
            'paralelo' => round((float) $paralelo, 2),
            'diferencia' => $diferencia,
            'mejora' => $mejora,
        ];
    }

    private function obtenerResultadosGuardados(string $cicloEjecucion, string $firmaPedido): array
    {
        $resultados = array_filter([
            'secuencial' => session('ultimo_resultado_secuencial'),
            'paralelo' => session('ultimo_resultado_paralelo'),
        ]);

        return array_filter($resultados, fn (array $resultado): bool => $this->resultadoComparable($resultado, $cicloEjecucion, $firmaPedido));
    }

    private function resultadoComparable(mixed $resultado, string $cicloEjecucion, string $firmaPedido): bool
    {
        return is_array($resultado)
            && ($resultado['ciclo_ejecucion'] ?? null) === $cicloEjecucion
            && ($resultado['firma_pedido'] ?? null) === $firmaPedido;
    }

    /**
     * @param  Preparacion[]  $preparaciones
     */
    private function firmaPedido(array $preparaciones): string
    {
        return hash('sha256', json_encode(array_map(fn (Preparacion $preparacion): array => [
            'nombre' => $preparacion->getNombre(),
            'duracion' => $preparacion->getDuracion(),
        ], $preparaciones)) ?: '');
    }
}
