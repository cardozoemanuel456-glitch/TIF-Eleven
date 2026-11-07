<?php

namespace App\Http\Requests\Calendario;

use App\Enums\AnioCursada;
use App\Models\Aviso;
use Illuminate\Validation\Rule;

class UpdateAvisoRequest extends StoreAvisoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('aviso')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Aviso|null $aviso */
        $aviso = $this->route('aviso');
        $yaPublicado = $aviso?->estaPublicado() ?? false;

        if ($yaPublicado) {
            return [
                'titulo' => ['required', 'string', 'max:150'],
                'contenido' => ['required', 'string', 'max:5000'],
                'anio_cursada' => ['nullable', 'integer', Rule::in(AnioCursada::valores())],
                'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')],
            ];
        }

        return parent::rules();
    }
}

