<?php

namespace App\Services;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Models\User;
use App\Notifications\CuentaAprobadaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * M3-04, M3-07, M3-10 a M3-13 · Operaciones de Preceptoría sobre cuentas de alumnos.
 * Los controladores validan y autorizan; acá vive la lógica. Los logs registran
 * quién, a quién y cuándo, nunca datos personales.
 */
class GestionAlumnosService
{
    public function aprobar(User $alumno, User $actor): void
    {
        $alumno->forceFill([
            'estado' => EstadoUsuario::Activo,
            'aprobado_por' => $actor->id,
            'aprobado_en' => now(),
        ])->save();

        $alumno->notify(new CuentaAprobadaNotification);

        Log::info('alumno.aprobado', ['actor' => $actor->id, 'usuario' => $alumno->id]);
    }

    /** Rechazar elimina la cuenta pendiente: la persona podrá volver a registrarse. */
    public function rechazar(User $alumno, User $actor): void
    {
        Log::info('alumno.rechazado', ['actor' => $actor->id, 'usuario' => $alumno->id]);

        $alumno->delete();
    }

    /** M3-10 · Baja o egreso: pierde el acceso y se cierran sus sesiones. Es reversible. */
    public function desactivar(User $alumno, User $actor): void
    {
        $alumno->forceFill(['estado' => EstadoUsuario::Inactivo])->save();
        $alumno->cerrarSesiones();

        Log::info('alumno.desactivado', ['actor' => $actor->id, 'usuario' => $alumno->id]);
    }

    public function reactivar(User $alumno, User $actor): void
    {
        $alumno->forceFill(['estado' => EstadoUsuario::Activo])->save();

        Log::info('alumno.reactivado', ['actor' => $actor->id, 'usuario' => $alumno->id]);
    }

    public function cambiarAnio(User $alumno, AnioCursada $anio, User $actor): void
    {
        $alumno->forceFill(['anio_cursada' => $anio])->save();

        Log::info('alumno.anio_cambiado', ['actor' => $actor->id, 'usuario' => $alumno->id]);
    }

    /**
     * M3-11 · Eliminación definitiva = anonimización. Irreversible: se vacían nombre,
     * apellido, DNI y correo, y la cuenta queda como «Alumno eliminado». El DNI y el
     * correo quedan libres para un nuevo registro.
     */
    public function anonimizar(User $alumno, User $actor): void
    {
        $correo = $alumno->email;

        DB::transaction(function () use ($alumno, $correo) {
            // Notificaciones propias (M2) y pedidos pendientes de restablecer contraseña.
            if (Schema::hasTable('notifications')) {
                $alumno->notifications()->delete();
            }
            if ($correo !== null && Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $correo)->delete();
            }

            $alumno->forceFill([
                'nombre' => 'Alumno eliminado',
                'apellido' => '',
                'dni' => null,
                'email' => null,
                'email_verified_at' => null,
                'password' => Str::random(40),
                'remember_token' => null,
                'anonimizado_en' => now(),
            ])->save();
        });

        $alumno->cerrarSesiones();

        Log::info('alumno.eliminado', ['actor' => $actor->id, 'usuario' => $alumno->id]);
    }
}
