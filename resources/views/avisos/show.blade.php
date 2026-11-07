@extends('layouts.app')

@section('titulo', $aviso->titulo.' · Eleven')

@section('contenido')
    <div class="mb-4">
        <a href="{{ route('avisos.index') }}" class="text-sm font-medium text-[#1F4E8C] underline">← Volver a avisos</a>
    </div>

    @if (! $aviso->estaPublicado())
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 max-w-3xl">
            <div>
                @if ($aviso->estaProgramado())
                    Este aviso aún no fue publicado. Está <strong>programado</strong> para el {{ $aviso->programado_para->format('d/m/Y H:i') }} hs.
                @else
                    Este aviso está en <strong>borrador</strong> y solo es visible para preceptoría y administración.
                @endif
            </div>
            @if ($puedeGestionar)
                <form method="POST" action="{{ route('gestion.avisos.publicar', $aviso) }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-[#1F4E8C] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#12263F]">
                        Publicar ahora
                    </button>
                </form>
            @endif
        </div>
    @endif

    <article class="max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-slate-100 px-3 py-0.5 text-xs font-medium text-slate-700">
                Destinatario: {{ $aviso->etiquetaDestinatario() }}
            </span>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $aviso->clasesEstado() }}">
                {{ $aviso->etiquetaEstado() }}
            </span>
        </div>

        <h1 class="mt-3 text-2xl font-semibold text-slate-900">{{ $aviso->titulo }}</h1>

        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            <span>Por {{ $aviso->autor?->nombreCompleto() ?? 'Equipo de Eleven' }}</span>
            @if ($aviso->estaPublicado())
                <span>· Publicado el {{ $aviso->publicado_en->format('d/m/Y \a \l\a\s H:i') }} hs</span>
            @endif
            @if ($aviso->fueEditado())
                <span class="font-medium text-slate-700">
                    · Actualizado el {{ $aviso->editado_en->format('d/m/Y \a \l\a\s H:i') }} hs
                </span>
            @endif
        </div>

        @if ($aviso->evento)
            <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Evento académico vinculado</p>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $aviso->evento->titulo }}</p>
                        <p class="text-xs text-slate-600">
                            {{ $aviso->evento->tipo->etiqueta() }} · {{ $aviso->evento->rangoFechasTexto() }}
                        </p>
                    </div>
                    <a href="{{ route('calendario.index') }}" class="text-xs font-medium text-[#1F4E8C] underline">
                        Ver en calendario
                    </a>
                </div>
            </div>
        @endif

        <div class="mt-6 border-t border-slate-100 pt-5 text-sm leading-relaxed text-slate-800">
            {{ $aviso->contenidoFormateado() }}
        </div>

        @if ($puedeGestionar)
            <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 text-sm">
                <a href="{{ route('gestion.avisos.edit', $aviso) }}"
                   class="rounded-md bg-[#1F4E8C] px-3.5 py-2 font-medium text-white hover:bg-[#12263F]">
                    Editar aviso
                </a>
                <form method="POST" action="{{ route('gestion.avisos.destroy', $aviso) }}"
                      onsubmit="return confirm('¿Eliminar este aviso? Las notificaciones asociadas pasarán a mostrar «Aviso no disponible».')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-red-300 bg-white px-3.5 py-2 font-medium text-red-700 hover:bg-red-50">
                        Eliminar
                    </button>
                </form>
            </div>
        @endif
    </article>
@endsection

