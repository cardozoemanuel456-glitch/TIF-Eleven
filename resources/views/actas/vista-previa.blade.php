@extends('layouts.app')

@section('titulo', 'Vista previa del acta · Eleven')

@section('contenido')
    @php
        $resumen = $plan['resumen'];
        $hayConflictos = count($plan['conflictos']) > 0;
        $hayAlgoParaCargar = $resumen['a_cargar'] > 0 || $hayConflictos;
    @endphp

    <a href="{{ route('actas.index') }}" class="text-sm text-[#1F4E8C] underline">← Volver al historial</a>
    <h1 class="mt-2 text-2xl font-semibold">Vista previa de la carga</h1>
    <p class="mt-1 max-w-2xl text-sm text-slate-600">
        <strong>Todavía no se escribió nada en la planilla.</strong> Revisá lo que sigue y confirmá al final.
    </p>

    @if ($duplicada)
        <div class="mt-4 max-w-4xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Este mismo PDF ya fue procesado el {{ $duplicada->procesada_en?->format('d/m/Y H:i') }}
            por {{ $duplicada->user?->nombreCompleto() }}. Si lo cargás de nuevo, las notas que ya están en tu planilla
            figurarán como «sin cambios».
        </div>
    @endif

    @foreach (array_merge($datos['advertencias'], $plan['advertencias']) as $aviso)
        <div class="mt-4 max-w-4xl rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $aviso }}</div>
    @endforeach

    @include('actas._resumen', [
        'materia' => $datos['materia'], 'tomo' => $datos['tomo'], 'folio' => $datos['folio'],
        'leidos' => $datos['total_alumnos'], 'control' => $datos['control'],
    ])

    <section class="mt-4 grid max-w-4xl gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['A cargar', $resumen['a_cargar']],
            ['Sin cambios', $resumen['sin_cambios']],
            ['Conflictos', $resumen['conflictos']],
            ['No encontrados', $resumen['no_encontrados']],
            ['Sin columna', $resumen['sin_columna']],
            ['Líneas sin leer', count($datos['lineas_no_leidas'])],
        ] as [$rotulo, $cantidad])
            <div class="rounded-xl border border-slate-200 bg-white p-3 text-center">
                <div class="text-2xl font-semibold">{{ $cantidad }}</div>
                <div class="text-xs text-slate-600">{{ $rotulo }}</div>
            </div>
        @endforeach
    </section>

    @if ($resumen['a_cargar'] > 0)
        <details class="mt-4 max-w-4xl rounded-xl border border-slate-200 bg-white p-5">
            <summary class="cursor-pointer font-semibold">Notas que se cargarán ({{ $resumen['a_cargar'] }})</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">Nombre</th><th class="py-1 pr-4">Pestaña</th><th class="py-1 pr-4">Celda</th><th class="py-1">Nota</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plan['cargas'] as $c)
                            <tr>
                                <td class="py-1 pr-4">{{ $c['legajo'] }}</td>
                                <td class="py-1 pr-4">{{ $c['nombre_acta'] }}</td>
                                <td class="py-1 pr-4">{{ $c['hoja'] }}</td>
                                <td class="py-1 pr-4">{{ $c['celda'] }}</td>
                                <td class="py-1">{{ $c['nota'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    <form method="POST" action="{{ route('actas.confirmar', $acta) }}">
        @csrf

        @if ($hayConflictos)
            <section id="conflictos" class="mt-4 max-w-4xl rounded-xl border border-amber-300 bg-white p-5">
                <h2 class="font-semibold">Conflictos: celdas que ya tienen otra nota ({{ count($plan['conflictos']) }})</h2>
                <p class="mb-3 text-sm text-slate-600">
                    Por defecto se conserva lo que ya está en la planilla. Elegí «sobrescribir» solo en los que quieras reemplazar.
                </p>
                <div class="mb-3 flex flex-wrap gap-2">
                    <button type="button" onclick="marcarConflictos('conservar')" class="rounded-md border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">Conservar todos</button>
                    <button type="button" onclick="marcarConflictos('sobrescribir')" class="rounded-md border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">Sobrescribir todos</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">Nombre</th><th class="py-1 pr-4">Celda</th><th class="py-1 pr-4">Hoy tiene</th><th class="py-1 pr-4">El acta dice</th><th class="py-1">Qué hacer</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($plan['conflictos'] as $c)
                                <tr>
                                    <td class="py-2 pr-4">{{ $c['legajo'] }}</td>
                                    <td class="py-2 pr-4">{{ $c['nombre_acta'] }}</td>
                                    <td class="py-2 pr-4">{{ $c['hoja'] }} · {{ $c['celda'] }}</td>
                                    <td class="py-2 pr-4">{{ $c['actual'] }}</td>
                                    <td class="py-2 pr-4">{{ $c['nota'] }}</td>
                                    <td class="py-2">
                                        <label class="mr-3"><input type="radio" name="decision[{{ $c['legajo'] }}]" value="conservar" checked> Conservar</label>
                                        <label><input type="radio" name="decision[{{ $c['legajo'] }}]" value="sobrescribir"> Sobrescribir</label>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($hayAlgoParaCargar)
            <div class="mt-4 flex max-w-4xl flex-wrap items-center gap-3">
                <button type="submit" class="rounded-md bg-[#1F4E8C] px-5 py-2.5 font-medium text-white hover:bg-[#12263F]">Confirmar y cargar las notas</button>
                <a href="{{ route('actas.index') }}" class="text-sm text-slate-700 underline">Cancelar</a>
            </div>
        @else
            <p class="mt-4 max-w-4xl rounded-md border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                No hay notas nuevas para cargar: todo lo que trae el acta ya está en la planilla o quedó sin ubicar (abajo).
            </p>
        @endif
    </form>

    @include('actas._listas', [
        'noEncontrados' => $plan['no_encontrados'], 'sinColumna' => $plan['sin_columna'],
        'lineasNoLeidas' => $datos['lineas_no_leidas'], 'repetidosPlanilla' => $plan['legajos_repetidos'],
        'repetidosActa' => $datos['legajos_repetidos'], 'difNombres' => $plan['diferencias_nombre'],
    ])

    <script>
        function marcarConflictos(valor) {
            document.querySelectorAll('#conflictos input[type=radio][value="' + valor + '"]').forEach(function (r) { r.checked = true; });
        }
    </script>
@endsection
