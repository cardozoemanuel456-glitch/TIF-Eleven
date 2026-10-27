@extends('layouts.app')

@section('titulo', 'Inicio · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Hola, {{ $usuario->nombre }}</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        {{ $usuario->role?->nombre?->etiqueta() }}
        @if ($usuario->anio_cursada) · {{ $usuario->anio_cursada->etiqueta() }} @endif
    </p>

    <section class="max-w-xl rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-1 font-semibold">Tu espacio</h2>
        <p class="text-sm text-slate-600">
            Desde acá vas a acceder a los avisos, el calendario y el repositorio de TIF a medida que se
            incorporen los módulos de Eleven.
        </p>
    </section>
@endsection
