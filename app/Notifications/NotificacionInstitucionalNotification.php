<?php

namespace App\Notifications;

/**
 * HU 16: notificación o recordatorio programado vinculado opcionalmente a un evento o aviso.
 */
class NotificacionInstitucionalNotification extends ComunicacionElevenNotification
{
    public function __construct(
        public readonly int $programacionId,
        public readonly string $titulo,
        public readonly string $contenido,
        public readonly string $destinatario,
        public readonly ?int $avisoId = null,
        public readonly ?int $eventoId = null,
    ) {}

    protected function asuntoSinPrefijo(): string
    {
        return $this->titulo;
    }

    protected function textoExtracto(): string
    {
        return $this->contenido;
    }

    protected function urlDestino(): string
    {
        if ($this->avisoId !== null) {
            return route('avisos.show', $this->avisoId);
        }

        if ($this->eventoId !== null) {
            return route('calendario.index');
        }

        return route('notificaciones.index');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'categoria' => 'notificacion_programada',
            'notificacion_programada_id' => $this->programacionId,
            'aviso_id' => $this->avisoId,
            'evento_id' => $this->eventoId,
            'titulo' => $this->titulo,
            'mensaje' => $this->extractoSeguro($this->contenido),
            'destinatario' => $this->destinatario,
            'url' => $this->urlDestino(),
        ];
    }
}

