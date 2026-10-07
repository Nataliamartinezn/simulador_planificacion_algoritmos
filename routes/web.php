<?php

use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\CocinaController;
use App\Http\Controllers\ConcurrencyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchedulerController::class, 'index'])->name('scheduler.index');
Route::post('/simular', [SchedulerController::class, 'simulate'])->name('scheduler.simulate');
Route::get('/cocina', [CocinaController::class, 'index'])->name('cocina.index');
Route::post('/ejecutar', [CocinaController::class, 'ejecutar'])->name('cocina.ejecutar');

Route::prefix('concurrency')->name('concurrency.')->group(function () {
    Route::get('/', [ConcurrencyController::class, 'index'])->name('index');
    Route::post('/run', [ConcurrencyController::class, 'run'])->name('run');
    Route::get('/tests/{test}/status', [ConcurrencyController::class, 'status'])->name('status');
    Route::post('/reset', [ConcurrencyController::class, 'reset'])->name('reset');
});
