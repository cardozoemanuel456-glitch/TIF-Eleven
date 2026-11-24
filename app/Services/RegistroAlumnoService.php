<?php

namespace App\Services;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * M3-01 a M3-04 · Alta de un alumno por registro público.
 * La cuenta nace pendiente y sin correo verificado: no puede ingresar hasta que
 * verifique el correo y Preceptoría la apruebe.
 */
class RegistroAlumnoService
{
    /**
     * @param  array{nombre: string, apellido: string, dni: string, email: string, password: string, anio_cursada: int|string}  $datos
     */
    public function registrar(array $datos): User
    {
        $alumno = new User;

        // forceFill: el rol y el estado nunca vienen del formulario.
        $alumno->forceFill([
            'role_id' => Role::where('nombre', Rol::Alumno->value)->firstOrFail()->id,
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'dni' => $datos['dni'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'anio_cursada' => AnioCursada::from((int) $datos['anio_cursada']),
            'estado' => EstadoUsuario::Pendiente,
            'debe_cambiar_password' => false,
        ])->save();

        $alumno->sendEmailVerificationNotification();

        Log::info('alumno.registrado', ['usuario' => $alumno->id]);

        return $alumno;
    }
}
