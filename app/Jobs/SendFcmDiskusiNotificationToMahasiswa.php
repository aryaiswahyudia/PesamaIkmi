<?php

namespace App\Jobs;

use App\Models\Pengaduan;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\DiskusiPengaduan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Firebase Imports
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class SendFcmDiskusiNotificationToMahasiswa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Pengaduan $pengaduan;
    protected User $admin;
    protected DiskusiPengaduan $diskusi;

    /**
     * Buat job baru.
     */
    public function __construct(Pengaduan $pengaduan, User $admin, DiskusiPengaduan $diskusi)
    {
        $this->pengaduan = $pengaduan;
        $this->admin = $admin;
        $this->diskusi = $diskusi;
    }

    /**
     * Jalankan job.
     */
    public function handle(): void
    {
        $mahasiswa = $this->pengaduan->mahasiswa;

        if (!$mahasiswa) {
            Log::error('[FCM Job Diskusi Mhs] ❌ Pengaduan ID ' . $this->pengaduan->id_pengaduan . ' tidak memiliki relasi mahasiswa.');
            return;
        }

        $token = $mahasiswa->fcm_token;
        if (is_null($token)) {
            Log::info('[FCM Job Diskusi Mhs] ℹ️ Mahasiswa ID ' . $mahasiswa->id_mahasiswa . ' tidak memiliki FCM token.');
            return;
        }

        $messaging = Firebase::messaging();

        // 📨 Judul & Isi Pesan
        $judul = "Pesan Baru dari Petugas";
        $body = "{$this->admin->nama}: " . Str::limit($this->diskusi->isi_pesan, 100);

        // ⚙️ Konfigurasi Android & iOS
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

        // ✅ Hybrid Message (agar tampil otomatis di background dan tetap bisa diproses di foreground)
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FirebaseNotification::create($judul, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'id_pengaduan' => (string) $this->pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'PESAN_DISKUSI_BARU', // ✅ Dibedakan dari versi admin
                'title' => $judul,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);

        try {
            $messaging->send($message);
            Log::info('[FCM Job Diskusi Mhs] ✅ Notifikasi terkirim ke Mahasiswa ID: ' . $mahasiswa->id_mahasiswa);
        } catch (\Exception $e) {
            Log::error('[FCM Job Diskusi Mhs] ❌ Gagal Mengirim: ' . $e->getMessage());
        }
    }
}
