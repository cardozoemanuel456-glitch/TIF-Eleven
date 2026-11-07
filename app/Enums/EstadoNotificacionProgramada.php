<?php

namespace App\Enums;

enum EstadoNotificacionProgramada: string
{
    case Pendiente = 'pendiente';
    case Enviada = 'enviada';
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Enviada => 'Enviada',
            self::Error => 'Error de envío',
        };
    }

    public function clasesBadge(): string
    {
        return match ($this) {
            self::Pendiente => 'bg-amber-100 text-amber-800',
            self::Enviada => 'bg-green-100 text-green-800',
            self::Error => 'bg-red-100 text-red-800',
        };
    }
}

