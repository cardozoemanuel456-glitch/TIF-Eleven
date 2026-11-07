<?php

namespace App\Models;

use App\Enums\AnioCursada;
use App\Enums\EstadoNotificacionProgramada;
use Database\Factories\NotificacionProgramadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionProgramada extends Model
{
    /** @use HasFactory<NotificacionProgramadaFactory> */
    use HasFactory;

    protected $table = 'notificaciones_programadas';

    protected $fillable = [
        'titulo',
        'contenido',
        'anio_cursada',
        'evento_id',
        'aviso_id',
        'programada_para',
    ];

    protected function casts(): array
    {
        return [
            'anio_cursada' => AnioCursada::class,
            'estado' => EstadoNotificacionProgramada::class,
            'programada_para' => 'datetime',
            'enviada_en' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Aviso::class, 'aviso_id');
    }

    public function etiquetaDestinatario(): string
    {
        return $this->anio_cursada?->etiqueta() ?? 'General';
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoNotificacionProgramada::Pendiente;
    }
}

