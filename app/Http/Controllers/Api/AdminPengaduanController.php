<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pengaduan;
use App\Models\Tanggapan;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use Illuminate\Support\Str;
use App\Jobs\SendFcmTanggapanNotification;
use App\Jobs\SendFcmDiskusiNotificationToMahasiswa;
use App\Models\DiskusiPengaduan;
use Exception;

class AdminPengaduanController extends Controller
{
    /**
     * Menampilkan daftar pengaduan untuk Admin (sesuai role).
     * (Ini adalah method indexAdmin yang sudah kita ganti nama)
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        $query = Pengaduan::query();
        if ($admin->jabatan == 'operator') {
            $query->where(function($q) use ($admin) {
                $q->where('status', 'Belum Ditanggapi');
                $q->orWhere('id_user_penanggap_terakhir', $admin->id_user);
            });
        }

        if ($request->filled('status') && $request->query('status') != 'Semua') {
            if (in_array($request->query('status'), ['Belum Ditanggapi', 'Diproses', 'Selesai', 'Ditolak'])) {
                $query->where('status', $request->query('status'));
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->query('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('judul_pengaduan', 'like', '%' . $searchTerm . '%')
                  ->orWhere('isi_laporan', 'like', '%' . $searchTerm . '%');
            });
        }

        if ($request->filled('id_jenis_masukan')) {
            $query->where('id_jenis_masukan', $request->query('id_jenis_masukan'));
        }
        if ($request->filled('id_jenis_pengaduan')) {
            $query->where('id_jenis_pengaduan', $request->query('id_jenis_pengaduan'));
        }
        if ($request->filled('id_pihak_terkait')) {
            $query->where('id_pihak_terkait', $request->query('id_pihak_terkait'));
        }

        $sortBy = $request->query('sortBy', 'terbaru');
        if ($sortBy == 'terlama') {
            $query->orderBy('updated_at', 'asc');
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $pengaduans = $query->with([
                                'mahasiswa:id_mahasiswa,nama,nim,prodi,angkatan',
                                'lampirans',
                                'jenisMasukan',
                                'jenisPengaduan',
                                'pihakTerkait'
                            ])
                           ->paginate(10)
                           ->withQueryString();

        return response()->json($pengaduans);
    }

    /**
     * Admin melihat detail laporan (termasuk data mahasiswa).
     */
    public function show(Request $request, $id_pengaduan)
    {
        try {
            $pengaduan = Pengaduan::with([
                'mahasiswa',
                'lampirans',
                'jenisMasukan',
                'jenisPengaduan',
                'pihakTerkait',
                'tanggapans' => function($q) { $q->with(['user', 'lampirans'])->orderBy('created_at', 'asc'); },
                'diskusiPengaduans' => function($q) { $q->with(['sender'])->orderBy('created_at', 'asc'); }
            ])
            ->findOrFail($id_pengaduan);

            return response()->json($pengaduan);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Laporan tidak ditemukan.'], 404);
        }
    }

    /**
     * Operator mengirim tanggapan resmi.
     */
    public function storeTanggapan(Request $request, $id_pengaduan)
    {
        // 1. Validasi Baru (Multi-file)
        $validator = Validator::make($request->all(), [
            'isi_tanggapan' => 'required|string',
            'status_tanggapan' => 'required|in:Diproses,Selesai,Ditolak',
            'lampiran'           => 'nullable|array|max:5', // Harus berupa array
            'lampiran.*'         => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', // Validasi setiap item
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('lampiran')) {
            foreach ($request->file('lampiran') as $file) {
                if ($file->getSize() > 2 * 1024 * 1024) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Ukuran file maksimal 2MB/file.',
                    ], 422);
                }
            }
        }

        $operator = $request->user();
        $pengaduan = Pengaduan::findOrFail($id_pengaduan);

        try {
            $tanggapan = DB::transaction(function () use ($request, $operator, $pengaduan) {

                // a. Simpan Tanggapan
                $tanggapanBaru = $pengaduan->tanggapans()->create([
                    'isi_tanggapan' => $request->isi_tanggapan,
                    'status_tanggapan' => $request->status_tanggapan,
                    'id_user' => $operator->id_user,
                ]);

                if ($request->hasFile('lampiran')) {
                    // Loop melalui setiap file dalam array 'lampiran'
                    foreach ($request->file('lampiran') as $file) {
                        $path = $file->store('lampiran_tanggapan', 'public');
                        $tanggapanBaru->lampirans()->create([
                            'nama_file'   => $file->getClientOriginalName(),
                            'path_file'   => $path,
                            'tipe_file'   => $file->getMimeType(),
                            'ukuran_file' => $file->getSize(),
                        ]);
                    }
                }

                // c. Update Status Pengaduan
                $pengaduan->status = $request->status_tanggapan;
                $pengaduan->id_user_penanggap_terakhir = $operator->id_user;
                $pengaduan->save();

                // d. Buat Notifikasi Database untuk Mahasiswa
                $mahasiswa = $pengaduan->mahasiswa;
                Notifikasi::create([
                    'notifiable_id' => $mahasiswa->id_mahasiswa,
                    'notifiable_type' => Mahasiswa::class,
                    'id_pengaduan' => $pengaduan->id_pengaduan,
                    'tipe_notifikasi' => 'TANGGAPAN_BARU',
                    'judul_notifikasi' => 'Laporan Anda Telah Ditanggapi!',
                    'pesan_notifikasi' => "Status laporan \"".Str::limit($pengaduan->judul_pengaduan, 50)."\" diubah menjadi '{$request->status_tanggapan}'."
                ]);

                // e. Kirim Notifikasi FCM
                SendFcmTanggapanNotification::dispatch($pengaduan, $mahasiswa, $tanggapanBaru);

                return $tanggapanBaru->load('user', 'lampirans');
            });

            return response()->json(['status' => 'success', 'message' => 'Tanggapan berhasil dikirim.', 'data' => $tanggapan], 201);

        } catch (Exception $e) {
            Log::error('[Store Tanggapan Gagal] Op: '.$operator->id_user.' - '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirim tanggapan.'], 500);
        }
    }

    /**
     * Admin/Operator mengirim pesan diskusi.
     */
    public function kirimDiskusi(Request $request, $id_pengaduan)
    {
        $validator = Validator::make($request->all(), ['isi_pesan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $admin = $request->user(); // Ini bisa Admin atau Operator
        $pengaduan = Pengaduan::findOrFail($id_pengaduan);

        try {
            // 1. Simpan pesan (Sender adalah User)
            $diskusi = $pengaduan->diskusiPengaduans()->create([
                'sender_id'   => $admin->id_user,
                'sender_type' => User::class, // <-- Path ke model User
                'isi_pesan'   => $request->isi_pesan,
            ]);

            // 2. Buat Notifikasi Database untuk Mahasiswa
            $mahasiswa = $pengaduan->mahasiswa;
            Notifikasi::create([
                'notifiable_id' => $mahasiswa->id_mahasiswa,
                'notifiable_type' => Mahasiswa::class,
                'id_pengaduan' => $pengaduan->id_pengaduan,
                'tipe_notifikasi' => 'PESAN_DISKUSI_BARU',
                'judul_notifikasi' => "Pesan Baru dari Petugas",
                'pesan_notifikasi' => "{$admin->nama}: " . Str::limit($diskusi->isi_pesan, 100)
            ]);
            SendFcmDiskusiNotificationToMahasiswa::dispatch($pengaduan, $admin, $diskusi);

            // 3. Ambil ulang data lengkap (agar foto_profile ikut termuat)
            $diskusiLengkap = DiskusiPengaduan::with(['sender' => function ($q) {
                $q->select('id_user', 'nama', 'foto_profile');
            }])->find($diskusi->id_diskusi);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan terkirim.',
                'data' => $diskusiLengkap
            ], 201);

        } catch (Exception $e) {
            Log::error('[Kirim Diskusi Admin Gagal] User: '.$admin->id_user.' - '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengirim pesan.'], 500);
        }
    }

    public function indexDashboard(Request $request)
    {
        try {
            $user = $request->user();

            // Base query
            $query = Pengaduan::query();

            // 🔹 Jika operator: hanya tampilkan laporan yang dia tangani atau yang belum ditanggapi
            if ($user->jabatan === 'operator') {
                $query->where(function ($q) use ($user) {
                    $q->where('status', 'Belum Ditanggapi')
                    ->orWhere('id_user_penanggap_terakhir', $user->id_user);
                });
            }

            // 🔹 Jika admin: tampilkan semua laporan (tanpa filter)
            // (jadi nggak usah pakai else — default-nya semua data)

            // Ambil semua laporan (tanpa pagination untuk hitung count)
            $all = $query->with(['lampirans','jenisMasukan', 'jenisPengaduan', 'pihakTerkait', 'mahasiswa'])
                        ->orderBy('created_at', 'desc')
                        ->get();

            // Hitung jumlah berdasarkan status
            $countBelum = $all->where('status', 'Belum Ditanggapi')->count();
            $countProses = $all->where('status', 'Diproses')->count();
            $countSelesai = $all->where('status', 'Selesai')->count();
            $countDitolak = $all->where('status', 'Ditolak')->count();

            // Ambil 5 laporan terbaru
            $recent = $all->take(5)->values();

            return response()->json([
                'status' => 'success',
                'jabatan' => $user->jabatan,
                'count_belum' => $countBelum,
                'count_proses' => $countProses,
                'count_selesai' => $countSelesai,
                'count_ditolak' => $countDitolak,
                'recent' => $recent,
            ], 200);
        } catch (\Exception $e) {
            Log::error('[Dashboard Admin Error] ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data dashboard.'
            ], 500);
        }
    }

    public function chart(Request $request)
    {
        $request->validate([
            'type' => 'required|in:jenis_masukan,jenis_pengaduan,pihak_terkait',
            'range' => 'required|in:7days,4weeks,6months',
        ]);

        $user = $request->user();
        $type = $request->type;
        $range = $request->range;

        $column = match ($type) {
            'jenis_masukan' => 'id_jenis_masukan',
            'jenis_pengaduan' => 'id_jenis_pengaduan',
            'pihak_terkait' => 'id_pihak_terkait',
        };

        $relation = match ($type) {
            'jenis_masukan' => 'jenisMasukan',
            'jenis_pengaduan' => 'jenisPengaduan',
            'pihak_terkait' => 'pihakTerkait',
        };

        $fromDate = match ($range) {
            '7days'   => now()->subDays(7),
            '4weeks'  => now()->subWeeks(4),
            '6months' => now()->subMonths(6),
        };

        $query = \App\Models\Pengaduan::query()
            ->where('created_at', '>=', $fromDate)
            ->with($relation)
            ->select($column, \DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total');

        if ($user->jabatan == 'operator') {
            $query->where(function ($q) use ($user) {
                $q->where('id_user_penanggap_terakhir', $user->id_user)
                ->orWhereNull('id_user_penanggap_terakhir')
                ->orWhere('status', 'Belum Ditanggapi');
            });
        }

        $data = $query->get();

        $labels = $data->map(function ($item) use ($relation, $type) {
            if ($item->$relation) {
                return match ($type) {
                    'jenis_masukan' => $item->$relation->jenis_masukan ?? '-',
                    'jenis_pengaduan' => $item->$relation->jenis_pengaduan ?? '-',
                    'pihak_terkait' => $item->$relation->nama_pihak ?? '-',
                    default => '-',
                };
            }
            return '-';
        });

        return response()->json([
            'labels' => $labels,
            'data' => $data->pluck('total'),
        ]);
    }
}
