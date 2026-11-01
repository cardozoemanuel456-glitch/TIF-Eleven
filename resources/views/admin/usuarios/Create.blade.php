@extends('layouts.app')

@section('titulo', 'Nuevo usuario · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Nuevo usuario del personal</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        Se genera una contraseña temporal que verás una sola vez. La persona deberá cambiarla en su primer ingreso.
    </p>

    <form method="POST" action="{{ route('admin.usuarios.store') }}" class="max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @include('admin.usuarios._form')

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">Crear usuario</button>
            <a href="{{ route('admin.usuarios.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection