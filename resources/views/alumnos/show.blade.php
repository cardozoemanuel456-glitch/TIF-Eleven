@extends('layouts.app')

@section('titulo', 'Alumno · Eleven')

@section('contenido')
    @php
        $color = match ($alumno->estado) {
            \App\Enums\EstadoUsuario::Activo => 'bg-green-100 text-green-800',
            \App\Enums\EstadoUsuario::Pendiente => 'bg-amber-100 text-amber-800',
            default => 'bg-slate-200 text-slate-700',
        };
        $etiqueta = 'text-slate-500';
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ $alumno->nombreCompleto() }}</h1>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $alumno->estado->etiqueta() }}</span>
        </div>
        <a href="{{ route('alumnos.index') }}" class="text-sm text-[#1F4E8C] underline">Volver al listado</a>
    </div>

    <div class="grid max-w-4xl gap-4 md:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 font-semibold">Datos</h2>
            <dl class="grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
                <dt class="{{ $etiqueta }}">DNI</dt><dd>{{ $alumno->dni ?? '—' }}</dd>
                <dt class="{{ $etiqueta }}">Correo</dt>
                <dd>
                    {{ $alumno->email ?? '—' }}
                    @if ($alumno->email && ! $alumno->email_verified_at)
                        <span class="text-xs text-amber-700">(sin verificar)</span>
                    @endif
                </dd>
                <dt class="{{ $etiqueta }}">Aula</dt><dd>{{ $alumno->anio_cursada?->etiqueta() ?? '—' }}</dd>
                <dt class="{{ $etiqueta }}">Aprobado</dt>
                <dd>
                    @if ($alumno->aprobado_en)
                        {{ $alumno->aprobado_en->format('d/m/Y') }}
                        @if ($alumno->aprobadoPor) por {{ $alumno->aprobadoPor->nombreCompleto() }} @endif
                    @else
                        —
                    @endif
                </dd>
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="mb-3 font-semibold">Acciones</h2>
            <div class="space-y-3 text-sm">
                @can('aprobarAlumno', $alumno)
                    <form method="POST" action="{{ route('alumnos.aprobar', ['alumno' => $alumno]) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2 font-medium text-white hover:bg-[#12263F]">Aprobar registro</button>
                    </form>
                @endcan

                @can('rechazarAlumno', $alumno)
                    <form method="POST" action="{{ route('alumnos.rechazar', ['alumno' => $alumno]) }}"
                          onsubmit="return confirm('Rechazar elimina la cuenta pendiente. ¿Continuar?')">
                        @csrf
                        <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50">Rechazar registro</button>
                    </form>
                @endcan

                @if ($alumno->estado === \App\Enums\EstadoUsuario::Pendiente && ! $alumno->email_verified_at)
                    <p class="text-xs text-amber-800">Todavía no verificó su correo: no se puede aprobar.</p>
                @endif

                @can('desactivarAlumno', $alumno)
                    <form method="POST" action="{{ route('alumnos.desactivar', ['alumno' => $alumno]) }}"
                          onsubmit="return confirm('Al desactivar, el alumno pierde el acceso y se cierran sus sesiones. ¿Continuar?')">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 hover:bg-slate-50">Desactivar (baja o egreso)</button>
                    </form>
                @endcan

                @can('reactivarAlumno', $alumno)
                    <form method="POST" action="{{ route('alumnos.reactivar', ['alumno' => $alumno]) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-[#1F4E8C] px-4 py-2 font-medium text-white hover:bg-[#12263F]">Reactivar cuenta</button>
                    </form>
                @endcan

                @can('cambiarAnio', $alumno)
                    <form method="POST" action="{{ route('alumnos.aula', ['alumno' => $alumno]) }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        @method('PUT')
                        <select name="anio_cursada" class="rounded-md border border-slate-300 bg-white px-3 py-2">
                            @foreach ($anios as $a)
                                <option value="{{ $a->value }}" @selected($alumno->anio_cursada === $a)>{{ $a->etiqueta() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 hover:bg-slate-50">Cambiar aula</button>
                    </form>
                    @error('anio_cursada') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                @endcan
            </div>
        </section>

        @can('eliminarAlumno', $alumno)
            <section class="rounded-xl border border-red-200 bg-red-50 p-5 md:col-span-2">
                <h2 class="mb-1 font-semibold text-red-900">Eliminar perfil (irreversible)</h2>
                <p class="mb-3 text-sm text-red-900">
                    Se borran el nombre, el apellido, el DNI y el correo. La cuenta queda como «Alumno eliminado» y no se puede reactivar.
                </p>
                <form method="POST" action="{{ route('alumnos.destroy', ['alumno' => $alumno]) }}" class="flex flex-wrap items-center gap-2"
                      onsubmit="return confirm('Esta acción no se puede deshacer. ¿Eliminar el perfil?')">
                    @csrf
                    @method('DELETE')
                    <input type="text" name="confirmacion" placeholder="Escribí ELIMINAR" autocomplete="off"
                           class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm">
                    <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Eliminar perfil</button>
                </form>
                @error('confirmacion') <p class="mt-2 text-sm text-red-800">{{ $message }}</p> @enderror
            </section>
        @endcan
    </div>
@endsection
