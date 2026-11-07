<?php

namespace App\Http\Requests\Calendario;

class UpdateEventoRequest extends StoreEventoRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('evento')) ?? false;
    }
}

