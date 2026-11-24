@extends('layouts.app')

@section('titulo', 'Alumnos · Eleven')

@section('contenido')
    <h1 class="mb-4 text-2xl font-semibold">Alumnos</h1>

    <form method="GET" action="{{ route('alumnos.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, apellido o DNI"
               class="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm">
        <select name="anio" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="">Todas las aulas</option>
            @foreach ($anios as $a)
                <option value="{{ $a->value }}" @selected($anio === (string) $a->value)>{{ $a->etiqueta() }}</option>
            @endforeach
        </select>
        <select name="estado" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="">Todos los estados</option>
            @foreach ($estados as $e)
                <option value="{{ $e->value }}" @selected($estado === $e->value)>{{ $e->etiqueta() }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Alumno</th>
                    <th class="px-4 py-3">DNI</th>
                    <th class="px-4 py-3">Aula</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($alumnos as $a)
                    @php
                        $color = match ($a->estado) {
                            \App\Enums\EstadoUsuario::Activo => 'bg-green-100 text-green-800',
                            \App\Enums\EstadoUsuario::Pendiente => 'bg-amber-100 text-amber-800',
                            default => 'bg-slate-200 text-slate-700',
                        };
                    @endphp
                    <tr class="{{ $a->estado === \App\Enums\EstadoUsuario::Pendiente ? 'bg-amber-50' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $a->nombreCompleto() }}</td>
                        <td class="px-4 py-3">{{ $a->dni ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $a->anio_cursada?->etiqueta() ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $a->estado->etiqueta() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('alumnos.show', ['alumno' => $a]) }}" class="text-[#1F4E8C] underline">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No se encontraron alumnos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $alumnos->links() }}</div>
@endsection
