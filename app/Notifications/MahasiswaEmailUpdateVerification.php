<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MahasiswaEmailUpdateVerification extends Notification
{
    use Queueable;

    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verifikasi Email Baru Anda')
            ->greeting('Halo, ' . $notifiable->nama . '!')
            ->line('Anda baru saja memperbarui email akun Anda.')
            ->line('Gunakan kode verifikasi berikut untuk mengaktifkan email baru ini:')
            ->line($this->token)
            ->line('Kode berlaku selama 15 menit.')
            ->line('Jika Anda tidak meminta perubahan email, abaikan pesan ini.');
    }
}
