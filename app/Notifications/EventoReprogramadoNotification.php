<?php

namespace App\Notifications;

/**
 * M2-05 y M2-12: notificación al cambiar la fecha de inicio de un parcial o mesa de examen.
 */
class EventoReprogramadoNotification extends ComunicacionElevenNotification
{
    public function __construct(
        public readonly int $eventoId,
        public readonly string $titulo,
        public readonly string $tipoEtiqueta,
        public readonly string $fechaAnterior,
        public readonly string $nuevaFecha,
        public readonly string $destinatario,
    ) {}

    protected function asuntoSinPrefijo(): string
    {
        return "Reprogramación: {$this->titulo}";
    }

    protected function textoExtracto(): string
    {
        return "{$this->tipoEtiqueta} «{$this->titulo}» cambió de fecha: pasó del {$this->fechaAnterior} al {$this->nuevaFecha}.";
    }

    protected function urlDestino(): string
    {
        return route('calendario.index');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'categoria' => 'evento_reprogramado',
            'aviso_id' => null,
            'evento_id' => $this->eventoId,
            'titulo' => "Reprogramación: {$this->titulo}",
            'mensaje' => $this->extractoSeguro($this->textoExtracto()),
            'destinatario' => $this->destinatario,
            'url' => route('calendario.index'),
        ];
    }
}

