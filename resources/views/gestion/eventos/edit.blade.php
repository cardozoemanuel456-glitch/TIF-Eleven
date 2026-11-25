@extends('layouts.app')

@section('titulo', 'Editar evento · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Editar evento académico</h1>

    @if ($evento->tipo->llevaRecordatorio())
        <p class="mb-6 mt-2 max-w-2xl rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Si modificás la fecha de inicio de este <strong>{{ strtolower($evento->tipo->etiqueta()) }}</strong>, se notificará la reprogramación a los alumnos activos destinatarios y se reiniciará su recordatorio automático.
        </p>
    @else
        <p class="mb-6 mt-1 text-sm text-slate-600">Actualizá los datos de la fecha en el calendario institucional.</p>
    @endif

    <form method="POST" action="{{ route('gestion.eventos.update', $evento) }}"
          class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('gestion.eventos._form')

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#12263F]">
                Guardar cambios
            </button>
            <a href="{{ route('gestion.eventos.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
        </div>
    </form>
@endsection

