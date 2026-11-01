<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Correo de restablecimiento de contraseña, en español y sin datos personales en el texto. */
class RestablecerPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('[Eleven] Restablecer contraseña')
            ->greeting('Hola')
            ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta de Eleven.')
            ->action('Restablecer contraseña', $url)
            ->line("El enlace se puede usar una sola vez y vence en {$minutos} minutos.")
            ->line('Si no lo pediste vos, ignorá este correo: tu contraseña no cambia.')
            ->salutation('Equipo de Eleven');
    }
}