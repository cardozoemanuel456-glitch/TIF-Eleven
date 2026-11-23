{{--
    Lo que quedó sin cargar o merece revisión. Cada bloque aparece solo si tiene contenido.
    Recibe: $noEncontrados, $sinColumna, $lineasNoLeidas, $repetidosPlanilla, $repetidosActa, $difNombres
--}}
@php $caja = 'mt-4 max-w-4xl rounded-xl border border-slate-200 bg-white p-5'; @endphp

@if (! empty($noEncontrados))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Alumnos no encontrados en la planilla ({{ count($noEncontrados) }})</h2>
        <p class="mb-2 text-sm text-slate-600">Su legajo no figura en ninguna pestaña. No se cargó su nota.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">Nombre</th><th class="py-1">Nota</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($noEncontrados as $n)
                        <tr><td class="py-1 pr-4">{{ $n['legajo'] }}</td><td class="py-1 pr-4">{{ $n['nombre'] }}</td><td class="py-1">{{ $n['nota'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if (! empty($sinColumna))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Alumnos en una pestaña sin columna para la materia ({{ count($sinColumna) }})</h2>
        <p class="mb-2 text-sm text-slate-600">Figuran en la planilla, pero su pestaña no tiene esta materia. No se cargó su nota.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">Nombre</th><th class="py-1 pr-4">Pestaña</th><th class="py-1">Nota</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sinColumna as $n)
                        <tr><td class="py-1 pr-4">{{ $n['legajo'] }}</td><td class="py-1 pr-4">{{ $n['nombre'] }}</td><td class="py-1 pr-4">{{ $n['hoja'] }}</td><td class="py-1">{{ $n['nota'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if (! empty($lineasNoLeidas))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Líneas del acta que no se pudieron leer ({{ count($lineasNoLeidas) }})</h2>
        <p class="mb-2 text-sm text-slate-600">Empiezan con número de orden y legajo pero no tienen el formato esperado (por ejemplo, ausentes o libres). Cargalas a mano si corresponde.</p>
        <ul class="space-y-1 font-mono text-xs text-slate-700">
            @foreach ($lineasNoLeidas as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </section>
@endif

@if (! empty($repetidosPlanilla))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Legajos en más de una pestaña ({{ count($repetidosPlanilla) }})</h2>
        <p class="mb-2 text-sm text-slate-600">La nota se cargó en la pestaña más reciente donde figuran con esta materia.</p>
        <ul class="space-y-1 text-sm">
            @foreach ($repetidosPlanilla as $r)
                <li>{{ $r['legajo'] }}: pestañas {{ implode(', ', $r['hojas']) }}</li>
            @endforeach
        </ul>
    </section>
@endif

@if (! empty($repetidosActa))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Legajos repetidos dentro del acta ({{ count($repetidosActa) }})</h2>
        <p class="mb-2 text-sm text-slate-600">Se usó la primera aparición de cada uno.</p>
        <p class="text-sm">{{ implode(', ', $repetidosActa) }}</p>
    </section>
@endif

@if (! empty($difNombres))
    <section class="{{ $caja }}">
        <h2 class="font-semibold">Nombres que difieren entre el acta y la planilla ({{ count($difNombres) }})</h2>
        <p class="mb-2 text-sm text-slate-600">Es solo un aviso: las notas se cruzan por legajo. Conviene revisar si alguno es un error de carga.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500"><tr><th class="py-1 pr-4">Legajo</th><th class="py-1 pr-4">En el acta</th><th class="py-1">En la planilla</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($difNombres as $d)
                        <tr><td class="py-1 pr-4">{{ $d['legajo'] }}</td><td class="py-1 pr-4">{{ $d['nombre_acta'] }}</td><td class="py-1">{{ $d['nombre_planilla'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
