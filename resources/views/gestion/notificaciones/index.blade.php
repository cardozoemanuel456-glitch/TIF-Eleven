@extends('layouts.app')

@section('titulo', 'Programación de notificaciones · Eleven')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Recordatorios y notificaciones programadas</h1>
            <p class="mt-1 text-sm text-slate-600">
                Programá recordatorios vinculados a fechas o avisos académicos para enviarlos en el momento oportuno.
            </p>
        </div>
        <a href="{{ route('gestion.notificaciones.create') }}"
           class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
            + Programar notificación
        </a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Contenido</th>
                    <th class="px-4 py-3">Destinatario</th>
                    <th class="px-4 py-3">Programada para</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($programadas as $prog)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $prog->titulo }}</p>
                            <p class="mt-0.5 line-clamp-1 text-xs text-slate-600">{{ $prog->contenido }}</p>
                            @if ($prog->evento)
                                <p class="mt-0.5 text-[11px] text-slate-500">Evento: {{ $prog->evento->titulo }}</p>
                            @endif
                            @if ($prog->aviso)
                                <p class="mt-0.5 text-[11px] text-slate-500">Aviso: {{ $prog->aviso->titulo }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $prog->etiquetaDestinatario() }}</td>
                        <td class="px-4 py-3 text-xs text-slate-700">
                            {{ $prog->programada_para->format('d/m/Y H:i') }} hs
                            @if ($prog->enviada_en)
                                <br><span class="text-slate-500">Enviada: {{ $prog->enviada_en->format('d/m/Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $prog->estado->clasesBadge() }}">
                                {{ $prog->estado->etiqueta() }}
                            </span>
                            @if ($prog->mensaje_error)
                                <p class="mt-1 text-xs text-red-700">{{ $prog->mensaje_error }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($prog->estaPendiente())
                                    <form method="POST" action="{{ route('gestion.notificaciones.enviar', $prog) }}">
                                        @csrf
                                        <button type="submit" class="text-[#1F4E8C] underline">Enviar ahora</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('gestion.notificaciones.destroy', $prog) }}"
                                      onsubmit="return confirm('¿Eliminar esta programación?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-700 underline">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-600">
                            No hay notificaciones programadas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $programadas->links() }}</div>
@endsection

