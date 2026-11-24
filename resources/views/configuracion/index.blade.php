@extends('layouts.app')

@section('titulo', 'Configuración · Eleven')

@section('contenido')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Configuración</h1>
        <p class="mt-1 text-sm text-slate-600">
            Opciones de tu cuenta, administración y personalización visual de los eventos del calendario.
        </p>
    </div>

    <div class="max-w-4xl space-y-6">
        {{-- 1. Administración de usuarios (solo Administrador) --}}
        @can('viewAny', \App\Models\User::class)
            @if (Route::has('admin.usuarios.index'))
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Administración de usuarios</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Creá cuentas del personal (profesores, preceptores y administradores), asigná roles y restablecé contraseñas.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.usuarios.index') }}"
                               class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                                Administrar usuarios
                            </a>
                        </div>
                    </div>
                </section>
            @endif
        @endcan

        {{-- 2. Personalización de colores de eventos del calendario --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Personalización de colores de eventos</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Elegí colores personalizados para distinguir cada tipo de evento en el calendario si preferís no usar la leyenda establecida por defecto.
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $usaColoresPersonalizados ? 'bg-blue-100 text-[#1F4E8C]' : 'bg-slate-100 text-slate-700' }}">
                    {{ $usaColoresPersonalizados ? 'Usando colores personalizados' : 'Usando leyenda establecida' }}
                </span>
            </div>

            <form method="POST" action="{{ route('configuracion.colores.actualizar') }}" class="mt-5 space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                    @foreach ($tiposEvento as $tipo)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5">
                            <div>
                                <label for="color-{{ $tipo->value }}" class="block text-sm font-medium text-slate-900">
                                    {{ $tipo->etiqueta() }}
                                </label>
                                <span class="text-[11px] text-slate-500">Por defecto: {{ $tipo->colorPorDefecto() }}</span>
                            </div>
                            <input id="color-{{ $tipo->value }}"
                                   type="color"
                                   name="colores[{{ $tipo->value }}]"
                                   value="{{ old('colores.'.$tipo->value, $tipo->color()) }}"
                                   class="h-9 w-12 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                        </div>
                    @endforeach
                </div>

                @error('colores.*')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <button type="submit"
                            class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                        Guardar colores
                    </button>
                </div>
            </form>

            @if ($usaColoresPersonalizados)
                <form method="POST" action="{{ route('configuracion.colores.restablecer') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="text-sm font-medium text-slate-700 underline hover:text-slate-900">
                        Restablecer colores de la leyenda establecida
                    </button>
                </form>
            @endif
        </section>

        {{-- 3. Preferencias de correo, cambio de contraseña y cierre de sesión --}}
        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Notificaciones por correo</h2>
                <p class="mt-1 text-sm text-slate-600">
                    La campana interna permanece activa siempre. Acá podés elegir si querés recibir también los avisos por email.
                </p>

                <form method="POST" action="{{ route('cuenta.notificaciones') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="notificar_email" value="0">
                    <label class="flex items-center gap-2.5 text-sm text-slate-800">
                        <input type="checkbox" name="notificar_email" value="1"
                               class="rounded border-slate-300"
                               @checked($usuario->notificar_email)>
                        <span>Recibir los avisos también por correo</span>
                    </label>
                    <button type="submit"
                            class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                        Guardar preferencia
                    </button>
                </form>
            </section>

            <section class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Seguridad y sesión</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Actualizá tu contraseña de acceso o cerrá tu sesión activa en este dispositivo.
                    </p>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a href="{{ route('password.cambiar') }}"
                       class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">
                        Cambiar contraseña
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
