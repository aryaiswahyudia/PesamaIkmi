<?php

namespace App\Jobs;

use App\Models\Pengaduan;
use App\Models\Mahasiswa;
use App\Models\Tanggapan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Firebase imports
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class SendFcmTanggapanNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Pengaduan $pengaduan;
    protected Mahasiswa $mahasiswa;
    protected Tanggapan $tanggapan;

    /**
     * Buat job baru.
     */
    public function __construct(Pengaduan $pengaduan, Mahasiswa $mahasiswa, Tanggapan $tanggapan)
    {
        $this->pengaduan = $pengaduan;
        $this->mahasiswa = $mahasiswa;
        $this->tanggapan = $tanggapan;
    }

    /**
     * Jalankan job.
     */
    public function handle(): void
    {
        $token = $this->mahasiswa->fcm_token;
        if (is_null($token)) {
            Log::info('[FCM Job Tanggapan] ❌ Mahasiswa ID ' . $this->mahasiswa->id_mahasiswa . ' tidak memiliki FCM token.');
            return;
        }

        $messaging = Firebase::messaging();

        // 📝 Judul dan isi notifikasi
        $judul = "Laporan Anda Telah Ditanggapi!";
        $body = "Status laporan \"" . Str::limit($this->pengaduan->judul_pengaduan, 50) .
                "\" diubah menjadi '{$this->tanggapan->status_tanggapan}'.";

        // 🔧 Konfigurasi Android & iOS
        $androidConfig = AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'channel_id' => 'high_importance_channel',
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ]);

        $apnsConfig = ApnsConfig::fromArray([
            'payload' => ['aps' => ['sound' => 'default']],
        ]);

        // ✅ Gunakan hybrid message: tampil otomatis di background, tapi tetap bisa diproses manual di foreground
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FirebaseNotification::create($judul, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'id_pengaduan' => (string) $this->pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'TANGGAPAN_BARU', // ✅ Dibuat unik & konsisten
                'title' => $judul,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);

        try {
            $messaging->send($message);
            Log::info('[FCM Job Tanggapan] ✅ Hybrid message terkirim ke Mahasiswa ID: ' . $this->mahasiswa->id_mahasiswa);
        } catch (\Exception $e) {
            Log::error('[FCM Job Tanggapan] ❌ Gagal Mengirim: ' . $e->getMessage());
        }
    }
}
