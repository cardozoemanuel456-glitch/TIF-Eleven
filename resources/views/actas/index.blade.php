@extends('layouts.app')

@section('titulo', 'Actas · Eleven')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Actas</h1>
            <p class="mt-1 text-sm text-slate-600">Historial de actas cargadas en la planilla de seguimiento.</p>
        </div>
        @if (Route::has('actas.create'))
            <a href="{{ route('actas.create') }}"
               class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">Subir acta</a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Materia</th>
                    <th class="px-4 py-3">Tomo</th>
                    <th class="px-4 py-3">Folio</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Cargada por</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($actas as $acta)
                    @php
                        $color = match ($acta->estado) {
                            \App\Enums\EstadoActa::Procesada => 'bg-green-100 text-green-800',
                            \App\Enums\EstadoActa::Error => 'bg-red-100 text-red-800',
                            default => 'bg-amber-100 text-amber-800',
                        };
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3">{{ $acta->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('actas.show', $acta) }}" class="text-[#1F4E8C] underline">{{ $acta->materia ?? 'Sin identificar' }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $acta->tomo ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $acta->folio ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $acta->estado->etiqueta() }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $acta->user?->nombreCompleto() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-600">Todavía no se procesaron actas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $actas->links() }}</div>
@endsection
