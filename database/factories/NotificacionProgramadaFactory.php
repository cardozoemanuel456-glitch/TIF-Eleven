<?php

namespace Database\Factories;

use App\Enums\AnioCursada;
use App\Enums\EstadoNotificacionProgramada;
use App\Enums\Rol;
use App\Models\NotificacionProgramada;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificacionProgramada>
 */
class NotificacionProgramadaFactory extends Factory
{
    protected $model = NotificacionProgramada::class;

    public function definition(): array
    {
        return [
            'creado_por' => User::factory()->conRol(Rol::Administrador),
            'anio_cursada' => null,
            'evento_id' => null,
            'aviso_id' => null,
            'titulo' => fake()->sentence(4),
            'contenido' => fake()->paragraph(),
            'programada_para' => now()->addHours(2),
            'estado' => EstadoNotificacionProgramada::Pendiente,
            'enviada_en' => null,
            'mensaje_error' => null,
        ];
    }

    public function paraAnio(AnioCursada $anio): static
    {
        return $this->state(fn (): array => ['anio_cursada' => $anio]);
    }
}

