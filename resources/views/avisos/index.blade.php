@extends('layouts.app')

@section('titulo', 'Avisos · Eleven')

@section('contenido')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Avisos institucionales</h1>
            <p class="mt-1 text-sm text-slate-600">
                @if ($usuario->esAlumno())
                    Comunicaciones generales
                    @if ($usuario->anio_cursada) y de {{ $usuario->anio_cursada->etiqueta() }} @endif
                @else
                    Novedades y comunicados publicados en Eleven
                @endif
            </p>
        </div>

        @if ($puedeGestionar)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('gestion.avisos.index') }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span>Gestionar avisos</span>
                    @if ($noPublicadosCount > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                            {{ $noPublicadosCount }} sin publicar
                        </span>
                    @endif
                </a>
                <a href="{{ route('gestion.avisos.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F4E8C] px-3.5 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#12263F]">
                    <span>+</span> Nuevo aviso
                </a>
            </div>
        @endif
    </div>

    @if ($usuario->esPersonal())
        <form method="GET" action="{{ route('avisos.index') }}"
              class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <label for="anio" class="block text-xs font-medium text-slate-600">Destinatario</label>
                <select id="anio" name="anio" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    <option value="" @selected($filtroAnio === '')>Todos los destinatarios</option>
                    <option value="general" @selected($filtroAnio === 'general')>Solo generales</option>
                    @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                        <option value="{{ $anioCaso->value }}" @selected($filtroAnio === (string) $anioCaso->value)>
                            {{ $anioCaso->etiqueta() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#12263F]">
                Filtrar
            </button>
            @if ($filtroAnio !== '')
                <a href="{{ route('avisos.index') }}" class="px-2 py-1.5 text-sm text-slate-600 underline">Limpiar</a>
            @endif
        </form>
    @endif

    <div class="max-w-4xl space-y-3">
        @forelse ($avisos as $aviso)
            @php $leido = $leidosIds->has($aviso->id); @endphp
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 {{ $leido ? '' : 'border-l-4 border-l-[#1F4E8C]' }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                            {{ $aviso->etiquetaDestinatario() }}
                        </span>
                        @if (! $leido)
                            <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-[#1F4E8C]">
                                Sin leer
                            </span>
                        @endif
                        @if ($aviso->evento)
                            <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs text-slate-600">
                                Evento: {{ $aviso->evento->titulo }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500">
                        Publicado el {{ $aviso->publicado_en?->format('d/m/Y H:i') }} hs
                        @if ($aviso->fueEditado())
                            · <span class="font-medium text-slate-600">Actualizado el {{ $aviso->editado_en?->format('d/m/Y H:i') }} hs</span>
                        @endif
                    </div>
                </div>

                <h2 class="mt-2 text-lg font-semibold text-slate-900">
                    <a href="{{ route('avisos.show', $aviso) }}" class="hover:text-[#1F4E8C] hover:underline">
                        {{ $aviso->titulo }}
                    </a>
                </h2>

                <p class="mt-1.5 text-sm text-slate-600">{{ $aviso->extracto(240) }}</p>

                <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                    <span>Por {{ $aviso->autor?->nombreCompleto() ?? 'Equipo de Eleven' }}</span>
                    <a href="{{ route('avisos.show', $aviso) }}" class="font-medium text-[#1F4E8C] underline">Leer aviso completo</a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 shadow-sm">
                No hay avisos publicados para mostrar en este momento.
            </div>
        @endforelse
    </div>

    <div class="mt-5 max-w-4xl">{{ $avisos->links() }}</div>
@endsection

