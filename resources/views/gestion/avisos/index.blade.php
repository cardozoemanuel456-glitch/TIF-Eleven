@extends('layouts.app')

@section('titulo', 'Gestión de avisos · Eleven')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Gestión de avisos</h1>
            <p class="mt-1 text-sm text-slate-600">Administrá borradores, avisos agendados y comunicados publicados.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('avisos.index') }}"
               class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Vista pública
            </a>
            <a href="{{ route('gestion.avisos.create') }}"
               class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                + Nuevo aviso
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('gestion.avisos.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título o contenido"
               class="w-full max-w-xs rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">

        <select name="estado" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="" @selected($estado === '')>Todos los estados</option>
            <option value="publicado" @selected($estado === 'publicado')>Publicados</option>
            <option value="programado" @selected($estado === 'programado')>Programados</option>
            <option value="borrador" @selected($estado === 'borrador')>Borradores</option>
        </select>

        <select name="anio" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="" @selected($anio === '')>Todos los destinatarios</option>
            <option value="general" @selected($anio === 'general')>General</option>
            @foreach (\App\Enums\AnioCursada::cases() as $anioCaso)
                <option value="{{ $anioCaso->value }}" @selected($anio === (string) $anioCaso->value)>
                    {{ $anioCaso->etiqueta() }}
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
                    <th class="px-4 py-3">Título</th>
                    <th class="px-4 py-3">Destinatario</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($avisos as $aviso)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('avisos.show', $aviso) }}" class="font-medium text-slate-900 hover:text-[#1F4E8C] hover:underline">
                                {{ $aviso->titulo }}
                            </a>
                            <p class="mt-0.5 line-clamp-1 text-xs text-slate-500">{{ $aviso->extracto(100) }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $aviso->etiquetaDestinatario() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $aviso->clasesEstado() }}">
                                {{ $aviso->etiquetaEstado() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            @if ($aviso->estaPublicado())
                                Publicado: {{ $aviso->publicado_en->format('d/m/Y H:i') }}
                                @if ($aviso->fueEditado())
                                    <br><span class="text-slate-500">Editado: {{ $aviso->editado_en->format('d/m/Y H:i') }}</span>
                                @endif
                            @elseif ($aviso->estaProgramado())
                                Programado: {{ $aviso->programado_para->format('d/m/Y H:i') }}
                            @else
                                Creado: {{ $aviso->created_at->format('d/m/Y H:i') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                @if (! $aviso->estaPublicado())
                                    <form method="POST" action="{{ route('gestion.avisos.publicar', $aviso) }}">
                                        @csrf
                                        <button type="submit" class="font-medium text-green-700 underline">Publicar</button>
                                    </form>
                                @endif
                                <a href="{{ route('gestion.avisos.edit', $aviso) }}" class="text-[#1F4E8C] underline">Editar</a>
                                <form method="POST" action="{{ route('gestion.avisos.destroy', $aviso) }}"
                                      onsubmit="return confirm('¿Eliminar este aviso institucional?')">
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
                            No se encontraron avisos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $avisos->links() }}</div>
@endsection

