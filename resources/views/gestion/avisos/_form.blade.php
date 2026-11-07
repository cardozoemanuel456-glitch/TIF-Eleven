@php
    $campo = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm';
    $yaPublicado = isset($aviso) && $aviso->estaPublicado();
    $anioActual = old('anio_cursada', isset($aviso) && $aviso->anio_cursada ? (string) $aviso->anio_cursada->value : '');
    $eventoActual = old('evento_id', isset($aviso) && $aviso->evento_id ? (string) $aviso->evento_id : '');
    $modoPorDefecto = isset($aviso)
        ? ($aviso->estaProgramado() ? 'programar' : ($aviso->estaPublicado() ? 'publicar' : 'borrador'))
        : 'publicar';
    $modoActual = old('modo_publicacion', $modoPorDefecto);
    $fechaProgramadaActual = old(
        'programado_para',
        isset($aviso) && $aviso->programado_para ? $aviso->programado_para->format('Y-m-d\TH:i') : ''
    );
@endphp

<div>
    <label for="titulo" class="block text-sm font-medium">Título del aviso</label>
    <input id="titulo" name="titulo" type="text" value="{{ old('titulo', $aviso->titulo ?? '') }}"
           required maxlength="150" placeholder="Ej.: Inscripción a mesas de examen del turno noviembre" class="{{ $campo }}">
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
        <label for="evento_id" class="block text-sm font-medium">Evento vinculado (opcional)</label>
        <select id="evento_id" name="evento_id" class="{{ $campo }}">
            <option value="">Sin evento vinculado</option>
            @foreach ($eventos as $ev)
                <option value="{{ $ev->id }}" @selected($eventoActual === (string) $ev->id)>
                    {{ $ev->fecha_inicio->format('d/m/Y') }} · {{ $ev->titulo }} ({{ $ev->etiquetaDestinatario() }})
                </option>
            @endforeach
        </select>
        @error('evento_id') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="contenido" class="block text-sm font-medium">Contenido</label>
    <textarea id="contenido" name="contenido" rows="7" required maxlength="5000"
              placeholder="Escribí el texto del comunicado. Las direcciones web (https://...) se convierten automáticamente en enlaces."
              class="{{ $campo }}">{{ old('contenido', $aviso->contenido ?? '') }}</textarea>
    <p class="mt-1 text-xs text-slate-500">Texto plano (máximo 5.000 caracteres, sin datos personales ni adjuntos).</p>
    @error('contenido') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

@if (! $yaPublicado)
    <div x-data="{ modo: @js($modoActual) }" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <span class="block text-sm font-medium text-slate-900">Estado y programación del aviso</span>
        <div class="mt-2 flex flex-wrap gap-4 text-sm">
            <label class="inline-flex items-center gap-2">
                <input type="radio" name="modo_publicacion" value="publicar" x-model="modo">
                <span>Publicar ahora (notifica al instante)</span>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="radio" name="modo_publicacion" value="borrador" x-model="modo">
                <span>Guardar como borrador</span>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="radio" name="modo_publicacion" value="programar" x-model="modo">
                <span>Agendar publicación</span>
            </label>
        </div>
        @error('modo_publicacion') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror

        <div x-show="modo === 'programar'" class="mt-3 max-w-xs">
            <label for="programado_para" class="block text-xs font-medium text-slate-700">Fecha y hora de publicación</label>
            <input id="programado_para" name="programado_para" type="datetime-local"
                   value="{{ $fechaProgramadaActual }}" class="{{ $campo }} bg-white">
            @error('programado_para') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
    </div>
@else
    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
        Este aviso ya fue publicado el {{ $aviso->publicado_en->format('d/m/Y H:i') }} hs. Guardar cambios actualizará el texto y registrará la fecha de edición sin volver a enviar notificaciones (M2-07).
    </div>
@endif

