@extends('layouts.app')

@section('titulo', 'Cambiar contraseña · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Cambiar contraseña</h1>

    @if ($obligatorio)
        <p class="mb-6 mt-1 max-w-lg rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Tu cuenta usa una contraseña temporal. Elegí una nueva para poder usar Eleven.
        </p>
    @else
        <p class="mb-6 mt-1 text-sm text-slate-600">Usá una contraseña que no uses en otros sitios.</p>
    @endif

    <form method="POST" action="{{ route('password.actualizar') }}" class="max-w-sm space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')

        <div>
            <label for="password_actual" class="block text-sm font-medium">Contraseña actual</label>
            <input id="password_actual" name="password_actual" type="password" required autocomplete="current-password" autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('password_actual') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Nueva contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-600">Mínimo 8 caracteres, con letras y números.</p>
            @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Repetir nueva contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">
            Guardar contraseña
        </button>
    </form>
@endsection