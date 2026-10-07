<?php

namespace App\Jobs;

use App\Models\ConcurrencyJob;
use App\Models\ConcurrencyProduct;
use App\Services\ConcurrencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessConcurrencyOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(private readonly int $concurrencyJobId)
    {
    }

    public function handle(ConcurrencyService $service): void
    {
        $job = ConcurrencyJob::with('test')->findOrFail($this->concurrencyJobId);
        $pid = getmypid() ?: null;

        $job->update([
            'pid' => $pid,
            'worker' => gethostname() ?: 'worker',
            'status' => 'reading',
            'started_at' => now(),
        ]);

        $this->log($service, $job, 'started', "PID {$pid} inicio Pedido #{$job->order_number}");

        try {
            match ($job->test->method) {
                ConcurrencyService::METHOD_DB_LOCK => $this->processWithDbLock($service, $job),
                ConcurrencyService::METHOD_OPTIMISTIC_LOCK => $this->processWithOptimisticLock($service, $job),
                default => $this->processWithoutLock($service, $job),
            };

            $job->update([
                'status' => 'finished',
                'lock_status' => $job->lock_status === 'Lock adquirido' ? 'Lock liberado' : $job->lock_status,
                'finished_at' => now(),
            ]);
            $this->log($service, $job, 'finished', "PID {$pid} finalizo Pedido #{$job->order_number}");
        } catch (Throwable $exception) {
            $job->update([
                'status' => 'failed',
                'lock_status' => 'Error',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
            $this->log($service, $job, 'failed', "PID {$pid} fallo Pedido #{$job->order_number}: {$exception->getMessage()}");

            throw $exception;
        } finally {
            $service->finalizeIfComplete($job->test);
        }
    }

    private function processWithoutLock(ConcurrencyService $service, ConcurrencyJob $job): void
    {
        $this->log($service, $job, 'request_product', "PID {$job->pid} solicito acceso sin lock al producto");

        $product = ConcurrencyProduct::findOrFail($job->test->concurrency_product_id);
        $stockRead = $product->stock;

        $job->update([
            'status' => 'processing',
            'lock_status' => 'Sin lock',
            'stock_read' => $stockRead,
        ]);
        $this->log($service, $job, 'stock_read', "PID {$job->pid} leyo stock {$stockRead}");

        sleep(2);

        $stockWritten = max(0, $stockRead - $job->quantity);
        $job->update(['status' => 'updating', 'stock_written' => $stockWritten]);
        $this->log($service, $job, 'updating', "PID {$job->pid} calculo stock {$stockRead} -> {$stockWritten}");

        sleep(1);

        $product->stock = $stockWritten;
        $product->save();
        $this->log($service, $job, 'updated', "PID {$job->pid} guardo stock {$stockWritten} sin sincronizacion");
    }

    private function processWithDbLock(ConcurrencyService $service, ConcurrencyJob $job): void
    {
        $job->update(['status' => 'waiting_lock', 'lock_status' => 'Esperando lock MySQL']);
        $this->log($service, $job, 'waiting_lock', "PID {$job->pid} esperando lock MySQL");

        DB::transaction(function () use ($service, $job) {
            $product = ConcurrencyProduct::where('id', $job->test->concurrency_product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $stockRead = $product->stock;
            $job->update([
                'status' => 'lock_acquired',
                'lock_status' => 'Lock adquirido',
                'stock_read' => $stockRead,
            ]);
            $this->log($service, $job, 'lock_acquired', "PID {$job->pid} adquirio lock MySQL y leyo stock {$stockRead}");

            sleep(2);

            $stockWritten = max(0, $stockRead - $job->quantity);
            $product->stock = $stockWritten;
            $job->update(['status' => 'updating', 'stock_written' => $stockWritten]);
            $this->log($service, $job, 'updating', "PID {$job->pid} modifico stock {$stockRead} -> {$stockWritten}");

            sleep(1);

            $product->save();
        });

        $this->log($service, $job, 'lock_released', "PID {$job->pid} libero lock MySQL");
    }

    private function processWithOptimisticLock(ConcurrencyService $service, ConcurrencyJob $job): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $product = ConcurrencyProduct::findOrFail($job->test->concurrency_product_id);
            $stockRead = $product->stock;

            $job->update([
                'status' => $attempt === 1 ? 'processing' : 'waiting_lock',
                'lock_status' => $attempt === 1 ? 'Lectura optimista' : 'Reintentando actualizacion',
                'stock_read' => $stockRead,
            ]);
            $this->log($service, $job, 'stock_read', "PID {$job->pid} leyo stock {$stockRead} con intento optimista #{$attempt}");

            sleep(2);

            $stockWritten = max(0, $stockRead - $job->quantity);
            $job->update(['status' => 'updating', 'stock_written' => $stockWritten]);
            $this->log($service, $job, 'updating', "PID {$job->pid} intento actualizar stock {$stockRead} -> {$stockWritten}");

            sleep(1);

            $updated = ConcurrencyProduct::where('id', $job->test->concurrency_product_id)
                ->where('stock', $stockRead)
                ->update(['stock' => $stockWritten]);

            if ($updated === 1) {
                $job->update(['lock_status' => 'Actualizacion atomica aplicada']);
                $this->log($service, $job, 'updated', "PID {$job->pid} aplico actualizacion optimista {$stockRead} -> {$stockWritten}");

                return;
            }

            $job->update(['lock_status' => 'Conflicto detectado']);
            $this->log($service, $job, 'retry', "PID {$job->pid} detecto conflicto y reintenta");
            usleep(300000);
        }

        throw new \RuntimeException('No se pudo actualizar el stock despues de varios reintentos optimistas.');
    }

    private function log(ConcurrencyService $service, ConcurrencyJob $job, string $event, string $message): void
    {
        $service->log($job->concurrency_test_id, $job->id, $job->pid, $event, $message);
    }
}
