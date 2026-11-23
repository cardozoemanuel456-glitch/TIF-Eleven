@extends('layouts.app')

@section('titulo', 'Inicio · Eleven')

@section('contenido')
    <h1 class="text-2xl font-semibold">Hola, {{ $usuario->nombre }}</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">
        {{ $usuario->role?->nombre?->etiqueta() }}
        @if ($usuario->anio_cursada) · {{ $usuario->anio_cursada->etiqueta() }} @endif
    </p>

    <div class="grid max-w-4xl gap-4 md:grid-cols-2">
        @if ($pendientes !== null)
            <section class="rounded-xl border border-slate-200 bg-white p-5 md:col-span-2">
                <h2 class="mb-1 font-semibold">Registros pendientes</h2>
                @if ($pendientes > 0)
                    <p class="text-sm text-slate-700">
                        {{ $pendientes }} {{ $pendientes === 1 ? 'alumno espera' : 'alumnos esperan' }} aprobación.
                    </p>
                    @if (Route::has('alumnos.index'))
                        <a href="{{ route('alumnos.index') }}" class="mt-2 inline-block text-sm font-medium text-[#1F4E8C] underline">Revisar registros</a>
                    @endif
                @else
                    <p class="text-sm text-slate-600">No hay registros esperando aprobación.</p>
                @endif
            </section>
        @endif

        @if ($usuario->esAlumno())
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-1 font-semibold">Avisos de tu año</h2>
                <p class="text-sm text-slate-600">Todavía no hay avisos para mostrar.</p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="mb-1 font-semibold">Próximos eventos</h2>
                <p class="text-sm text-slate-600">No hay eventos próximos.</p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 md:col-span-2">
                <h2 class="mb-1 font-semibold">Tus TIF</h2>
                <p class="text-sm text-slate-600">Cuando figures como autor de un trabajo integrador, lo vas a ver acá.</p>
            </section>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 md:col-span-2">
                <h2 class="mb-1 font-semibold">Tus funciones</h2>
                <p class="text-sm text-slate-600">
                    Los accesos de tu rol aparecen en el menú lateral a medida que se incorporan los módulos.
                </p>
            </section>
        @endif
    </div>
@endsection