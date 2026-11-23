<?php

namespace App\Enums;

/**
 * Año de cursada de la carrera (dos años). No hay aulas fijas: los alumnos,
 * los eventos y los avisos se segmentan solo por año.
 */
enum AnioCursada: int
{
    case Primero = 1;
    case Segundo = 2;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Primero => 'Primer año',
            self::Segundo => 'Segundo año',
        };
    }

    /**
     * Valores numéricos permitidos, para validaciones.
     *
     * @return array<int, int>
     */
    public static function valores(): array
    {
        return array_map(fn (self $anio) => $anio->value, self::cases());
    }
}
