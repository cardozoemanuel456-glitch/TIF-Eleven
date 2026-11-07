<?php

namespace Database\Seeders;

use App\Enums\AnioCursada;
use App\Enums\Rol;
use App\Enums\TipoEvento;
use App\Models\Aviso;
use App\Models\Evento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder opcional de demostración para poblar eventos y avisos de prueba en desarrollo.
 * Uso manual: php artisan db:seed --class=CalendarioDemoSeeder
 */
class CalendarioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolSeeder::class, AdminSeeder::class]);

        $rolAdmin = Role::where('nombre', Rol::Administrador->value)->firstOrFail();
        $autor = User::where('role_id', $rolAdmin->id)->first()
            ?? User::factory()->conRol(Rol::Administrador)->create();

        $parcial = Evento::firstOrCreate(
            ['titulo' => 'Segundo Parcial · Programación I', 'fecha_inicio' => today()->addDays(5)->toDateString()],
            [
                'creado_por' => $autor->id,
                'anio_cursada' => AnioCursada::Primero,
                'descripcion' => 'Evaluación práctica presencial en laboratorio.',
                'tipo' => TipoEvento::Parcial,
                'fecha_fin' => today()->addDays(5)->toDateString(),
                'hora' => '18:30',
            ]
        );

        Evento::firstOrCreate(
            ['titulo' => 'Entrega de avances de TIF', 'fecha_inicio' => today()->addDays(9)->toDateString()],
            [
                'creado_por' => $autor->id,
                'anio_cursada' => AnioCursada::Segundo,
                'descripcion' => 'Presentación de informe preliminar ante docentes tutores.',
                'tipo' => TipoEvento::Otro,
                'fecha_fin' => today()->addDays(9)->toDateString(),
                'hora' => '19:00',
            ]
        );

        Evento::firstOrCreate(
            ['titulo' => 'Mesa de examen turno noviembre', 'fecha_inicio' => today()->addDays(14)->toDateString()],
            [
                'creado_por' => $autor->id,
                'anio_cursada' => null,
                'descripcion' => 'Mesas finales para todas las materias de la tecnicatura.',
                'tipo' => TipoEvento::MesaExamen,
                'fecha_fin' => today()->addDays(18)->toDateString(),
                'hora' => '18:00',
            ]
        );

        Evento::firstOrCreate(
            ['titulo' => 'Feriado nacional · Día de la Soberanía', 'fecha_inicio' => today()->addDays(12)->toDateString()],
            [
                'creado_por' => $autor->id,
                'anio_cursada' => null,
                'descripcion' => 'Sin actividad académica ni administrativa en la subsede.',
                'tipo' => TipoEvento::Feriado,
                'fecha_fin' => today()->addDays(12)->toDateString(),
                'hora' => null,
            ]
        );

        Aviso::firstOrCreate(
            ['titulo' => 'Apertura de inscripciones a mesas finales'],
            [
                'autor_id' => $autor->id,
                'anio_cursada' => null,
                'evento_id' => null,
                'contenido' => "Recordamos que las inscripciones oficiales a mesas finales se realizan por SysAcad: https://sysacad.frre.utn.edu.ar\n\nConsultá las fechas en el calendario académico de Eleven.",
                'publicado_en' => now()->subHours(3),
            ]
        );

        Aviso::firstOrCreate(
            ['titulo' => 'Temario confirmado del Segundo Parcial de Programación I'],
            [
                'autor_id' => $autor->id,
                'anio_cursada' => AnioCursada::Primero,
                'evento_id' => $parcial->id,
                'contenido' => 'Se evaluarán estructuras repetitivas, arreglos y modularización. Traer DNI.',
                'publicado_en' => now()->subHour(),
            ]
        );
    }
}

