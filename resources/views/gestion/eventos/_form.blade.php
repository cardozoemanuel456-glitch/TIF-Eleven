@php
    $campo = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm';
    $tipoActual = old('tipo', isset($evento) ? $evento->tipo->value : \App\Enums\TipoEvento::Parcial->value);
    $anioActual = old('anio_cursada', isset($evento) && $evento->anio_cursada ? (string) $evento->anio_cursada->value : '');
    $fechaInicioActual = old('fecha_inicio', isset($evento) ? $evento->fecha_inicio->format('Y-m-d') : ($fechaSugerida ?? ''));
    $fechaFinActual = old('fecha_fin', isset($evento) ? $evento->fecha_fin->format('Y-m-d') : ($fechaSugerida ?? ''));
    $horaActual = old('hora', isset($evento) ? ($evento->horaFormateada() ?? '') : '');
@endphp

<div>
    <label for="titulo" class="block text-sm font-medium">Título del evento</label>
    <input id="titulo" name="titulo" type="text" value="{{ old('titulo', $evento->titulo ?? '') }}"
           required maxlength="150" placeholder="Ej.: Primer parcial de Programación II" class="{{ $campo }}">
    @error('titulo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="tipo" class="block text-sm font-medium">Tipo de fecha</label>
        <select id="tipo" name="tipo" required class="{{ $campo }}">
            @foreach ($tipos as $tipoCaso)
                <option value="{{ $tipoCaso->value }}" @selected($tipoActual === $tipoCaso->value)>
                    {{ $tipoCaso->etiqueta() }}
                </option>
            @endforeach
        </select>
        @error('tipo') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="anio_cursada" class="block text-sm font-medium">Destinatario</label>
        <select id="anio_cursada" name="anio_cursada" class="{{ $campo }}">
            <option value="" @selected($anioActual === '')>General (toda la carrera)</option>
            @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                <option value="{{ $anioCaso->value }}" @selected($anioActual === (string) $anioCaso->value)>
                    {{ $anioCaso->etiqueta() }}
                </option>
            @endforeach
        </select>
        @error('anio_cursada') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div>
        <label for="fecha_inicio" class="block text-sm font-medium">Fecha de inicio</label>
        <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ $fechaInicioActual }}" required class="{{ $campo }}">
        @error('fecha_inicio') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="fecha_fin" class="block text-sm font-medium">Fecha de fin (opcional)</label>
        <input id="fecha_fin" name="fecha_fin" type="date" value="{{ $fechaFinActual }}" class="{{ $campo }}">
        <p class="mt-1 text-xs text-slate-500">Si se deja vacía, coincide con la de inicio.</p>
        @error('fecha_fin') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="hora" class="block text-sm font-medium">Hora (opcional)</label>
        <input id="hora" name="hora" type="time" value="{{ $horaActual }}" class="{{ $campo }}">
        @error('hora') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
    </div>
</div>

<div>
    <label for="descripcion" class="block text-sm font-medium">Descripción (opcional)</label>
    <textarea id="descripcion" name="descripcion" rows="4" maxlength="5000"
              placeholder="Temario, modalidad, aula física u observaciones adicionales..."
              class="{{ $campo }}">{{ old('descripcion', $evento->descripcion ?? '') }}</textarea>
    @error('descripcion') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

