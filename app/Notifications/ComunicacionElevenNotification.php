<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base para las comunicaciones de M2 (M2-09 a M2-12).
 * Guarda siempre en la campana de la app y envía por correo (con 3 reintentos)
 * únicamente cuando la cuenta tiene correo y no desactivó notificar_email.
 * Nunca incluye datos personales en el correo.
 */
abstract class ComunicacionElevenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canales = ['database'];

        if (($notifiable->notificar_email ?? false) && filled($notifiable->email ?? null)) {
            $canales[] = 'mail';
        }

        return $canales;
    }

    /**
     * La campana se persiste en el acto; el correo sale por la cola configurada.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Eleven] '.$this->asuntoSinPrefijo())
            ->greeting('Hola')
            ->line($this->extractoSeguro($this->textoExtracto()))
            ->action('Ver en Eleven', $this->urlDestino())
            ->salutation('Equipo de Eleven');
    }

    /**
     * Limita el extracto del correo a un máximo estricto de 300 caracteres (M2-10).
     */
    protected function extractoSeguro(string $texto): string
    {
        $limpio = preg_replace('/\s+/u', ' ', trim($texto)) ?? '';

        return Str::limit($limpio, 297, '...');
    }

    abstract protected function asuntoSinPrefijo(): string;

    abstract protected function textoExtracto(): string;

    abstract protected function urlDestino(): string;
}

