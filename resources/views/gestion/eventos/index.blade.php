@extends('layouts.app')

@section('titulo', 'Gestión de eventos · Eleven')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Gestión de eventos académicos</h1>
            <p class="mt-1 text-sm text-slate-600">Registrá, modificá o eliminá fechas del calendario institucional.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('calendario.index') }}"
               class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Ver calendario
            </a>
            <a href="{{ route('gestion.eventos.create') }}"
               class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                + Nuevo evento
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('gestion.eventos.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título o descripción"
               class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">

        <select name="anio" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="" @selected($anio === '')>Todos los destinatarios</option>
            <option value="general" @selected($anio === 'general')>General</option>
            @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                <option value="{{ $anioCaso->value }}" @selected($anio === (string) $anioCaso->value)>
                    {{ $anioCaso->etiqueta() }}
                </option>
            @endforeach
        </select>

        <select name="tipo" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="" @selected($tipo === '')>Todos los tipos</option>
            @foreach ($tipos as $tipoCaso)
                <option value="{{ $tipoCaso->value }}" @selected($tipo === $tipoCaso->value)>
                    {{ $tipoCaso->etiqueta() }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Filtrar
        </button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Fecha y hora</th>
                    <th class="px-4 py-3">Destinatario</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($eventos as $evento)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $evento->titulo }}</p>
                            @if ($evento->descripcion)
                                <p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $evento->descripcion }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $evento->tipo->clasesBadge() }}">
                                <span class="h-2 w-2 rounded-full" style="background-color: {{ $evento->tipo->color() }}"></span>
                                {{ $evento->tipo->etiqueta() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $evento->rangoFechasTexto() }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $evento->etiquetaDestinatario() }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('gestion.eventos.edit', $evento) }}" class="text-[#1F4E8C] underline">Editar</a>
                                <form method="POST" action="{{ route('gestion.eventos.destroy', $evento) }}"
                                      onsubmit="return confirm('¿Eliminar este evento académico? Esta acción requiere tu confirmación y no se puede deshacer.')">
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
                            No hay eventos registrados con esos criterios.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $eventos->links() }}</div>
@endsection

