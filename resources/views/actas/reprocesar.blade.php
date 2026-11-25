@extends('layouts.app')

@section('titulo', 'Reprocesar acta · Eleven')

@section('contenido')
    <a href="{{ route('actas.show', $acta) }}" class="text-sm text-[#1F4E8C] underline">← Volver al acta</a>
    <h1 class="mt-2 text-2xl font-semibold">Reprocesar acta</h1>

    <p class="mb-4 mt-1 max-w-2xl text-sm text-slate-600">
        Se vuelve a aplicar el acta ya guardada (<strong>{{ $acta->materia ?? 'sin identificar' }}</strong>,
        tomo {{ $acta->tomo ?? '—' }}, folio {{ $acta->folio ?? '—' }}) sobre la planilla que subas.
        <strong>No hace falta subir el PDF otra vez.</strong> Primero vas a ver una vista previa y no se escribe nada hasta que confirmes.
    </p>

    <div class="mb-4 max-w-2xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Subí tu planilla actual: la que tiene todo lo que cargaste hasta hoy. Si usás una versión vieja, la planilla nueva
        no tendría las notas que cargaste después. Las planillas que ya generaste para esta acta se conservan.
    </div>

    <form method="POST" action="{{ route('actas.reprocesar.guardar', $acta) }}" enctype="multipart/form-data"
          class="max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="planilla" class="block text-sm font-medium">Planilla de seguimiento actual (.xlsx)</label>
            <input id="planilla" name="planilla" type="file" accept=".xlsx" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
            @error('planilla') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">
            Analizar con esta planilla
        </button>
    </form>
@endsection
