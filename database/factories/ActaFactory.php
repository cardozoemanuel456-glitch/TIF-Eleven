<?php

namespace Database\Factories;

use App\Enums\EstadoActa;
use App\Models\Acta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Los tests deben pasar user_id explícitamente: Acta::factory()->create(['user_id' => $u->id]).
 *
 * @extends Factory<Acta>
 */
class ActaFactory extends Factory
{
    protected $model = Acta::class;

    public function definition(): array
    {
        $nombre = 'acta_'.Str::random(6).'.pdf';

        return [
            'nombre_original' => $nombre,
            'archivo_pdf' => 'actas/'.$nombre,
            'hash_sha256' => hash('sha256', Str::random(40)),
            'materia' => 'Gestión de Desarrollo de Software',
            'tomo' => '156',
            'folio' => '404-406',
            'estado' => EstadoActa::Analizada,
        ];
    }

    public function procesada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoActa::Procesada,
            'procesada_en' => now(),
        ]);
    }
}
