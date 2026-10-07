<?php

namespace App\Http\Controllers;

use App\Models\ConcurrencyProduct;
use App\Models\ConcurrencyTest;
use App\Services\ConcurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ConcurrencyController extends Controller
{
    public function index(ConcurrencyService $service): View
    {
        $service->ensureDefaultProduct();

        return view('concurrency.index', [
            'products' => ConcurrencyProduct::orderBy('name')->get(),
            'history' => $service->history()->map(fn (ConcurrencyTest $test) => $service->formatHistory($test)),
        ]);
    }

    public function run(Request $request, ConcurrencyService $service): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:concurrency_products,id'],
            'initial_stock' => ['required', 'integer', 'min:1', 'max:10000'],
            'order_count' => ['required', 'integer', 'min:1', 'max:50'],
            'quantity_per_order' => ['required', 'integer', 'min:1', 'max:1000'],
            'method' => ['required', Rule::in(ConcurrencyService::METHODS)],
        ]);

        $test = $service->startTest($validated);

        return response()->json([
            'message' => 'Prueba encolada correctamente.',
            'test_id' => $test->id,
            'status_url' => route('concurrency.status', $test),
        ]);
    }

    public function status(ConcurrencyTest $test, ConcurrencyService $service): JsonResponse
    {
        return response()->json($service->testStatus($test));
    }

    public function reset(Request $request, ConcurrencyService $service): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:concurrency_products,id'],
            'stock' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $product = $service->resetStock(
            ConcurrencyProduct::findOrFail($validated['product_id']),
            $validated['stock']
        );

        return response()->json([
            'message' => 'Stock restablecido.',
            'product' => $product,
        ]);
    }
}
