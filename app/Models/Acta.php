<?php

namespace App\Models;

use App\Enums\EstadoActa;
use Database\Factories\ActaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro de un acta procesada. Solo guarda metadatos, el informe y las rutas de
 * los archivos; las filas de alumnos extraídas del PDF nunca se guardan en la base.
 */
class Acta extends Model
{
    /** @use HasFactory<ActaFactory> */
    use HasFactory;

    /** Solo las crea el servidor (nunca desde un formulario), por eso se listan todos. */
    protected $fillable = [
        'user_id',
        'nombre_original',
        'archivo_pdf',
        'hash_sha256',
        'planilla_origen',
        'materia',
        'tomo',
        'folio',
        'estado',
        'informe',
        'mensaje_error',
        'procesada_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoActa::class,
            'informe' => 'array',
            'procesada_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function planillas(): HasMany
    {
        return $this->hasMany(PlanillaExcel::class)->latest('generada_en');
    }
}
