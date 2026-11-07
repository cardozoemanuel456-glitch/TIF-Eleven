<?php

namespace App\Enums;

/**
 * Tipos de evento del calendario académico (M2-01 y M2-03).
 * Cada tipo define su propio color y rótulo de texto para no depender solo del color.
 */
enum TipoEvento: string
{
    case Feriado = 'feriado';
    case Receso = 'receso';
    case InicioClases = 'inicio_clases';
    case FinClases = 'fin_clases';
    case Parcial = 'parcial';
    case MesaExamen = 'mesa_examen';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Feriado => 'Feriado',
            self::Receso => 'Receso',
            self::InicioClases => 'Inicio de clases',
            self::FinClases => 'Fin de clases',
            self::Parcial => 'Parcial',
            self::MesaExamen => 'Mesa de examen',
            self::Otro => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Feriado => '#DC2626',
            self::Receso => '#D97706',
            self::InicioClases => '#16A34A',
            self::FinClases => '#0D9488',
            self::Parcial => '#1F4E8C',
            self::MesaExamen => '#7C3AED',
            self::Otro => '#475569',
        };
    }

    public function clasesBadge(): string
    {
        return match ($this) {
            self::Feriado => 'bg-red-100 text-red-800 border-red-200',
            self::Receso => 'bg-amber-100 text-amber-800 border-amber-200',
            self::InicioClases => 'bg-green-100 text-green-800 border-green-200',
            self::FinClases => 'bg-teal-100 text-teal-800 border-teal-200',
            self::Parcial => 'bg-blue-100 text-blue-800 border-blue-200',
            self::MesaExamen => 'bg-purple-100 text-purple-800 border-purple-200',
            self::Otro => 'bg-slate-100 text-slate-800 border-slate-200',
        };
    }

    /**
     * M2-05 y M2-12: los parciales y las mesas de examen notifican reprogramación
     * cuando cambia su fecha de inicio y envían recordatorio el día anterior.
     */
    public function llevaRecordatorio(): bool
    {
        return in_array($this, [self::Parcial, self::MesaExamen], true);
    }

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $tipo): string => $tipo->value, self::cases());
    }
}

