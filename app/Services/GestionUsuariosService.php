<?php

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Operaciones sobre cuentas de usuario. Los controladores solo validan y autorizan;
 * la lógica vive acá para que M3 (alumnos) pueda reutilizarla.
 * Los logs registran quién, a quién y cuándo, nunca datos personales.
 */
class GestionUsuariosService
{
    /**
     * Crea una cuenta del personal con contraseña temporal.
     *
     * @param  array{nombre: string, apellido: string, email: string, rol: string}  $datos
     * @return array{0: User, 1: string} la cuenta y la contraseña temporal en texto plano
     */
    public function crearPersonal(array $datos, User $actor): array
    {
        $password = $this->generarPasswordTemporal();

        $usuario = new User;
        $usuario->forceFill([
            'role_id' => Role::where('nombre', $datos['rol'])->firstOrFail()->id,
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
            'password' => $password,
            'estado' => EstadoUsuario::Activo,
            'debe_cambiar_password' => true,
            'email_verified_at' => now(),
            'aprobado_por' => $actor->id,
            'aprobado_en' => now(),
        ])->save();

        Log::info('usuario.creado', ['actor' => $actor->id, 'usuario' => $usuario->id]);

        return [$usuario, $password];
    }

    /**
     * Actualiza datos y rol. Nadie puede cambiar su propio rol (evita quedarse
     * sin administradores).
     *
     * @param  array{nombre: string, apellido: string, email: string, rol: string}  $datos
     */
    public function actualizar(User $objetivo, array $datos, User $actor): User
    {
        $objetivo->fill([
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email' => $datos['email'],
        ]);

        $rolCambio = false;
        if (! $actor->is($objetivo)) {
            $rol = Role::where('nombre', $datos['rol'])->firstOrFail();
            $rolCambio = $objetivo->role_id !== $rol->id;
            $objetivo->role_id = $rol->id;
        }

        $objetivo->save();

        Log::info($rolCambio ? 'usuario.rol_cambiado' : 'usuario.actualizado', [
            'actor' => $actor->id,
            'usuario' => $objetivo->id,
        ]);

        return $objetivo;
    }

    /** M0-07: al desactivar se cierran las sesiones abiertas. */
    public function desactivar(User $objetivo, User $actor): void
    {
        $objetivo->forceFill(['estado' => EstadoUsuario::Inactivo])->save();
        $objetivo->cerrarSesiones();

        Log::info('usuario.desactivado', ['actor' => $actor->id, 'usuario' => $objetivo->id]);
    }

    public function reactivar(User $objetivo, User $actor): void
    {
        $objetivo->forceFill(['estado' => EstadoUsuario::Activo])->save();

        Log::info('usuario.reactivado', ['actor' => $actor->id, 'usuario' => $objetivo->id]);
    }

    /**
     * M0-04: genera una contraseña temporal. Se devuelve para mostrarla una sola
     * vez; la cuenta debe cambiarla en el próximo ingreso y pierde sus sesiones.
     */
    public function restablecerConTemporal(User $objetivo, User $actor): string
    {
        $password = $this->generarPasswordTemporal();

        $objetivo->forceFill([
            'password' => $password,
            'debe_cambiar_password' => true,
            'remember_token' => null,
        ])->save();
        $objetivo->cerrarSesiones();

        Log::info('usuario.password_temporal', ['actor' => $actor->id, 'usuario' => $objetivo->id]);

        return $password;
    }

    private function generarPasswordTemporal(): string
    {
        return Str::password(12, symbols: false);
    }
}
