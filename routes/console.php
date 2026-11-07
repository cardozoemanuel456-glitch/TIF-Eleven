<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M2-13: recordatorio diario a las 08:00 (zona America/Argentina/Buenos_Aires).
Schedule::command('eventos:recordar')->dailyAt('08:00');

// HU 16: despacho de avisos agendados y notificaciones programadas.
Schedule::command('avisos:despachar-programados')->everyMinute();
