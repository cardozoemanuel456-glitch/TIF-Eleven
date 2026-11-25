@extends('layouts.app')

@section('titulo', 'Nuevo evento · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Nuevo evento académico</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        Cargá parciales, mesas de examen, feriados, recesos u otras fechas importantes de la subsede.
    </p>

    <form method="POST" action="{{ route('gestion.eventos.store') }}"
          class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('gestion.eventos._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#12263F]">
                Registrar evento
            </button>
            <a href="{{ route('gestion.eventos.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection

