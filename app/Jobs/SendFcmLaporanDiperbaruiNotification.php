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
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Laravel\Firebase\Facades\Firebase;

class SendFcmLaporanDiperbaruiNotification implements ShouldQueue
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
        // 🎯 Ambil semua admin/operator dengan FCM token valid
        $admins = User::whereIn('jabatan', ['operator', 'administrator'])
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter(fn($token) => !empty($token))
            ->unique()
            ->values()
            ->toArray();

        if (empty($admins)) {
            Log::info('[FCM Job Diperbarui] ❌ Tidak ada token admin ditemukan untuk laporan ID: ' . $this->pengaduan->id_pengaduan);
            return;
        }

        $messaging = Firebase::messaging();

        // 📝 Judul dan isi notifikasi
        $judul = "Laporan Diperbarui: {$this->pengaduan->judul_pengaduan}";
        $body = "Laporan \"{$this->pengaduan->judul_pengaduan}\" telah diperbarui oleh {$this->mahasiswa->nama}.";

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

        // ✅ Gunakan hybrid message: bisa otomatis tampil di background, juga bisa diproses manual di foreground
        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($judul, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'id_pengaduan' => (string) $this->pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'PENGADUAN_DIPERBARUI_TO_ADMIN',
                'title' => $judul, // ✅ Tambahkan untuk Flutter
                'body' => $body,   // ✅ Tambahkan untuk Flutter
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);

        try {
            $report = $messaging->sendMulticast($message, $admins);
            Log::info("[FCM Job Diperbarui] ✅ Sukses kirim ke admin. Success: {$report->successes()->count()} | Failures: {$report->failures()->count()}");
        } catch (\Exception $e) {
            Log::error('[FCM Job Diperbarui] ❌ Gagal mengirim: ' . $e->getMessage());
        }
    }
}
