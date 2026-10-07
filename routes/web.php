<?php

use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\CocinaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchedulerController::class, 'index'])->name('scheduler.index');
Route::post('/simular', [SchedulerController::class, 'simulate'])->name('scheduler.simulate');
Route::get('/cocina', [CocinaController::class, 'index'])->name('cocina.index');
Route::post('/ejecutar', [CocinaController::class, 'ejecutar'])->name('cocina.ejecutar');
