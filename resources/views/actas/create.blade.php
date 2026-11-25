@extends('layouts.app')

@section('titulo', 'Subir acta · Eleven')

@section('contenido')
    <a href="{{ route('actas.index') }}" class="text-sm text-[#1F4E8C] underline">← Volver al historial</a>
    <h1 class="mt-2 text-2xl font-semibold">Subir acta</h1>
    <p class="mb-6 mt-1 max-w-2xl text-sm text-slate-600">
        Subí el acta en PDF y tu planilla de seguimiento <strong>actual</strong>. Primero vas a ver una vista previa
        de lo que se cargaría; no se escribe nada hasta que confirmes.
    </p>

    <form method="POST" action="{{ route('actas.analizar') }}" enctype="multipart/form-data"
          class="max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf

        <div>
            <label for="acta_pdf" class="block text-sm font-medium">Acta en PDF</label>
            <input id="acta_pdf" name="acta_pdf" type="file" accept=".pdf,application/pdf" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
            <p class="mt-1 text-xs text-slate-600">Hasta {{ $maxMb }} MB. Tiene que tener texto (no sirve un acta escaneada como imagen).</p>
            @error('acta_pdf') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="planilla" class="block text-sm font-medium">Planilla de seguimiento (.xlsx)</label>
            <input id="planilla" name="planilla" type="file" accept=".xlsx" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
            <p class="mt-1 text-xs text-slate-600">
                Hasta {{ $maxMb }} MB. Usá la última versión que generaste, para no perder las notas que ya cargaste.
            </p>
            @error('planilla') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">
            Analizar acta
        </button>
    </form>
@endsection
