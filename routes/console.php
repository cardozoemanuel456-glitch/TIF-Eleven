<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M1-12: descarta las planillas subidas que no se confirmaron a las 24 horas.
// En producción hace falta el cron: * * * * * php /ruta/artisan schedule:run
Schedule::command('actas:limpiar')->hourly();

// M2-13: recordatorio diario a las 08:00 (zona America/Argentina/Buenos_Aires).
Schedule::command('eventos:recordar')->dailyAt('08:00');

// HU 16: despacho de avisos agendados y notificaciones programadas.
Schedule::command('avisos:despachar-programados')->everyMinute();
