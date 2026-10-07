<?php

namespace App\Services;

use App\Jobs\ProcessConcurrencyOrder;
use App\Models\ConcurrencyJob;
use App\Models\ConcurrencyLog;
use App\Models\ConcurrencyProduct;
use App\Models\ConcurrencyTest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ConcurrencyService
{
    public const METHOD_NONE = 'none';
    public const METHOD_DB_LOCK = 'db_lock';
    public const METHOD_OPTIMISTIC_LOCK = 'optimistic_lock';

    public const METHODS = [
        self::METHOD_NONE,
        self::METHOD_DB_LOCK,
        self::METHOD_OPTIMISTIC_LOCK,
    ];

    public function ensureDefaultProduct(): ConcurrencyProduct
    {
        return ConcurrencyProduct::firstOrCreate(
            ['name' => 'Producto de concurrencia'],
            ['stock' => 20]
        );
    }

    public function startTest(array $data): ConcurrencyTest
    {
        $test = DB::transaction(function () use ($data) {
            $product = ConcurrencyProduct::query()->lockForUpdate()->findOrFail($data['product_id']);
            $product->update(['stock' => $data['initial_stock']]);

            $totalRequested = $data['order_count'] * $data['quantity_per_order'];

            $test = ConcurrencyTest::create([
                'concurrency_product_id' => $product->id,
                'method' => $data['method'],
                'initial_stock' => $data['initial_stock'],
                'order_count' => $data['order_count'],
                'quantity_per_order' => $data['quantity_per_order'],
                'total_requested' => $totalRequested,
                'expected_stock' => max(0, $data['initial_stock'] - $totalRequested),
                'status' => 'running',
                'started_at' => now(),
            ]);

            for ($order = 1; $order <= $data['order_count']; $order++) {
                $job = ConcurrencyJob::create([
                    'concurrency_test_id' => $test->id,
                    'order_number' => $order,
                    'quantity' => $data['quantity_per_order'],
                    'status' => 'queued',
                    'lock_status' => 'Pendiente',
                ]);

                $this->log($test->id, $job->id, null, 'queued', "Pedido #{$order} encolado");
            }

            return $test;
        });

        ConcurrencyJob::where('concurrency_test_id', $test->id)
            ->orderBy('order_number')
            ->each(fn (ConcurrencyJob $job) => ProcessConcurrencyOrder::dispatch($job->id)->onQueue('concurrency'));

        return $test->fresh(['product', 'jobs', 'logs']);
    }

    public function resetStock(ConcurrencyProduct $product, int $stock): ConcurrencyProduct
    {
        $product->update(['stock' => $stock]);

        return $product->fresh();
    }

    public function testStatus(ConcurrencyTest $test): array
    {
        $this->finalizeIfComplete($test);

        $test = $test->fresh(['product', 'jobs' => fn ($query) => $query->orderBy('order_number')]);

        return [
            'test' => $this->formatTest($test),
            'jobs' => $test->jobs->map(fn (ConcurrencyJob $job) => $this->formatJob($job))->values(),
            'logs' => $this->logsFor($test->id)->map(fn (ConcurrencyLog $log) => $this->formatLog($log))->values(),
            'history' => $this->history()->map(fn (ConcurrencyTest $item) => $this->formatHistory($item))->values(),
        ];
    }

    public function history(): Collection
    {
        return ConcurrencyTest::query()
            ->with('product')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function finalizeIfComplete(ConcurrencyTest $test): void
    {
        if (! in_array($test->status, ['running', 'queued'], true)) {
            return;
        }

        $pendingJobs = ConcurrencyJob::where('concurrency_test_id', $test->id)
            ->whereNotIn('status', ['finished', 'failed'])
            ->count();

        if ($pendingJobs > 0) {
            return;
        }

        $finishedAt = now();
        $finalStock = ConcurrencyProduct::find($test->concurrency_product_id)?->stock ?? 0;
        $failedJobs = ConcurrencyJob::where('concurrency_test_id', $test->id)->where('status', 'failed')->count();

        $test->update([
            'status' => $failedJobs > 0 ? 'finished_with_errors' : 'finished',
            'final_stock' => $finalStock,
            'elapsed_seconds' => $test->started_at ? round($test->started_at->diffInSeconds($finishedAt), 2) : null,
            'finished_at' => $finishedAt,
        ]);
    }

    public function log(int $testId, ?int $jobId, ?int $pid, string $event, string $message): void
    {
        ConcurrencyLog::create([
            'concurrency_test_id' => $testId,
            'concurrency_job_id' => $jobId,
            'pid' => $pid,
            'event' => $event,
            'message' => $message,
        ]);
    }

    public function logsFor(int $testId): Collection
    {
        return ConcurrencyLog::query()
            ->where('concurrency_test_id', $testId)
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    public function formatTest(ConcurrencyTest $test): array
    {
        $finalStock = $test->final_stock ?? $test->product?->stock;

        return [
            'id' => $test->id,
            'method' => $test->method,
            'method_label' => $this->methodLabel($test->method),
            'status' => $test->status,
            'product' => $test->product?->name,
            'initial_stock' => $test->initial_stock,
            'order_count' => $test->order_count,
            'quantity_per_order' => $test->quantity_per_order,
            'total_requested' => $test->total_requested,
            'expected_stock' => $test->expected_stock,
            'final_stock' => $finalStock,
            'elapsed_seconds' => $test->elapsed_seconds,
            'is_consistent' => $test->status === 'finished' && (int) $finalStock === (int) $test->expected_stock,
        ];
    }

    public function formatHistory(ConcurrencyTest $test): array
    {
        $formatted = $this->formatTest($test);

        return [
            'id' => $formatted['id'],
            'method_label' => $formatted['method_label'],
            'initial_stock' => $formatted['initial_stock'],
            'expected_stock' => $formatted['expected_stock'],
            'final_stock' => $formatted['final_stock'],
            'elapsed_seconds' => $formatted['elapsed_seconds'],
            'result' => $formatted['status'] === 'finished'
                ? ($formatted['is_consistent'] ? 'Correcto' : 'Incorrecto')
                : $formatted['status'],
            'status' => $formatted['status'],
        ];
    }

    public function methodLabel(string $method): string
    {
        return match ($method) {
            self::METHOD_DB_LOCK => 'Mutex',
            self::METHOD_OPTIMISTIC_LOCK => 'Bloqueo optimista',
            default => 'Sin sincronizacion',
        };
    }

    private function formatJob(ConcurrencyJob $job): array
    {
        return [
            'id' => $job->id,
            'worker' => $job->worker,
            'pid' => $job->pid,
            'order_number' => $job->order_number,
            'status' => $job->status,
            'lock_status' => $job->lock_status,
            'stock_read' => $job->stock_read,
            'stock_written' => $job->stock_written,
            'error_message' => $job->error_message,
        ];
    }

    private function formatLog(ConcurrencyLog $log): array
    {
        return [
            'time' => $log->created_at?->format('H:i:s'),
            'pid' => $log->pid,
            'event' => $log->event,
            'message' => $log->message,
        ];
    }
}
