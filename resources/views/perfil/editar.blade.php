@extends('layouts.app')

@section('titulo', 'Mi perfil · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Mi perfil</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        {{ $usuario->anio_cursada?->etiqueta() }} · DNI {{ $usuario->dni }}
        <span class="text-slate-500">(el DNI y el aula solo los cambia Preceptoría)</span>
    </p>

    <form method="POST" action="{{ route('perfil.update') }}" class="max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')

        <div>
            <label for="nombre" class="block text-sm font-medium">Nombre</label>
            <input id="nombre" name="nombre" type="text" value="{{ old('nombre', $usuario->nombre) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('nombre') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="apellido" class="block text-sm font-medium">Apellido</label>
            <input id="apellido" name="apellido" type="text" value="{{ old('apellido', $usuario->apellido) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('apellido') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email', $usuario->email) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Si lo cambiás, vas a tener que verificar la nueva dirección.</p>
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">Guardar</button>
    </form>
@endsection
