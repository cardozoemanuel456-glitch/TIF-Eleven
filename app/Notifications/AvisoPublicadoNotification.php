<?php

namespace App\Notifications;

/**
 * M2-12: notificación enviada una única vez cuando se publica un aviso.
 */
class AvisoPublicadoNotification extends ComunicacionElevenNotification
{
    public function __construct(
        public readonly int $avisoId,
        public readonly string $titulo,
        public readonly string $extracto,
        public readonly string $destinatario,
    ) {}

    protected function asuntoSinPrefijo(): string
    {
        return $this->titulo;
    }

    protected function textoExtracto(): string
    {
        return $this->extracto;
    }

    protected function urlDestino(): string
    {
        return route('avisos.show', $this->avisoId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'categoria' => 'aviso_publicado',
            'aviso_id' => $this->avisoId,
            'evento_id' => null,
            'titulo' => $this->titulo,
            'mensaje' => $this->extractoSeguro($this->extracto),
            'destinatario' => $this->destinatario,
            'url' => route('avisos.show', $this->avisoId),
        ];
    }
}

