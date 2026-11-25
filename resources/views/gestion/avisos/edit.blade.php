@extends('layouts.app')

@section('titulo', 'Editar aviso · Eleven')

@section('contenido')
    <h1 class="mb-6 text-2xl font-semibold">Editar aviso institucional</h1>

    <form method="POST" action="{{ route('gestion.avisos.update', $aviso) }}"
          class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('gestion.avisos._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#12263F]">
                Guardar cambios
            </button>
            <a href="{{ route('gestion.avisos.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection

