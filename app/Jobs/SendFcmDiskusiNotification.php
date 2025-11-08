<?php

namespace App\Jobs;

use App\Models\Pengaduan;
use App\Models\Mahasiswa;
use App\Models\User;
use App\Models\DiskusiPengaduan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class SendFcmDiskusiNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Pengaduan $pengaduan;
    protected Mahasiswa $mahasiswa;
    protected DiskusiPengaduan $diskusi;

    public function __construct(Pengaduan $pengaduan, Mahasiswa $mahasiswa, DiskusiPengaduan $diskusi)
    {
        $this->pengaduan = $pengaduan;
        $this->mahasiswa = $mahasiswa;
        $this->diskusi = $diskusi;
    }

    public function handle(): void
    {
        // 🔐 Cegah duplikasi pengiriman
        $lockKey = 'fcm_diskusi_lock_' . $this->diskusi->id_diskusi;
        if (Cache::has($lockKey)) {
            Log::warning("[FCM Job Diskusi] ❌ Duplikat deteksi (Diskusi ID {$this->diskusi->id_diskusi}) dilewati.");
            return;
        }
        Cache::put($lockKey, true, 5);

        // 🎯 Ambil token admin
        $adminTokens = User::where('jabatan', 'administrator')
            ->pluck('fcm_token')
            ->filter()
            ->toArray();

        // 🧠 Tentukan siapa target operator
        $operatorTokens = [];

        if (!empty($this->pengaduan->id_user_penanggap_terakhir)) {
            // Jika laporan SUDAH ditanggapi → kirim hanya ke penanggap terakhir
            $penanggap = User::find($this->pengaduan->id_user_penanggap_terakhir);
            if ($penanggap && $penanggap->fcm_token) {
                $operatorTokens[] = $penanggap->fcm_token;
                Log::info("[FCM Job Diskusi] 🎯 Mengirim hanya ke penanggap terakhir (User ID: {$penanggap->id_user})");
            } else {
                Log::info("[FCM Job Diskusi] ⚠️ Token penanggap terakhir kosong — tidak mengirim ke operator lain.");
            }
        } else {
            // Jika BELUM ditanggapi → kirim ke semua operator
            $operatorTokens = User::where('jabatan', 'operator')
                ->pluck('fcm_token')
                ->filter()
                ->toArray();
            Log::info("[FCM Job Diskusi] 📢 Laporan belum ditanggapi → dikirim ke semua operator.");
        }

        // 🧩 Gabungkan semua target (operator + admin)
        $targets = array_values(array_unique(array_merge($operatorTokens, $adminTokens)));

        if (empty($targets)) {
            Log::info("[FCM Job Diskusi] ❌ Tidak ada token FCM aktif untuk Pengaduan ID: {$this->pengaduan->id_pengaduan}");
            return;
        }

        // 📝 Buat isi notifikasi
        $judul = "Pesan Baru di Laporan: {$this->pengaduan->judul_pengaduan}";
        $body = "{$this->mahasiswa->nama}: " . Str::limit($this->diskusi->isi_pesan, 120);

        // ⚙️ Payload FCM hybrid
        $payload = [
            'notification' => [
                'title' => $judul,
                'body'  => $body,
            ],
            'data' => [
                'id_pengaduan' => (string) $this->pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'PESAN_DISKUSI_BARU_TO_ADMIN',
                'id_user_penanggap_terakhir' => (string) ($this->pengaduan->id_user_penanggap_terakhir ?? ''),
                'title' => $judul,
                'body' => $body,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'high_importance_channel',
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        try {
            $messaging = Firebase::messaging();

            if (count($targets) > 1) {
                $report = $messaging->sendMulticast(
                    CloudMessage::fromArray($payload),
                    $targets
                );
                Log::info("[FCM Job Diskusi] ✅ Dikirim ke " . count($targets) . " target. Success: {$report->successes()->count()} | Failures: {$report->failures()->count()}");
            } else {
                $messaging->send(
                    CloudMessage::fromArray(array_merge($payload, ['token' => $targets[0]]))
                );
                Log::info("[FCM Job Diskusi] ✅ Dikirim ke 1 target (token tunggal).");
            }
        } catch (\Exception $e) {
            Log::error("[FCM Job Diskusi] ❌ Gagal kirim: {$e->getMessage()}");
        }
    }
}
