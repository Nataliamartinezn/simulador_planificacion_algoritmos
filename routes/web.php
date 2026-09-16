<?php

use App\Http\Controllers\SchedulerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchedulerController::class, 'index'])->name('scheduler.index');
Route::post('/simular', [SchedulerController::class, 'simulate'])->name('scheduler.simulate');
