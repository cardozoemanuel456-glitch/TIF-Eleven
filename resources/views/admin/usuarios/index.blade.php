@extends('layouts.app')

@section('titulo', 'Usuarios · Eleven')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Usuarios del personal</h1>
        <a href="{{ route('admin.usuarios.create') }}"
           class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">Nuevo usuario</a>
    </div>

    @if (session('password_temporal'))
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p>Contraseña temporal de <strong>{{ session('password_temporal')['nombre'] }}</strong>:</p>
            <p class="mt-1 select-all font-mono text-lg">{{ session('password_temporal')['password'] }}</p>
            <p class="mt-1">Se muestra una sola vez. Entregásela de forma segura: deberá cambiarla al ingresar.</p>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, apellido o correo"
               class="w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm">
        <button type="submit" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Buscar</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($usuarios as $u)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $u->nombreCompleto() }}</td>
                        <td class="px-4 py-3">{{ $u->email }}</td>
                        <td class="px-4 py-3">{{ $u->role?->nombre?->etiqueta() }}</td>
                        <td class="px-4 py-3">
                            @php
                                $color = match ($u->estado) {
                                    \App\Enums\EstadoUsuario::Activo => 'bg-green-100 text-green-800',
                                    \App\Enums\EstadoUsuario::Pendiente => 'bg-amber-100 text-amber-800',
                                    default => 'bg-slate-200 text-slate-700',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $u->estado->etiqueta() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                @can('update', $u)
                                    <a href="{{ route('admin.usuarios.edit', $u) }}" class="text-[#1F4E8C] underline">Editar</a>
                                @endcan

                                @can('desactivar', $u)
                                    @if ($u->estaActivo())
                                        <form method="POST" action="{{ route('admin.usuarios.desactivar', $u) }}"
                                              onsubmit="return confirm('¿Desactivar esta cuenta? Se cerrarán sus sesiones.')">
                                            @csrf
                                            <button type="submit" class="text-red-700 underline">Desactivar</button>
                                        </form>
                                    @endif
                                @endcan

                                @can('reactivar', $u)
                                    @if (! $u->estaActivo())
                                        <form method="POST" action="{{ route('admin.usuarios.reactivar', $u) }}">
                                            @csrf
                                            <button type="submit" class="text-[#1F4E8C] underline">Reactivar</button>
                                        </form>
                                    @endif
                                @endcan

                                @can('restablecerPassword', $u)
                                    <form method="POST" action="{{ route('admin.usuarios.password-temporal', $u) }}"
                                          onsubmit="return confirm('Se reemplazará su contraseña por una temporal. ¿Continuar?')">
                                        @csrf
                                        <button type="submit" class="text-[#1F4E8C] underline">Contraseña temporal</button>
                                    </form>
                                    @if ($u->email && $u->estaActivo())
                                        <form method="POST" action="{{ route('admin.usuarios.enlace', $u) }}">
                                            @csrf
                                            <button type="submit" class="text-[#1F4E8C] underline">Enviar enlace</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-600">No se encontraron usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $usuarios->links() }}</div>
@endsection