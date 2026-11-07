<?php

namespace App\Models;

use App\Enums\AnioCursada;
use App\Enums\TipoEvento;
use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'titulo',
        'descripcion',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'hora',
        'anio_cursada',
    ];

    protected function casts(): array
    {
        return [
            'anio_cursada' => AnioCursada::class,
            'tipo' => TipoEvento::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'recordado_en' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class, 'evento_id');
    }

    /**
     * M2-02: el alumno ve los generales y los de su año; el personal ve todos.
     */
    public function scopeVisiblesPara(Builder $query, User $usuario): Builder
    {
        if ($usuario->esPersonal()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($usuario): void {
            $q->whereNull('anio_cursada');

            if ($usuario->anio_cursada !== null) {
                $q->orWhere('anio_cursada', $usuario->anio_cursada->value);
            }
        });
    }

    /**
     * Filtra por año cuando el personal usa el selector ('general', '1', '2').
     */
    public function scopeFiltrarAnio(Builder $query, ?string $filtro): Builder
    {
        return match ($filtro) {
            'general' => $query->whereNull('anio_cursada'),
            '1', '2' => $query->where('anio_cursada', (int) $filtro),
            default => $query,
        };
    }

    public function scopeEnRango(Builder $query, string $inicio, string $fin): Builder
    {
        return $query
            ->whereDate('fecha_inicio', '<=', $fin)
            ->whereDate('fecha_fin', '>=', $inicio);
    }

    public function scopeProximos(Builder $query): Builder
    {
        return $query
            ->whereDate('fecha_fin', '>=', today()->toDateString())
            ->orderBy('fecha_inicio')
            ->orderBy('hora')
            ->orderBy('id');
    }

    public function etiquetaDestinatario(): string
    {
        return $this->anio_cursada?->etiqueta() ?? 'General';
    }

    public function horaFormateada(): ?string
    {
        if (! filled($this->hora)) {
            return null;
        }

        return substr((string) $this->hora, 0, 5);
    }

    public function rangoFechasTexto(): string
    {
        $inicio = $this->fecha_inicio->format('d/m/Y');
        $fin = $this->fecha_fin->format('d/m/Y');
        $base = $inicio === $fin ? $inicio : "{$inicio} al {$fin}";
        $hora = $this->horaFormateada();

        return $hora !== null ? "{$base} · {$hora} hs" : $base;
    }

    /**
     * Estructura JSON consumida por FullCalendar (M2-03).
     *
     * @return array<string, mixed>
     */
    public function aEventoFullCalendar(bool $puedeGestionar = false): array
    {
        $hora = $this->horaFormateada();
        $mismoDiaConHora = $this->fecha_inicio->equalTo($this->fecha_fin) && $hora !== null;

        return [
            'id' => (string) $this->id,
            'title' => "[{$this->tipo->etiqueta()}] {$this->titulo}",
            'start' => $mismoDiaConHora
                ? $this->fecha_inicio->format('Y-m-d').'T'.$hora.':00'
                : $this->fecha_inicio->format('Y-m-d'),
            'end' => $mismoDiaConHora
                ? null
                : $this->fecha_fin->copy()->addDay()->format('Y-m-d'),
            'allDay' => ! $mismoDiaConHora,
            'backgroundColor' => $this->tipo->color(),
            'borderColor' => $this->tipo->color(),
            'textColor' => '#FFFFFF',
            'extendedProps' => [
                'titulo' => $this->titulo,
                'tipo_valor' => $this->tipo->value,
                'tipo_etiqueta' => $this->tipo->etiqueta(),
                'clases_badge' => $this->tipo->clasesBadge(),
                'color' => $this->tipo->color(),
                'destinatario' => $this->etiquetaDestinatario(),
                'descripcion' => $this->descripcion ?? '',
                'rango_fechas' => $this->rangoFechasTexto(),
                'hora' => $hora,
                'puede_gestionar' => $puedeGestionar,
                'url_editar' => $puedeGestionar ? route('gestion.eventos.edit', $this) : null,
                'url_eliminar' => $puedeGestionar ? route('gestion.eventos.destroy', $this) : null,
            ],
        ];
    }
}

