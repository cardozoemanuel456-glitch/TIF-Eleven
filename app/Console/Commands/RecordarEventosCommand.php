<?php

namespace App\Console\Commands;

use App\Services\CalendarioAvisosService;
use Illuminate\Console\Command;

/**
 * M2-13: tarea diaria a las 8:00 que recuerda parciales y mesas de examen
 * con inicio hoy o mañana y sin recordado_en.
 */
class RecordarEventosCommand extends Command
{
    protected $signature = 'eventos:recordar';

    protected $description = 'Envía recordatorios de parciales y mesas de examen próximos sin duplicar envíos.';

    public function handle(CalendarioAvisosService $servicio): int
    {
        $cantidad = $servicio->enviarRecordatoriosPendientes();

        $this->info("Recordatorios procesados: {$cantidad}.");

        return self::SUCCESS;
    }
}

