<?php

namespace App\Enums;

enum EstadoActa: string
{
    case Analizada = 'analizada';
    case Procesada = 'procesada';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Analizada => 'Analizada',
            self::Procesada => 'Procesada',
            self::Error => 'Con error',
        };
    }
}
