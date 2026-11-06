<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanillaExcel extends Model
{
    protected $table = 'planillas_excel';

    protected $fillable = [
        'acta_id',
        'nombre_original',
        'archivo_xlsx',
        'generada_en',
    ];

    protected function casts(): array
    {
        return [
            'generada_en' => 'datetime',
        ];
    }

    public function acta(): BelongsTo
    {
        return $this->belongsTo(Acta::class);
    }
}
