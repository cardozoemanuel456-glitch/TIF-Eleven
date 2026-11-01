@php
    $esSelf = isset($usuario) && auth()->id() === $usuario->id;
    $rolActual = isset($usuario) ? $usuario->role?->nombre?->value : null;
    $campo = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2';
@endphp

<div>
    <label for="nombre" class="block text-sm font-medium">Nombre</label>
    <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $usuario->nombre ?? '') }}" required maxlength="100" class="{{ $campo }}">
    @error('nombre') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div>
    <label for="apellido" class="block text-sm font-medium">Apellido</label>
    <input id="apellido" name="apellido" type="text" value="{{ old('apellido', $usuario->apellido ?? '') }}" required maxlength="100" class="{{ $campo }}">
    @error('apellido') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div>
    <label for="email" class="block text-sm font-medium">Correo electrónico</label>
    <input id="email" name="email" type="email" value="{{ old('email', $usuario->email ?? '') }}" required maxlength="255" class="{{ $campo }}">
    @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>

<div>
    <label for="rol" class="block text-sm font-medium">Rol</label>
    <select id="rol" name="rol" class="{{ $campo }}" @disabled($esSelf)>
        @foreach (\App\Enums\Rol::personal() as $r)
            <option value="{{ $r->value }}" @selected(old('rol', $rolActual) === $r->value)>{{ $r->etiqueta() }}</option>
        @endforeach
    </select>
    @if ($esSelf)
        <input type="hidden" name="rol" value="{{ $rolActual }}">
        <p class="mt-1 text-xs text-slate-600">No podés cambiar tu propio rol.</p>
    @endif
    @error('rol') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
</div>