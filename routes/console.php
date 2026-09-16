<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-scheduler', function () {
    $this->info('Simulador parametrizable de planificación de CPU.');
});
