<?php

namespace App\Services;

use App\Enums\AnioCursada;
use App\Enums\EstadoNotificacionProgramada;
use App\Enums\EstadoUsuario;
use App\Enums\TipoEvento;
use App\Models\Aviso;
use App\Models\AvisoLectura;
use App\Models\Evento;
use App\Models\NotificacionProgramada;
use App\Models\User;
use App\Notifications\AvisoPublicadoNotification;
use App\Notifications\EventoReprogramadoNotification;
use App\Notifications\NotificacionInstitucionalNotification;
use App\Notifications\RecordatorioEventoNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Lógica de negocio del módulo 2 (Calendario, Avisos y Notificaciones).
 * Los controladores validan y autorizan; la coordinación y los envíos viven acá.
 * Ningún log registra datos personales.
 */
class CalendarioAvisosService
{
    /**
     * M2-12: solo alumnos activos (todos si es general, o los del año indicado).
     *
     * @return EloquentCollection<int, User>
     */
    public function destinatariosAlumnos(?AnioCursada $anio): EloquentCollection
    {
        return User::alumnos()
            ->where('estado', EstadoUsuario::Activo->value)
            ->when($anio !== null, fn ($q) => $q->where('anio_cursada', $anio->value))
            ->get();
    }

    // ----------------------------------------------------------------- Eventos

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearEvento(array $datos, User $actor): Evento
    {
        $evento = new Evento;
        $evento->fill([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo' => $datos['tipo'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'] ?? $datos['fecha_inicio'],
            'hora' => $datos['hora'] ?? null,
            'anio_cursada' => $datos['anio_cursada'] ?? null,
        ]);
        $evento->forceFill(['creado_por' => $actor->id])->save();

        Log::info('evento.creado', ['actor' => $actor->id, 'evento' => $evento->id]);

        return $evento;
    }

    /**
     * M2-05: si cambia la fecha de inicio de un parcial o mesa de examen,
     * reinicia recordado_en y notifica la reprogramación a los alumnos activos.
     *
     * @param  array<string, mixed>  $datos
     * @return bool true si se disparó notificación de reprogramación
     */
    public function actualizarEvento(Evento $evento, array $datos, User $actor): bool
    {
        $fechaInicioIsoAnterior = $evento->fecha_inicio->format('Y-m-d');
        $fechaTextoAnterior = $evento->fecha_inicio->format('d/m/Y');

        $evento->fill([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo' => $datos['tipo'],
            'fecha_inicio' => $datos['fecha_inicio'],
            'fecha_fin' => $datos['fecha_fin'] ?? $datos['fecha_inicio'],
            'hora' => $datos['hora'] ?? null,
            'anio_cursada' => $datos['anio_cursada'] ?? null,
        ]);

        $cambioFechaInicio = $fechaInicioIsoAnterior !== Carbon::parse($datos['fecha_inicio'])->format('Y-m-d');
        $reprogramado = $evento->tipo->llevaRecordatorio() && $cambioFechaInicio;

        if ($reprogramado) {
            $evento->forceFill(['recordado_en' => null]);
        }

        $evento->save();

        Log::info('evento.actualizado', [
            'actor' => $actor->id,
            'evento' => $evento->id,
            'reprogramado' => $reprogramado,
        ]);

        if ($reprogramado) {
            $destinatarios = $this->destinatariosAlumnos($evento->anio_cursada);

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new EventoReprogramadoNotification(
                    eventoId: $evento->id,
                    titulo: $evento->titulo,
                    tipoEtiqueta: $evento->tipo->etiqueta(),
                    fechaAnterior: $fechaTextoAnterior,
                    nuevaFecha: $evento->fecha_inicio->format('d/m/Y'),
                    destinatario: $evento->etiquetaDestinatario(),
                ));
            }
        }

        return $reprogramado;
    }

    /**
     * M2-05: eliminar un evento no envía notificaciones.
     */
    public function eliminarEvento(Evento $evento, User $actor): void
    {
        $eventoId = $evento->id;
        $evento->delete();

        Log::info('evento.eliminado', ['actor' => $actor->id, 'evento' => $eventoId]);
    }

    // ------------------------------------------------------------------ Avisos

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearAviso(array $datos, User $actor): Aviso
    {
        $modo = $datos['modo_publicacion'] ?? 'publicar';

        $aviso = new Aviso;
        $aviso->fill([
            'titulo' => $datos['titulo'],
            'contenido' => $datos['contenido'],
            'anio_cursada' => $datos['anio_cursada'] ?? null,
            'evento_id' => $datos['evento_id'] ?? null,
        ]);

        $aviso->forceFill([
            'autor_id' => $actor->id,
            'publicado_en' => $modo === 'publicar' ? now() : null,
            'programado_para' => $modo === 'programar' && filled($datos['programado_para'] ?? null)
                ? Carbon::parse($datos['programado_para'])
                : null,
            'editado_en' => null,
        ])->save();

        Log::info('aviso.creado', [
            'actor' => $actor->id,
            'aviso' => $aviso->id,
            'modo' => $modo,
        ]);

        if ($aviso->estaPublicado()) {
            $this->notificarAvisoPublicado($aviso);
        }

        return $aviso;
    }

    /**
     * M2-07: editar un aviso ya publicado registra editado_en y NO vuelve a notificar.
     * Si era borrador o programado y pasa a publicado, notifica una única vez.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarAviso(Aviso $aviso, array $datos, User $actor): Aviso
    {
        $yaEstabaPublicado = $aviso->estaPublicado();

        $aviso->fill([
            'titulo' => $datos['titulo'],
            'contenido' => $datos['contenido'],
            'anio_cursada' => $datos['anio_cursada'] ?? null,
            'evento_id' => $datos['evento_id'] ?? null,
        ]);

        if ($yaEstabaPublicado) {
            $aviso->forceFill([
                'programado_para' => null,
                'editado_en' => now(),
            ])->save();

            Log::info('aviso.actualizado', ['actor' => $actor->id, 'aviso' => $aviso->id]);

            return $aviso;
        }

        $modo = $datos['modo_publicacion'] ?? 'borrador';

        $aviso->forceFill([
            'publicado_en' => $modo === 'publicar' ? now() : null,
            'programado_para' => $modo === 'programar' && filled($datos['programado_para'] ?? null)
                ? Carbon::parse($datos['programado_para'])
                : null,
        ])->save();

        Log::info('aviso.actualizado', [
            'actor' => $actor->id,
            'aviso' => $aviso->id,
            'modo' => $modo,
        ]);

        if ($aviso->estaPublicado()) {
            $this->notificarAvisoPublicado($aviso);
        }

        return $aviso;
    }

    public function publicarAviso(Aviso $aviso, ?User $actor = null): Aviso
    {
        if ($aviso->estaPublicado()) {
            return $aviso;
        }

        $aviso->forceFill([
            'publicado_en' => now(),
            'programado_para' => null,
        ])->save();

        Log::info('aviso.publicado', [
            'actor' => $actor?->id,
            'aviso' => $aviso->id,
        ]);

        $this->notificarAvisoPublicado($aviso);

        return $aviso;
    }

    public function eliminarAviso(Aviso $aviso, User $actor): void
    {
        $avisoId = $aviso->id;
        $aviso->delete();

        Log::info('aviso.eliminado', ['actor' => $actor->id, 'aviso' => $avisoId]);
    }

    public function marcarAvisoComoLeido(Aviso $aviso, User $usuario): void
    {
        AvisoLectura::firstOrCreate(
            ['aviso_id' => $aviso->id, 'user_id' => $usuario->id],
            ['leido_en' => now()],
        );

        $noLeidas = $usuario->unreadNotifications()
            ->get()
            ->filter(fn (DatabaseNotification $n): bool => ($n->data['aviso_id'] ?? null) === $aviso->id);

        if ($noLeidas->isNotEmpty()) {
            $noLeidas->markAsRead();
        }
    }

    /**
     * @param  Collection<int, DatabaseNotification>  $notificaciones
     */
    public function sincronizarLecturasDesdeNotificaciones(Collection $notificaciones, User $usuario): void
    {
        $avisoIds = $notificaciones
            ->map(fn (DatabaseNotification $n) => $n->data['aviso_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($avisoIds->isEmpty()) {
            return;
        }

        $existentes = Aviso::whereIn('id', $avisoIds)->pluck('id');

        foreach ($existentes as $avisoId) {
            AvisoLectura::firstOrCreate(
                ['aviso_id' => $avisoId, 'user_id' => $usuario->id],
                ['leido_en' => now()],
            );
        }
    }

    // --------------------------------------------------- Notificaciones y M2-14

    /**
     * M2-14: si un aviso fue eliminado, las notificaciones que lo mencionan
     * muestran «Aviso no disponible» y omiten el enlace roto.
     *
     * @param  Collection<int, DatabaseNotification>  $notificaciones
     * @return Collection<int, array<string, mixed>>
     */
    public function presentarNotificaciones(Collection $notificaciones): Collection
    {
        $avisoIds = $notificaciones
            ->map(fn (DatabaseNotification $n) => $n->data['aviso_id'] ?? null)
            ->filter()
            ->unique()
            ->all();

        $avisosVigentes = $avisoIds === []
            ? collect()
            : Aviso::whereIn('id', $avisoIds)->pluck('id')->flip();

        return $notificaciones->map(function (DatabaseNotification $notificacion) use ($avisosVigentes): array {
            $datos = $notificacion->data;
            $avisoId = $datos['aviso_id'] ?? null;
            $avisoEliminado = $avisoId !== null && ! $avisosVigentes->has($avisoId);

            return [
                'id' => $notificacion->id,
                'leida' => $notificacion->read_at !== null,
                'creada_en' => $notificacion->created_at,
                'disponible' => ! $avisoEliminado,
                'titulo' => $avisoEliminado ? 'Aviso no disponible' : ($datos['titulo'] ?? 'Notificación'),
                'mensaje' => $avisoEliminado
                    ? 'Aviso no disponible.'
                    : ($datos['mensaje'] ?? ''),
                'destinatario' => $datos['destinatario'] ?? null,
                'url' => $avisoEliminado ? null : ($datos['url'] ?? null),
            ];
        });
    }

    // ------------------------------------------ Recordatorios y programaciones

    /**
     * M2-13: busca parciales y mesas de examen con inicio hoy o mañana y sin recordado_en.
     */
    public function enviarRecordatoriosPendientes(): int
    {
        $hoy = today()->toDateString();
        $maniana = today()->addDay()->toDateString();

        $eventos = Evento::query()
            ->whereIn('tipo', [TipoEvento::Parcial->value, TipoEvento::MesaExamen->value])
            ->whereNull('recordado_en')
            ->whereDate('fecha_inicio', '>=', $hoy)
            ->whereDate('fecha_inicio', '<=', $maniana)
            ->get();

        foreach ($eventos as $evento) {
            $evento->forceFill(['recordado_en' => now()])->save();

            $destinatarios = $this->destinatariosAlumnos($evento->anio_cursada);

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new RecordatorioEventoNotification(
                    eventoId: $evento->id,
                    titulo: $evento->titulo,
                    tipoEtiqueta: $evento->tipo->etiqueta(),
                    fechaTexto: $evento->rangoFechasTexto(),
                    destinatario: $evento->etiquetaDestinatario(),
                ));
            }
        }

        return $eventos->count();
    }

    /**
     * HU 16: registra una notificación programada para enviarse en el momento indicado.
     *
     * @param  array<string, mixed>  $datos
     */
    public function programarNotificacion(array $datos, User $actor): NotificacionProgramada
    {
        $programada = new NotificacionProgramada;
        $programada->fill([
            'titulo' => $datos['titulo'],
            'contenido' => $datos['contenido'],
            'anio_cursada' => $datos['anio_cursada'] ?? null,
            'evento_id' => $datos['evento_id'] ?? null,
            'aviso_id' => $datos['aviso_id'] ?? null,
            'programada_para' => Carbon::parse($datos['programada_para']),
        ]);

        $programada->forceFill([
            'creado_por' => $actor->id,
            'estado' => EstadoNotificacionProgramada::Pendiente,
        ])->save();

        Log::info('notificacion_programada.creada', [
            'actor' => $actor->id,
            'notificacion_programada' => $programada->id,
        ]);

        return $programada;
    }

    public function despacharNotificacionProgramada(NotificacionProgramada $programada): bool
    {
        if (! $programada->estaPendiente()) {
            return false;
        }

        try {
            $destinatarios = $this->destinatariosAlumnos($programada->anio_cursada);

            if ($destinatarios->isNotEmpty()) {
                Notification::send($destinatarios, new NotificacionInstitucionalNotification(
                    programacionId: $programada->id,
                    titulo: $programada->titulo,
                    contenido: $programada->contenido,
                    destinatario: $programada->etiquetaDestinatario(),
                    avisoId: $programada->aviso_id,
                    eventoId: $programada->evento_id,
                ));
            }

            $programada->forceFill([
                'estado' => EstadoNotificacionProgramada::Enviada,
                'enviada_en' => now(),
                'mensaje_error' => null,
            ])->save();

            return true;
        } catch (Throwable $e) {
            $programada->forceFill([
                'estado' => EstadoNotificacionProgramada::Error,
                'mensaje_error' => 'No se pudo enviar la notificación programada.',
            ])->save();

            Log::error('notificacion_programada.error', [
                'notificacion_programada' => $programada->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Publica los avisos programados cuya hora ya llegó y envía notificaciones programadas vencidas.
     *
     * @return array{avisos: int, notificaciones: int}
     */
    public function despacharProgramacionesVencidas(): array
    {
        $avisosProgramados = Aviso::query()
            ->whereNull('publicado_en')
            ->whereNotNull('programado_para')
            ->where('programado_para', '<=', now())
            ->get();

        foreach ($avisosProgramados as $aviso) {
            $this->publicarAviso($aviso);
        }

        $notificacionesPendientes = NotificacionProgramada::query()
            ->where('estado', EstadoNotificacionProgramada::Pendiente->value)
            ->where('programada_para', '<=', now())
            ->get();

        $enviadas = 0;
        foreach ($notificacionesPendientes as $notificacion) {
            if ($this->despacharNotificacionProgramada($notificacion)) {
                $enviadas++;
            }
        }

        return [
            'avisos' => $avisosProgramados->count(),
            'notificaciones' => $enviadas,
        ];
    }

    private function notificarAvisoPublicado(Aviso $aviso): void
    {
        $destinatarios = $this->destinatariosAlumnos($aviso->anio_cursada);

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::send($destinatarios, new AvisoPublicadoNotification(
            avisoId: $aviso->id,
            titulo: $aviso->titulo,
            extracto: $aviso->contenido,
            destinatario: $aviso->etiquetaDestinatario(),
        ));
    }
}

