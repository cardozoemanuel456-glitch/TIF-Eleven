@extends('layouts.app')

@section('titulo', 'Inicio · Eleven')

@section('contenido')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Hola, {{ $usuario->nombre }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ $usuario->role?->nombre?->etiqueta() }}
                @if ($usuario->anio_cursada) · {{ $usuario->anio_cursada->etiqueta() }} @endif
            </p>
        </div>

        @if ($puedeGestionarComunicaciones)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('gestion.eventos.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-800 shadow-sm hover:bg-slate-50">
                    <span>+</span> Nuevo evento
                </a>
                <a href="{{ route('gestion.avisos.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F4E8C] px-3.5 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#12263F]">
                    <span>+</span> Nuevo aviso
                </a>
            </div>
        @endif
    </div>

    <div class="grid max-w-5xl gap-5 md:grid-cols-2">
        @if ($pendientes !== null)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
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
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-900">
                        {{ $usuario->esAlumno() ? 'Avisos para vos' : 'Avisos institucionales' }}
                    </h2>
                    <a href="{{ route('avisos.index') }}" class="text-xs font-medium text-[#1F4E8C] underline">Ver todos</a>
                </div>

                @if ($avisos->isEmpty())
                    <p class="py-4 text-sm text-slate-600">Todavía no hay avisos publicados para mostrar.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($avisos as $aviso)
                            @php $leido = $avisosLeidosIds->has($aviso->id); @endphp
                            <li class="py-2.5 first:pt-0 last:pb-0">
                                <a href="{{ route('avisos.show', $aviso) }}" class="group block">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate text-sm font-medium text-slate-900 group-hover:text-[#1F4E8C] group-hover:underline">
                                            {{ $aviso->titulo }}
                                        </span>
                                        @if (! $leido)
                                            <span class="shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-[#1F4E8C]">Sin leer</span>
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
            </div>
        </section>

        {{-- Próximos eventos del calendario académico (HU 17 y M0-05) --}}
        <section class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-slate-900">Próximos eventos</h2>
                    <a href="{{ route('calendario.index') }}" class="text-xs font-medium text-[#1F4E8C] underline">Abrir calendario</a>
                </div>

                @if ($proximosEventos->isEmpty())
                    <p class="py-4 text-sm text-slate-600">No hay eventos próximos programados.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($proximosEventos as $evento)
                            <li class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                                      style="background-color: {{ $evento->tipo->color() }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $evento->titulo }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-600">
                                        <span class="rounded border px-1.5 py-0.5 text-[11px] font-medium {{ $evento->tipo->clasesBadge() }}">
                                            {{ $evento->tipo->etiqueta() }}
                                        </span>
                                        <span>{{ $evento->rangoFechasTexto() }}</span>
                                        <span>· {{ $evento->etiquetaDestinatario() }}</span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        @if ($usuario->esAlumno())
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
                <h2 class="mb-1 font-semibold text-slate-900">Tus TIF</h2>
                <p class="text-sm text-slate-600">Cuando figures como autor de un trabajo integrador, lo vas a ver acá.</p>
            </section>
        @elseif ($puedeGestionarComunicaciones)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
                <h2 class="mb-2 font-semibold text-slate-900">Accesos rápidos de gestión</h2>
                <div class="flex flex-wrap gap-3 text-sm">
                    <a href="{{ route('gestion.eventos.index') }}" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-[#1F4E8C] hover:bg-slate-100">
                        Administrar fechas del calendario
                    </a>
                    <a href="{{ route('gestion.avisos.index') }}" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-[#1F4E8C] hover:bg-slate-100">
                        Administrar avisos y borradores
                    </a>
                    <a href="{{ route('gestion.notificaciones.index') }}" class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-medium text-[#1F4E8C] hover:bg-slate-100">
                        Programar recordatorios
                    </a>
                </div>
            </section>
        @endif
    </div>
@endsection
