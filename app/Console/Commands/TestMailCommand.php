<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Exception;

class TestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-mail {email}'; // Kita akan mengirim email ke alamat yg kita tentukan

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengirim email tes untuk memeriksa konfigurasi SMTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recipientEmail = $this->argument('email');
        $this->info("Mencoba mengirim email tes ke: {$recipientEmail}");

        try {
            // Mengirim email paling sederhana menggunakan Mail facade
            Mail::raw('Ini adalah email tes dari aplikasi Laravel Anda.', function ($message) use ($recipientEmail) {
                $message->to($recipientEmail)
                        ->subject('Tes Konfigurasi Email');
            });

            // Jika berhasil sampai sini, berarti konfigurasi BENAR
            $this->info("✅ Email tes berhasil dikirim! Silakan periksa inbox Mailtrap Anda.");
            $this->info("Ini berarti masalahnya bukan di file .env, melainkan di cache.");

        } catch (Exception $e) {
            // Jika GAGAL, kita akan mendapatkan pesan error yang SEBENARNYA
            $this->error("❌ GAGAL mengirim email!");
            $this->error("Pesan Error Asli: " . $e->getMessage());
            $this->warn("Silakan periksa kembali file .env Anda atau masalah jaringan.");
        }

        return 0;
    }
}
