<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** M3-03 · Enlace de verificación de correo: firmado, de un solo uso y con vencimiento (24 h). */
class VerificarCorreoNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $horas = (int) round((int) config('auth.verification.expire', 1440) / 60);

        return (new MailMessage)
            ->subject('[Eleven] Verificá tu correo')
            ->greeting('Hola')
            ->line('Recibimos tu registro en Eleven. Para continuar, verificá que esta dirección de correo es tuya.')
            ->action('Verificar correo', $this->urlDeVerificacion($notifiable))
            ->line("El enlace se puede usar una sola vez y vence en {$horas} horas.")
            ->line('Después, Preceptoría revisará tu registro y te avisaremos por email cuando tu cuenta esté activa.')
            ->line('Si no te registraste vos, ignorá este correo.')
            ->salutation('Equipo de Eleven');
    }

    public function urlDeVerificacion(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ]
        );
    }
}
