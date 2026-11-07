<?php

namespace App\Models;

use App\Enums\AnioCursada;
use Database\Factories\AvisoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Aviso extends Model
{
    /** @use HasFactory<AvisoFactory> */
    use HasFactory;

    protected $table = 'avisos';

    protected $fillable = [
        'titulo',
        'contenido',
        'anio_cursada',
        'evento_id',
    ];

    protected function casts(): array
    {
        return [
            'anio_cursada' => AnioCursada::class,
            'publicado_en' => 'datetime',
            'programado_para' => 'datetime',
            'editado_en' => 'datetime',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(AvisoLectura::class, 'aviso_id');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->whereNotNull('publicado_en');
    }

    /**
     * M2-08: el alumno ve avisos generales y de su año; el personal ve todos.
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

    public function scopeFiltrarAnio(Builder $query, ?string $filtro): Builder
    {
        return match ($filtro) {
            'general' => $query->whereNull('anio_cursada'),
            '1', '2' => $query->where('anio_cursada', (int) $filtro),
            default => $query,
        };
    }

    public function estaPublicado(): bool
    {
        return $this->publicado_en !== null;
    }

    public function estaProgramado(): bool
    {
        return $this->publicado_en === null && $this->programado_para !== null;
    }

    public function esBorrador(): bool
    {
        return $this->publicado_en === null && $this->programado_para === null;
    }

    public function fueEditado(): bool
    {
        return $this->estaPublicado() && $this->editado_en !== null;
    }

    public function etiquetaDestinatario(): string
    {
        return $this->anio_cursada?->etiqueta() ?? 'General';
    }

    public function etiquetaEstado(): string
    {
        if ($this->estaPublicado()) {
            return 'Publicado';
        }

        if ($this->estaProgramado()) {
            return 'Programado';
        }

        return 'Borrador';
    }

    public function clasesEstado(): string
    {
        if ($this->estaPublicado()) {
            return 'bg-green-100 text-green-800';
        }

        if ($this->estaProgramado()) {
            return 'bg-blue-100 text-blue-800';
        }

        return 'bg-amber-100 text-amber-800';
    }

    public function extracto(int $largo = 220): string
    {
        $plano = preg_replace('/\s+/u', ' ', trim($this->contenido)) ?? '';

        return Str::limit($plano, $largo);
    }

    /**
     * M2-06: escapa todo HTML para que se muestre como texto plano y convierte
     * direcciones http/https en enlaces clicables seguros.
     */
    public function contenidoFormateado(): HtmlString
    {
        $partes = preg_split('~(https?://[^\s<>"\'`]+)~iu', $this->contenido, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($partes === false) {
            return new HtmlString(nl2br(e($this->contenido), false));
        }

        $html = '';

        foreach ($partes as $parte) {
            if (preg_match('~^https?://~iu', $parte) === 1) {
                $sufijo = '';
                if (preg_match('~([.,;:!?)]+)$~u', $parte, $coincidencia) === 1) {
                    $sufijo = $coincidencia[1];
                    $parte = substr($parte, 0, -strlen($sufijo));
                }

                $urlSegura = e($parte);
                $html .= '<a href="'.$urlSegura.'" target="_blank" rel="noopener noreferrer" class="text-[#1F4E8C] underline break-all">'.$urlSegura.'</a>'.e($sufijo);
            } else {
                $html .= e($parte);
            }
        }

        return new HtmlString(nl2br($html, false));
    }
}

