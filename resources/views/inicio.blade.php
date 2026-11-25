@extends('layouts.app')

@section('titulo', 'Inicio · Eleven')

@section('contenido')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Hola, {{ $usuario->nombre }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ $usuario->role?->nombre?->etiqueta() }}
            @if ($usuario->anio_cursada) · {{ $usuario->anio_cursada->etiqueta() }} @endif
        </p>
    </div>

    <div class="grid max-w-5xl gap-5 md:grid-cols-2">
        @if ($pendientes !== null)
            <section class="rounded-xl border border-amber-300 bg-amber-50/40 p-5 shadow-sm md:col-span-2">
                <h2 class="mb-1 font-semibold text-slate-900">Registros pendientes</h2>
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

        {{-- Avisos institucionales publicados (HU 17 y M0-05) --}}
        <section class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="mb-3 font-semibold text-slate-900">
                    {{ $usuario->esAlumno() ? 'Avisos de tu año' : 'Avisos institucionales' }}
                </h2>

                @if ($avisos->isEmpty())
                    <p class="py-2 text-sm text-slate-600">Todavía no hay avisos publicados para mostrar.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($avisos as $aviso)
                            @php $leido = $avisosLeidosIds->has($aviso->id); @endphp
                            <li class="py-2.5 first:pt-0 last:pb-0">
                                <a href="{{ route('avisos.show', $aviso) }}" class="group block">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium text-slate-900 group-hover:text-[#1F4E8C] group-hover:underline">
                                            {{ $aviso->titulo }}
                                        </span>
                                        @if (! $leido)
                                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Nuevo</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ $aviso->etiquetaDestinatario() }}</span>
                                        <span>·</span>
                                        <span>{{ $aviso->publicado_en?->format('d/m/Y') }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4">
                    <a href="{{ route('avisos.index') }}" class="text-sm font-medium text-[#1F4E8C] underline">
                        Ver todos los avisos
                    </a>
                </div>
            </div>
        </section>

        {{-- Próximos eventos del calendario académico (HU 17 y M0-05) --}}
        <section class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="mb-3 font-semibold text-slate-900">Próximos eventos</h2>

                @if ($proximosEventos->isEmpty())
                    <p class="py-2 text-sm text-slate-600">No hay eventos próximos programados.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($proximosEventos as $evento)
                            <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0 text-sm">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium {{ $evento->tipo->clasesBadge() }}">
                                        {{ $evento->tipo->etiqueta() }}
                                    </span>
                                    <span class="truncate font-medium text-slate-900">{{ $evento->titulo }}</span>
                                </div>
                                <span class="shrink-0 text-xs text-slate-500">{{ $evento->rangoFechasTexto() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4">
                    <a href="{{ route('calendario.index') }}" class="text-sm font-medium text-[#1F4E8C] underline">
                        Abrir el calendario
                    </a>
                </div>
            </div>
        </section>

        @if ($usuario->esAlumno())
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
                <h2 class="mb-1 font-semibold text-slate-900">Tus TIF</h2>
                <p class="text-sm text-slate-600">Cuando figures como autor de un trabajo integrador, lo vas a ver acá.</p>
            </section>
        @endif
    </div>
@endsection
