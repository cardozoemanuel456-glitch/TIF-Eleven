<?php

namespace App\Enums;

enum EstadoUsuario: string
{
    case Pendiente = 'pendiente';
    case Activo = 'activo';
    case Inactivo = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Activo => 'Activo',
            self::Inactivo => 'Inactivo',
        };
    }

    /**
     * Mensaje que ve quien intenta ingresar con una cuenta que no está activa.
     */
    public function mensajeDeAcceso(): ?string
    {
        return match ($this) {
            self::Activo => null,
            self::Pendiente => 'Tu cuenta está pendiente de aprobación. Preceptoría te avisará por email cuando esté activa.',
            self::Inactivo => 'Tu cuenta está desactivada. Consultá en preceptoría.',
        };
    }
}
