<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MahasiswaVerificationNotification extends Notification
{
    use Queueable;

    protected $token;

    /**
     * Buat instance notifikasi baru.
     *
     * @param string $token Token 6 digit untuk verifikasi.
     */
    public function __construct($token) // <-- PERBAIKAN UTAMA DI SINI
    {
        $this->token = $token;
    }

    /**
     * Dapatkan channel pengiriman notifikasi.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Dapatkan representasi email dari notifikasi.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Verifikasi Akun Pendaftaran Anda')
                    ->greeting('Halo, ' . $notifiable->nama . '!')
                    ->line('Terima kasih telah mendaftar. Gunakan kode 6 digit di bawah ini untuk memverifikasi akun Anda:')
                    ->line($this->token) // <-- Sekarang $this->token punya nilai
                    ->line('Kode ini akan kedaluwarsa dalam 15 menit.')
                    ->line('Jika Anda tidak mendaftar, abaikan email ini.');
    }

    /**
     * Dapatkan representasi array dari notifikasi.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
