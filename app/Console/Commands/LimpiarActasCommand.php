<?php

namespace App\Console\Commands;

use App\Services\ActaService;
use Illuminate\Console\Command;

class LimpiarActasCommand extends Command
{
    protected $signature = 'actas:limpiar';

    protected $description = 'Descarta las planillas subidas que no se confirmaron dentro del plazo configurado (M1-12)';

    public function handle(ActaService $servicio): int
    {
        $resultado = $servicio->limpiar();

        $this->info("Planillas subidas descartadas: {$resultado['planillas']}. Archivos huérfanos descartados: {$resultado['huerfanos']}.");

        return self::SUCCESS;
    }
}
