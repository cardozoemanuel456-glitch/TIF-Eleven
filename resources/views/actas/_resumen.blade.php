{{-- Datos del acta y control de lectura. Recibe: $materia, $tomo, $folio, $leidos, $control --}}
<section class="mt-4 max-w-4xl rounded-xl border border-slate-200 bg-white p-5">
    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        <div><dt class="text-xs uppercase text-slate-500">Materia</dt><dd class="font-medium">{{ $materia ?? 'Sin identificar' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-500">Tomo y folio</dt><dd class="font-medium">{{ $tomo ?? '—' }} · {{ $folio ?? '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-500">Alumnos leídos</dt><dd class="font-medium">{{ $leidos }}</dd></div>
    </dl>

    @if (! empty($control))
        @if ($control['coincide'] === true)
            <p class="mt-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-900">{{ $control['mensaje'] }}</p>
        @else
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $control['mensaje'] }}</p>
        @endif
    @endif
</section>
