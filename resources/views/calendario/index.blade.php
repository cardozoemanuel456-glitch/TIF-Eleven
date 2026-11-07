@extends('layouts.app')

@section('titulo', 'Calendario académico · Eleven')

@push('estilos')
    <style>
        .fc {
            --fc-border-color: #e2e8f0;
            --fc-button-bg-color: #ffffff;
            --fc-button-border-color: #cbd5e1;
            --fc-button-text-color: #1e293b;
            --fc-button-hover-bg-color: #f8fafc;
            --fc-button-hover-border-color: #94a3b8;
            --fc-button-active-bg-color: #1F4E8C;
            --fc-button-active-border-color: #1F4E8C;
            --fc-today-bg-color: #eff6ff;
            font-family: inherit;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            color: #ffffff;
        }
        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            text-transform: capitalize;
        }
        .fc .fc-col-header-cell-cushion {
            padding: 0.5rem 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            text-transform: capitalize;
        }
        .fc .fc-daygrid-event {
            border-radius: 0.375rem;
            padding: 1px 5px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
        }
        .fc .fc-list-event {
            cursor: pointer;
        }
    </style>
@endpush

@section('contenido')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Calendario académico</h1>
            <p class="mt-1 text-sm text-slate-600">
                @if ($usuario->esAlumno())
                    Fechas institucionales generales
                    @if ($usuario->anio_cursada) y de {{ $usuario->anio_cursada->etiqueta() }} @endif
                @else
                    Fechas académicas generales y por año de cursada
                @endif
            </p>
        </div>

        @if ($puedeGestionar)
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('gestion.eventos.index') }}"
                   class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Gestionar eventos
                </a>
                <a href="{{ route('gestion.eventos.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-900 shadow-sm hover:bg-slate-50">
                    <span class="text-base leading-none">+</span> Nuevo evento
                </a>
                <a href="{{ route('gestion.avisos.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-[#1F4E8C] px-3.5 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#12263F]">
                    <span class="text-base leading-none">+</span> Nuevo aviso
                </a>
            </div>
        @endif
    </div>

    {{-- Filtros de visualización (M2-02 y M2-03) --}}
    <form id="form-filtros-calendario" method="GET" action="{{ route('calendario.index') }}"
          class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @if ($usuario->esPersonal())
            <div>
                <label for="filtro-anio" class="block text-xs font-medium text-slate-600">Destinatario</label>
                <select id="filtro-anio" name="anio" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                    <option value="" @selected($filtroAnio === '')>Todos los años y generales</option>
                    <option value="general" @selected($filtroAnio === 'general')>Solo generales</option>
                    @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                        <option value="{{ $anioCaso->value }}" @selected($filtroAnio === (string) $anioCaso->value)>
                            {{ $anioCaso->etiqueta() }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="filtro-tipo" class="block text-xs font-medium text-slate-600">Tipo de fecha</label>
            <select id="filtro-tipo" name="tipo" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm">
                <option value="" @selected($filtroTipo === '')>Todos los tipos</option>
                @foreach ($tipos as $tipoCaso)
                    <option value="{{ $tipoCaso->value }}" @selected($filtroTipo === $tipoCaso->value)>
                        {{ $tipoCaso->etiqueta() }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-1.5 text-sm font-medium text-white hover:bg-[#12263F]">
            Filtrar
        </button>

        @if ($filtroAnio !== '' || $filtroTipo !== '')
            <a href="{{ route('calendario.index') }}" class="px-2 py-1.5 text-sm text-slate-600 underline">Limpiar</a>
        @endif
    </form>

    {{-- Tarjeta principal estilo Eleven (grilla/lista + columna lateral de referencias y próximos eventos) --}}
    <div class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-3 md:p-7">
        <div class="lg:col-span-2 min-w-0">
            <div id="calendario-eleven" class="min-h-[520px]"></div>
        </div>

        <aside class="flex flex-col gap-6 border-t border-slate-100 pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Próximas fechas</h2>
                @if ($proximosEventos->isEmpty())
                    <p class="mt-2 text-sm text-slate-600">No hay eventos vigentes con los filtros actuales.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($proximosEventos as $ev)
                            <li>
                                <button type="button"
                                        data-evento-lateral="{{ json_encode([
                                            'titulo' => $ev->titulo,
                                            'tipo_etiqueta' => $ev->tipo->etiqueta(),
                                            'clases_badge' => $ev->tipo->clasesBadge(),
                                            'color' => $ev->tipo->color(),
                                            'destinatario' => $ev->etiquetaDestinatario(),
                                            'descripcion' => $ev->descripcion ?? '',
                                            'rango_fechas' => $ev->rangoFechasTexto(),
                                            'puede_gestionar' => $puedeGestionar,
                                            'url_editar' => $puedeGestionar ? route('gestion.eventos.edit', $ev) : null,
                                            'url_eliminar' => $puedeGestionar ? route('gestion.eventos.destroy', $ev) : null,
                                        ]) }}"
                                        class="flex w-full items-start gap-3 rounded-lg p-2 text-left transition hover:bg-slate-50">
                                    <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full"
                                          style="background-color: {{ $ev->tipo->color() }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $ev->titulo }}</p>
                                        <p class="text-xs text-slate-600">
                                            {{ $ev->tipo->etiqueta() }} · {{ $ev->rangoFechasTexto() }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">{{ $ev->etiquetaDestinatario() }}</p>
                                    </div>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="border-t border-slate-100 pt-4">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tipos de evento</h2>
                <ul class="mt-2.5 grid grid-cols-2 gap-2 text-xs text-slate-700">
                    @foreach ($tipos as $tipoCaso)
                        <li class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $tipoCaso->color() }}"></span>
                            <span>{{ $tipoCaso->etiqueta() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($avisosRecientes->isNotEmpty())
                <div class="border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Últimos avisos</h2>
                        <a href="{{ route('avisos.index') }}" class="text-xs font-medium text-[#1F4E8C] underline">Ver todos</a>
                    </div>
                    <ul class="mt-2 space-y-2">
                        @foreach ($avisosRecientes as $aviso)
                            <li>
                                <a href="{{ route('avisos.show', $aviso) }}" class="block rounded-md p-1.5 text-xs hover:bg-slate-50">
                                    <span class="font-medium text-slate-800 hover:text-[#1F4E8C] hover:underline">{{ $aviso->titulo }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ $aviso->etiquetaDestinatario() }} · {{ $aviso->publicado_en?->format('d/m/Y') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>

    {{-- Modal de detalle del evento --}}
    <div id="modal-evento" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span id="modal-evento-tipo" class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-medium"></span>
                    <h3 id="modal-evento-titulo" class="mt-2 text-lg font-semibold text-slate-900"></h3>
                </div>
                <button type="button" id="cerrar-modal-evento" aria-label="Cerrar" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    ✕
                </button>
            </div>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex gap-2">
                    <dt class="font-medium text-slate-600">Fecha:</dt>
                    <dd id="modal-evento-fecha" class="text-slate-900"></dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium text-slate-600">Destinatario:</dt>
                    <dd id="modal-evento-destinatario" class="text-slate-900"></dd>
                </div>
                <div id="modal-evento-descripcion-bloque" class="pt-2">
                    <dt class="font-medium text-slate-600">Descripción:</dt>
                    <dd id="modal-evento-descripcion" class="mt-1 whitespace-pre-line rounded-md bg-slate-50 p-3 text-slate-800"></dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <div id="modal-evento-acciones" class="hidden items-center gap-3">
                    <a id="modal-evento-editar" href="#" class="rounded-md bg-[#1F4E8C] px-3.5 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                        Editar
                    </a>
                    <form id="modal-evento-eliminar-form" method="POST" action="#"
                          onsubmit="return confirm('¿Seguro que querés eliminar este evento del calendario?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-300 bg-white px-3.5 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                            Eliminar
                        </button>
                    </form>
                </div>
                <button type="button" id="cerrar-modal-evento-pie" class="ml-auto rounded-md border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const contenedor = document.getElementById('calendario-eleven');
            const modal = document.getElementById('modal-evento');
            const endpointBase = @json(route('calendario.eventos'));
            const urlCrearEvento = @json($puedeGestionar ? route('gestion.eventos.create') : null);
            const selectAnio = document.getElementById('filtro-anio');
            const selectTipo = document.getElementById('filtro-tipo');

            function abrirModalEvento(props) {
                document.getElementById('modal-evento-titulo').textContent = props.titulo || '';
                const badge = document.getElementById('modal-evento-tipo');
                badge.textContent = props.tipo_etiqueta || 'Evento';
                badge.className = 'inline-block rounded-full border px-2.5 py-0.5 text-xs font-medium ' + (props.clases_badge || 'bg-slate-100 text-slate-800 border-slate-200');

                document.getElementById('modal-evento-fecha').textContent = props.rango_fechas || '';
                document.getElementById('modal-evento-destinatario').textContent = props.destinatario || 'General';

                const bloqueDesc = document.getElementById('modal-evento-descripcion-bloque');
                const textoDesc = document.getElementById('modal-evento-descripcion');
                if (props.descripcion && props.descripcion.trim() !== '') {
                    textoDesc.textContent = props.descripcion;
                    bloqueDesc.classList.remove('hidden');
                } else {
                    textoDesc.textContent = '';
                    bloqueDesc.classList.add('hidden');
                }

                const acciones = document.getElementById('modal-evento-acciones');
                if (props.puede_gestionar && props.url_editar && props.url_eliminar) {
                    document.getElementById('modal-evento-editar').setAttribute('href', props.url_editar);
                    document.getElementById('modal-evento-eliminar-form').setAttribute('action', props.url_eliminar);
                    acciones.classList.remove('hidden');
                    acciones.classList.add('flex');
                } else {
                    acciones.classList.add('hidden');
                    acciones.classList.remove('flex');
                }

                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function cerrarModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.getElementById('cerrar-modal-evento').addEventListener('click', cerrarModal);
            document.getElementById('cerrar-modal-evento-pie').addEventListener('click', cerrarModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    cerrarModal();
                }
            });

            document.querySelectorAll('[data-evento-lateral]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    try {
                        abrirModalEvento(JSON.parse(btn.getAttribute('data-evento-lateral')));
                    } catch (_) {}
                });
            });

            if (typeof FullCalendar !== 'undefined' && contenedor) {
                const calendario = new FullCalendar.Calendar(contenedor, {
                    locale: 'es',
                    initialView: window.innerWidth < 640 ? 'listMonth' : 'dayGridMonth',
                    firstDay: 1,
                    height: 'auto',
                    dayMaxEvents: 3,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth',
                    },
                    buttonText: {
                        today: 'Hoy',
                        month: 'Mes',
                        list: 'Lista',
                    },
                    noEventsContent: 'No hay eventos académicos en este período.',
                    events: function (info, successCallback, failureCallback) {
                        const params = new URLSearchParams({
                            start: info.startStr,
                            end: info.endStr,
                        });
                        if (selectAnio && selectAnio.value) {
                            params.set('anio', selectAnio.value);
                        }
                        if (selectTipo && selectTipo.value) {
                            params.set('tipo', selectTipo.value);
                        }

                        fetch(endpointBase + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                        })
                            .then(function (resp) {
                                if (!resp.ok) {
                                    throw new Error('Error al cargar eventos');
                                }
                                return resp.json();
                            })
                            .then(function (datos) {
                                successCallback(datos);
                            })
                            .catch(function (err) {
                                failureCallback(err);
                            });
                    },
                    eventClick: function (info) {
                        info.jsEvent.preventDefault();
                        abrirModalEvento(info.event.extendedProps);
                    },
                    dateClick: function (info) {
                        if (urlCrearEvento) {
                            window.location.href = urlCrearEvento + '?fecha=' + encodeURIComponent(info.dateStr);
                        }
                    },
                });

                calendario.render();
            }
        });
    </script>
@endpush

