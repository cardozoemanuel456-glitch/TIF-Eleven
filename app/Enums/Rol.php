<?php

namespace App\Enums;

enum Rol: string
{
    case Alumno = 'alumno';
    case Profesor = 'profesor';
    case Preceptor = 'preceptor';
    case Administrador = 'administrador';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alumno => 'Alumno',
            self::Profesor => 'Profesor',
            self::Preceptor => 'Preceptor',
            self::Administrador => 'Administrador',
        };
    }

    /**
     * Roles del personal (todos menos el alumno).
     *
     * @return array<int, self>
     */
    public static function personal(): array
    {
        return [self::Profesor, self::Preceptor, self::Administrador];
    }

    /**
     * Valores en texto de los roles del personal, para consultas y validaciones.
     *
     * @return array<int, string>
     */
    public static function valoresDelPersonal(): array
    {
        return array_map(fn (self $rol) => $rol->value, self::personal());
    }
}
