@extends('layouts.app')

@section('titulo', 'Editar usuario · Eleven')

@section('contenido')
    <h1 class="mb-6 text-2xl font-semibold">Editar usuario</h1>

    <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}" class="max-w-md space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')
        @include('admin.usuarios._form')

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">Guardar cambios</button>
            <a href="{{ route('admin.usuarios.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection