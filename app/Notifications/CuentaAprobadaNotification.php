<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** M3-04 · Aviso al alumno cuando Preceptoría aprueba su cuenta. Sin datos personales. */
class CuentaAprobadaNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Eleven] Tu cuenta fue aprobada')
            ->greeting('Hola')
            ->line('Preceptoría aprobó tu registro. Ya podés ingresar a Eleven con tu correo y tu contraseña.')
            ->action('Ingresar a Eleven', route('login'))
            ->salutation('Equipo de Eleven');
    }
}
