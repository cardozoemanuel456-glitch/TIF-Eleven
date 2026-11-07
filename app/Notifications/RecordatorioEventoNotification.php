<?php

namespace App\Notifications;

/**
 * M2-12 y M2-13: recordatorio automático de parcial o mesa de examen.
 */
class RecordatorioEventoNotification extends ComunicacionElevenNotification
{
    public function __construct(
        public readonly int $eventoId,
        public readonly string $titulo,
        public readonly string $tipoEtiqueta,
        public readonly string $fechaTexto,
        public readonly string $destinatario,
    ) {}

    protected function asuntoSinPrefijo(): string
    {
        return "Recordatorio: {$this->titulo}";
    }

    protected function textoExtracto(): string
    {
        return "Recordatorio de {$this->tipoEtiqueta}: «{$this->titulo}» ({$this->fechaTexto}).";
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
            'categoria' => 'recordatorio_evento',
            'aviso_id' => null,
            'evento_id' => $this->eventoId,
            'titulo' => "Recordatorio: {$this->titulo}",
            'mensaje' => $this->extractoSeguro($this->textoExtracto()),
            'destinatario' => $this->destinatario,
            'url' => route('calendario.index'),
        ];
    }
}

