<?php

namespace Database\Factories;

use App\Enums\AnioCursada;
use App\Enums\Rol;
use App\Enums\TipoEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evento>
 */
class EventoFactory extends Factory
{
    protected $model = Evento::class;

    public function definition(): array
    {
        $fechaInicio = fake()->dateTimeBetween('today', '+2 months')->format('Y-m-d');

        return [
            'creado_por' => User::factory()->conRol(Rol::Preceptor),
            'anio_cursada' => null,
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->paragraph(),
            'tipo' => TipoEvento::Otro,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaInicio,
            'hora' => null,
            'recordado_en' => null,
        ];
    }

    public function paraAnio(AnioCursada $anio): static
    {
        return $this->state(fn (): array => ['anio_cursada' => $anio]);
    }

    public function parcial(): static
    {
        return $this->state(fn (): array => ['tipo' => TipoEvento::Parcial]);
    }

    public function mesaExamen(): static
    {
        return $this->state(fn (): array => ['tipo' => TipoEvento::MesaExamen]);
    }
}

