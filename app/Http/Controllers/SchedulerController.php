<?php

namespace App\Http\Controllers;

use App\Services\Scheduling\ProcessScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SchedulerController extends Controller
{
    public function index(): View
    {
        return view('scheduler');
    }

    public function simulate(Request $request, ProcessScheduler $scheduler): JsonResponse
    {
        $validated = $request->validate([
            'algorithm' => ['required', 'in:fcfs,sjf,rr,priority,mlfq'],
            'processes' => ['required', 'array', 'min:1'],
            'processes.*.id' => ['required', 'string', 'max:20', 'distinct'],
            'processes.*.arrival' => ['required', 'integer', 'min:0'],
            'processes.*.burst' => ['required', 'integer', 'min:1'],
            'processes.*.priority' => ['required', 'integer'],
            'quantum' => ['nullable', 'integer', 'min:1'],
            'priority_order' => ['nullable', 'in:lower,higher'],
            'mlfq_quantums' => ['nullable', 'string', 'regex:/^\s*\d+\s*(,\s*\d+\s*)*$/'],
            'boost_interval' => ['nullable', 'integer', 'min:0'],
        ], [
            'processes.required' => 'Agrega al menos un proceso.',
            'processes.min' => 'Agrega al menos un proceso.',
            'processes.*.id.distinct' => 'Los identificadores de proceso no se pueden repetir.',
            'mlfq_quantums.regex' => 'Los quantum de MLFQ deben escribirse separados por comas, por ejemplo: 2,4,8.',
        ]);

        $options = [
            'quantum' => $validated['quantum'] ?? 2,
            'priority_order' => $validated['priority_order'] ?? 'lower',
            'mlfq_quantums' => $validated['mlfq_quantums'] ?? '2,4,8',
            'boost_interval' => $validated['boost_interval'] ?? 0,
        ];

        try {
            return response()->json(
                $scheduler->simulate($validated['algorithm'], $validated['processes'], $options)
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
