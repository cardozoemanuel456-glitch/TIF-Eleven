@extends('layouts.app')

@section('titulo', 'Informe del acta · Eleven')

@section('contenido')
    <a href="{{ route('actas.index') }}" class="text-sm text-[#1F4E8C] underline">← Volver al historial</a>
    <h1 class="mt-2 text-2xl font-semibold">Informe de la carga</h1>
    <p class="mt-1 text-sm text-slate-600">
        Procesada el {{ $acta->procesada_en?->format('d/m/Y H:i') }} por {{ $acta->user?->nombreCompleto() }}.
    </p>

    <section class="mt-4 max-w-4xl rounded-xl border border-green-300 bg-green-50 p-5">
        <h2 class="font-semibold text-green-900">Descargas</h2>
        <p class="mb-3 text-sm text-green-900">
            Descargá la planilla y usala como tu planilla de seguimiento vigente: es la que tiene las notas cargadas.
        </p>
        <div class="flex flex-wrap gap-3">
            @foreach ($acta->planillas as $planilla)
                <a href="{{ route('actas.planilla', [$acta, $planilla]) }}"
                   class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                    Descargar planilla actualizada
                </a>
            @endforeach
            <a href="{{ route('actas.pdf', $acta) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Descargar el acta en PDF</a>
        </div>
    </section>

    @include('actas._resumen', [
        'materia' => $informe['materia'] ?? null, 'tomo' => $informe['tomo'] ?? null, 'folio' => $informe['folio'] ?? null,
        'leidos' => $informe['alumnos_leidos'] ?? 0, 'control' => $informe['control'] ?? null,
    ])

    @foreach ($informe['advertencias'] ?? [] as $aviso)
        <div class="mt-4 max-w-4xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $aviso }}</div>
    @endforeach

    <section class="mt-4 grid max-w-4xl gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Notas cargadas', $informe['notas_cargadas'] ?? 0],
            ['Sin cambios', $informe['sin_cambios'] ?? 0],
            ['Conflictos', count($informe['conflictos'] ?? [])],
            ['No encontrados', count($informe['no_encontrados'] ?? [])],
            ['Sin columna', count($informe['sin_columna'] ?? [])],
            ['Líneas sin leer', count($informe['lineas_no_leidas'] ?? [])],
        ] as [$rotulo, $cantidad])
            <div class="rounded-xl border border-slate-200 bg-white p-3 text-center">
                <div class="text-2xl font-semibold">{{ $cantidad }}</div>
                <div class="text-xs text-slate-600">{{ $rotulo }}</div>
            </div>
        @endforeach
    </section>

    @if (! empty($informe['conflictos']))
        <section class="mt-4 max-w-4xl rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">Conflictos y cómo se resolvieron</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">Celda</th><th class="py-1 pr-4">Tenía</th><th class="py-1 pr-4">El acta decía</th><th class="py-1">Resolución</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($informe['conflictos'] as $c)
                            <tr>
                                <td class="py-1 pr-4">{{ $c['legajo'] }}</td>
                                <td class="py-1 pr-4">{{ $c['hoja'] }} · {{ $c['celda'] }}</td>
                                <td class="py-1 pr-4">{{ $c['actual'] }}</td>
                                <td class="py-1 pr-4">{{ $c['nota'] }}</td>
                                <td class="py-1">{{ $c['resolucion'] === 'sobrescribir' ? 'Sobrescrita' : 'Se conservó la existente' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @include('actas._listas', [
        'noEncontrados' => $informe['no_encontrados'] ?? [], 'sinColumna' => $informe['sin_columna'] ?? [],
        'lineasNoLeidas' => $informe['lineas_no_leidas'] ?? [], 'repetidosPlanilla' => $informe['legajos_repetidos_en_planilla'] ?? [],
        'repetidosActa' => $informe['legajos_repetidos_en_acta'] ?? [], 'difNombres' => $informe['diferencias_nombre'] ?? [],
    ])
@endsection
