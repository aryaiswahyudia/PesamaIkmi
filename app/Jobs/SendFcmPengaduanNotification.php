<?php

namespace App\Jobs;

use App\Models\Pengaduan;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class SendFcmPengaduanNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Pengaduan $pengaduan;
    protected Mahasiswa $mahasiswa;

    /**
     * Create a new job instance.
     */
    public function __construct(Pengaduan $pengaduan, Mahasiswa $mahasiswa)
    {
        $this->pengaduan = $pengaduan;
        $this->mahasiswa = $mahasiswa;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 🎯 Ambil semua admin/operator dengan token FCM valid
        $admins = User::whereIn('jabatan', ['operator', 'administrator'])
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter(fn($token) => !empty($token))
            ->unique()
            ->values()
            ->toArray();

        if (empty($admins)) {
            Log::info('[FCM Job Pengaduan] ❌ Tidak ada token admin ditemukan.');
            return;
        }

        $messaging = Firebase::messaging();

        // 📝 Judul dan isi notifikasi
        $judul = 'Pengaduan Baru Diterima!';
        $body = "Laporan baru dari {$this->mahasiswa->nama}: \"" . Str::limit($this->pengaduan->judul_pengaduan, 50) . "\".";

        // 🔧 Konfigurasi Android dan iOS
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

        // ✅ Gunakan HYBRID MESSAGE: tampil otomatis di background, tapi lengkap juga untuk Flutter di foreground
        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($judul, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'id_pengaduan' => (string) $this->pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'PENGADUAN_BARU_TO_ADMIN', // 👈 ubah supaya unik dari versi mahasiswa
                'title' => $judul, // ✅ untuk Flutter handler
                'body' => $body,   // ✅ untuk Flutter handler
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);

        try {
            $report = $messaging->sendMulticast($message, $admins);
            Log::info("[FCM Job Pengaduan] ✅ Sukses kirim ke admin. Success: {$report->successes()->count()} | Failures: {$report->failures()->count()}");
        } catch (\Exception $e) {
            Log::error('[FCM Job Pengaduan] ❌ Gagal mengirim: ' . $e->getMessage());
        }
    }
}
