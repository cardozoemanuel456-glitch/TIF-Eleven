@extends('layouts.app')

@section('titulo', 'Notificaciones · Eleven')

@section('contenido')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Tus notificaciones</h1>
            <p class="mt-1 text-sm text-slate-600">
                Novedades de avisos, reprogramaciones y recordatorios de evaluaciones.
            </p>
        </div>

        @if ($noLeidasCount > 0)
            <form method="POST" action="{{ route('notificaciones.leer') }}">
                @csrf
                <button type="submit"
                        class="rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                    Marcar todas como leídas ({{ $noLeidasCount }})
                </button>
            </form>
        @endif
    </div>

    <div class="grid max-w-5xl gap-6 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @forelse ($notificaciones as $notif)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm {{ $notif['leida'] ? '' : 'border-l-4 border-l-[#1F4E8C] bg-blue-50/30' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if (! $notif['leida'])
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-[#1F4E8C]">Nueva</span>
                                @endif
                                @if ($notif['destinatario'])
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $notif['destinatario'] }}</span>
                                @endif
                                <span class="text-xs text-slate-400">
                                    {{ $notif['creada_en']?->format('d/m/Y H:i') }} hs
                                </span>
                            </div>

                            @if (! $notif['disponible'])
                                <p class="mt-2 text-sm font-medium italic text-slate-500">Aviso no disponible</p>
                            @else
                                <h2 class="mt-2 text-base font-semibold text-slate-900">
                                    @if ($notif['url'])
                                        <a href="{{ $notif['url'] }}" class="hover:text-[#1F4E8C] hover:underline">{{ $notif['titulo'] }}</a>
                                    @else
                                        {{ $notif['titulo'] }}
                                    @endif
                                </h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $notif['mensaje'] }}</p>
                            @endif
                        </div>

                        @if (! $notif['leida'])
                            <form method="POST" action="{{ route('notificaciones.leer.una', $notif['id']) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Marcar leída
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 shadow-sm">
                    No tenés notificaciones por el momento.
                </div>
            @endforelse

            <div class="pt-2">{{ $paginador->links() }}</div>
        </div>

        {{-- Preferencias de correo (M2-11) --}}
        <aside>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Notificaciones por correo</h2>
                <p class="mt-1 text-xs leading-relaxed text-slate-600">
                    La campana dentro de Eleven permanece activa siempre. Desde acá podés elegir si también querés recibir una copia por correo electrónico.
                </p>

                <form method="POST" action="{{ route('cuenta.notificaciones') }}" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="notificar_email" value="0">
                    <label class="flex items-start gap-2.5 text-sm text-slate-800">
                        <input type="checkbox" name="notificar_email" value="1"
                               class="mt-0.5 rounded border-slate-300"
                               @checked($usuario->notificar_email)>
                        <span>Recibir avisos y recordatorios en mi correo electrónico</span>
                    </label>

                    <button type="submit"
                            class="w-full rounded-md bg-[#1F4E8C] px-4 py-2 text-sm font-medium text-white hover:bg-[#12263F]">
                        Guardar preferencia
                    </button>
                </form>
            </section>
        </aside>
    </div>
@endsection

