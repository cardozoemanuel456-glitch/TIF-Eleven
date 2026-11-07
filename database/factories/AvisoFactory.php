<?php

namespace Database\Factories;

use App\Enums\AnioCursada;
use App\Enums\Rol;
use App\Models\Aviso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aviso>
 */
class AvisoFactory extends Factory
{
    protected $model = Aviso::class;

    public function definition(): array
    {
        return [
            'autor_id' => User::factory()->conRol(Rol::Administrador),
            'anio_cursada' => null,
            'evento_id' => null,
            'titulo' => fake()->sentence(5),
            'contenido' => fake()->paragraphs(2, true),
            'publicado_en' => now(),
            'programado_para' => null,
            'editado_en' => null,
        ];
    }

    public function borrador(): static
    {
        return $this->state(fn (): array => [
            'publicado_en' => null,
            'programado_para' => null,
        ]);
    }

    public function programado(?\DateTimeInterface $fecha = null): static
    {
        return $this->state(fn (): array => [
            'publicado_en' => null,
            'programado_para' => $fecha ?? now()->addDay(),
        ]);
    }

    public function paraAnio(AnioCursada $anio): static
    {
        return $this->state(fn (): array => ['anio_cursada' => $anio]);
    }
}

