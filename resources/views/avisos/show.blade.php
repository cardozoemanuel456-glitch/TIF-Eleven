@extends('layouts.app')

@section('titulo', $aviso->titulo.' · Eleven')

@section('contenido')
    @php
        $campanaNoLeidas = $campanaNoLeidas ?? 0;
        $campanaUltimas = $campanaUltimas ?? collect();
    @endphp

    <div class="grid max-w-5xl gap-6 lg:grid-cols-5">
        {{-- Columna izquierda: Detalle del aviso (Maqueta 16) --}}
        <div class="lg:col-span-3">
            @if (! $aviso->estaPublicado())
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
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

            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm md:p-7">
                <a href="{{ route('avisos.index') }}" class="text-sm font-medium text-[#1F4E8C] underline">← Volver a los avisos</a>

                <h1 class="mt-3 text-2xl font-semibold text-slate-900">{{ $aviso->titulo }}</h1>

                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-700">
                        {{ $aviso->etiquetaDestinatario() }}
                    </span>
                    @if ($aviso->estaPublicado())
                        <span>Publicado el {{ $aviso->publicado_en->format('d/m/Y H:i') }} hs</span>
                    @endif
                    @if ($aviso->fueEditado())
                        <span class="font-medium text-slate-700">
                            · Actualizado el {{ $aviso->editado_en->format('d/m/Y H:i') }} hs
                        </span>
                    @endif
                </div>

                <div class="mt-5 text-sm leading-relaxed text-slate-800">
                    {{ $aviso->contenidoFormateado() }}
                </div>

                @if ($aviso->evento)
                    <p class="mt-5 text-xs text-slate-600">
                        Evento vinculado:
                        <a href="{{ route('calendario.index') }}" class="font-medium text-[#1F4E8C] underline">
                            {{ $aviso->evento->tipo->etiqueta() }} · {{ $aviso->evento->titulo }} ({{ $aviso->evento->rangoFechasTexto() }})
                        </a>
                    </p>
                @endif

                @if ($puedeGestionar)
                    <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 text-sm">
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
        </div>

        {{-- Columna derecha: Panel de últimas 10 notificaciones (Maqueta 16) --}}
        <aside class="lg:col-span-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <h2 class="text-base font-semibold text-slate-900">
                        Notificaciones
                        @if ($campanaNoLeidas > 0)
                            · {{ $campanaNoLeidas }} {{ $campanaNoLeidas === 1 ? 'nueva' : 'nuevas' }}
                        @endif
                    </h2>
                    @if ($campanaNoLeidas > 0)
                        <form method="POST" action="{{ route('notificaciones.leer') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Marcar todas
                            </button>
                        </form>
                    @endif
                </div>

                <ul class="divide-y divide-slate-100">
                    @forelse ($campanaUltimas as $notif)
                        <li class="py-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    @if (! $notif['disponible'])
                                        <span class="italic text-slate-500">Aviso no disponible</span>
                                    @elseif ($notif['url'])
                                        <a href="{{ $notif['url'] }}" class="truncate font-medium text-slate-900 hover:text-[#1F4E8C] hover:underline">
                                            {{ $notif['titulo'] }}
                                        </a>
                                    @else
                                        <span class="truncate font-medium text-slate-900">{{ $notif['titulo'] }}</span>
                                    @endif

                                    @if (! $notif['leida'])
                                        <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                            Nuevo
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-xs text-slate-500">No tenés notificaciones registradas.</li>
                    @endforelse
                </ul>

                <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    Se muestran las últimas 10. La campana aparece siempre, aunque desactives los correos.
                </p>
            </section>
        </aside>
    </div>
@endsection
