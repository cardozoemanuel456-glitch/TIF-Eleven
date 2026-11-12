@extends('layouts.app')

@section('titulo', 'Acta · Eleven')

@section('contenido')
    <a href="{{ route('actas.index') }}" class="text-sm text-[#1F4E8C] underline">← Volver al historial</a>
    <h1 class="mt-2 text-2xl font-semibold">No se pudo continuar con esta acta</h1>

    <div class="mt-4 max-w-2xl rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
        {{ $mensaje ?? 'Ocurrió un problema al procesar el acta.' }}
    </div>

    <p class="mt-4 max-w-2xl text-sm text-slate-600">
        No se entregó ninguna planilla y tu planilla original no se modificó.
    </p>

    @if (Route::has('actas.create'))
        <a href="{{ route('actas.create') }}" class="mt-4 inline-block rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">Subir otra vez</a>
    @endif
@endsection
