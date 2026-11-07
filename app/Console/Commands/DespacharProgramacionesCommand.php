<?php

namespace App\Console\Commands;

use App\Services\CalendarioAvisosService;
use Illuminate\Console\Command;

/**
 * HU 16: publica avisos agendados y despacha notificaciones programadas vencidas.
 */
class DespacharProgramacionesCommand extends Command
{
    protected $signature = 'avisos:despachar-programados';

    protected $description = 'Publica avisos programados y envía recordatorios programados cuya fecha ya llegó.';

    public function handle(CalendarioAvisosService $servicio): int
    {
        $resultado = $servicio->despacharProgramacionesVencidas();

        $this->info("Avisos publicados: {$resultado['avisos']}. Notificaciones enviadas: {$resultado['notificaciones']}.");

        return self::SUCCESS;
    }
}

