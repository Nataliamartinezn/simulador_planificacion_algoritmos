<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        Schema::create('concurrency_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('stock');
            $table->timestamps();
        });

        Schema::create('concurrency_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concurrency_product_id')->constrained('concurrency_products')->cascadeOnDelete();
            $table->string('method', 30);
            $table->unsignedInteger('initial_stock');
            $table->unsignedInteger('order_count');
            $table->unsignedInteger('quantity_per_order');
            $table->unsignedInteger('total_requested');
            $table->unsignedInteger('expected_stock');
            $table->unsignedInteger('final_stock')->nullable();
            $table->string('status', 30)->default('queued');
            $table->decimal('elapsed_seconds', 8, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('concurrency_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concurrency_test_id')->constrained('concurrency_tests')->cascadeOnDelete();
            $table->unsignedInteger('order_number');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('pid')->nullable();
            $table->string('worker', 80)->nullable();
            $table->string('status', 30)->default('queued');
            $table->string('lock_status', 80)->nullable();
            $table->integer('stock_read')->nullable();
            $table->integer('stock_written')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['concurrency_test_id', 'order_number'], 'concurrency_jobs_test_order_unique');
        });

        Schema::create('concurrency_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concurrency_test_id')->constrained('concurrency_tests')->cascadeOnDelete();
            $table->foreignId('concurrency_job_id')->nullable()->constrained('concurrency_jobs')->nullOnDelete();
            $table->unsignedInteger('pid')->nullable();
            $table->string('event', 80);
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concurrency_logs');
        Schema::dropIfExists('concurrency_jobs');
        Schema::dropIfExists('concurrency_tests');
        Schema::dropIfExists('concurrency_products');
    }
};
