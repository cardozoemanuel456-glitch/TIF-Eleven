@extends('layouts.app')

@section('titulo', 'Programar notificación · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Programar notificación o recordatorio</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        Definí el contenido, el destinatario y el momento exacto de envío. Se publicará en la campana de Eleven y por correo electrónico.
    </p>

    @php
        $campo = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm';
        $anioActual = old('anio_cursada', '');
        $eventoActual = old('evento_id', '');
        $avisoActual = old('aviso_id', '');
    @endphp

    <form method="POST" action="{{ route('gestion.notificaciones.store') }}"
          class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <label for="titulo" class="block text-sm font-medium">Título</label>
            <input id="titulo" name="titulo" type="text" value="{{ old('titulo') }}" required maxlength="150"
                   placeholder="Ej.: Recordatorio de entrega de planilla o cierre de inscripción" class="{{ $campo }}">
            @error('titulo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="anio_cursada" class="block text-sm font-medium">Destinatario</label>
                <select id="anio_cursada" name="anio_cursada" class="{{ $campo }}">
                    <option value="" @selected($anioActual === '')>General (todos los años)</option>
                    @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                        <option value="{{ $anioCaso->value }}" @selected($anioActual === (string) $anioCaso->value)>
                            {{ $anioCaso->etiqueta() }}
                        </option>
                    @endforeach
                </select>
                @error('anio_cursada') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="programada_para" class="block text-sm font-medium">Fecha y hora de envío</label>
                <input id="programada_para" name="programada_para" type="datetime-local"
                       value="{{ old('programada_para') }}" required class="{{ $campo }}">
                @error('programada_para') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="evento_id" class="block text-sm font-medium">Fecha académica vinculada (opcional)</label>
                <select id="evento_id" name="evento_id" class="{{ $campo }}">
                    <option value="">Ninguna</option>
                    @foreach ($eventos as $ev)
                        <option value="{{ $ev->id }}" @selected($eventoActual === (string) $ev->id)>
                            {{ $ev->fecha_inicio->format('d/m/Y') }} · {{ $ev->titulo }}
                        </option>
                    @endforeach
                </select>
                @error('evento_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="aviso_id" class="block text-sm font-medium">Aviso vinculado (opcional)</label>
                <select id="aviso_id" name="aviso_id" class="{{ $campo }}">
                    <option value="">Ninguno</option>
                    @foreach ($avisos as $av)
                        <option value="{{ $av->id }}" @selected($avisoActual === (string) $av->id)>
                            {{ $av->titulo }}
                        </option>
                    @endforeach
                </select>
                @error('aviso_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="contenido" class="block text-sm font-medium">Mensaje de la notificación</label>
            <textarea id="contenido" name="contenido" rows="4" required maxlength="1000"
                      placeholder="Escribí el recordatorio que recibirán los alumnos..."
                      class="{{ $campo }}">{{ old('contenido') }}</textarea>
            @error('contenido') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#12263F]">
                Confirmar programación
            </button>
            <a href="{{ route('gestion.notificaciones.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection

