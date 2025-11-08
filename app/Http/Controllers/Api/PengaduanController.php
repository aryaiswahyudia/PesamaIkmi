<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pengaduan;
use App\Models\Lampiran;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Jobs\SendFcmPengaduanNotification;
use App\Jobs\SendFcmLaporanDiperbaruiNotification;
use App\Jobs\SendFcmDiskusiNotification;
use Exception;

class PengaduanController extends Controller
{
    /**
     * Menampilkan daftar pengaduan milik mahasiswa yang sedang login.
     * Mendukung filter status dan pencarian.
     */
    public function index(Request $request)
    {
        $mahasiswa = $request->user();

        // Mulai query builder dari relasi (otomatis filter by id_mahasiswa)
        $query = $mahasiswa->pengaduans();

        // --- Handle Filtering Status ---
        if ($request->filled('status') && $request->query('status') != 'Semua') {
            if (in_array($request->query('status'), ['Belum Ditanggapi', 'Diproses', 'Selesai', 'Ditolak'])) {
                $query->where('status', $request->query('status'));
            }
        }

        // --- Handle Searching ---
        if ($request->filled('search')) {
            $searchTerm = $request->query('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('judul_pengaduan', 'like', '%' . $searchTerm . '%')
                  ->orWhere('isi_laporan', 'like', '%' . $searchTerm . '%');
            });
        }
        // --- Handle Additional Filters ---
        if ($request->filled('id_jenis_masukan')) {
            $query->where('id_jenis_masukan', $request->query('id_jenis_masukan'));
        }
        if ($request->filled('id_jenis_pengaduan')) {
            $query->where('id_jenis_pengaduan', $request->query('id_jenis_pengaduan'));
        }
        if ($request->filled('id_pihak_terkait')) {
            $query->where('id_pihak_terkait', $request->query('id_pihak_terkait'));
        }

        // Handle Sorting (Urutan)
        $sortBy = $request->query('sortBy', 'terbaru'); // Default 'terbaru'
        if ($sortBy == 'terlama') {
            $query->orderBy('updated_at', 'asc');
        } else {
            $query->orderBy('updated_at', 'desc'); // Default terbaru
        }

        // Eksekusi Query
        $pengaduans = $query->with('lampirans',
                                'jenisMasukan',
                                'jenisPengaduan',
                                'pihakTerkait') // Muat lampiran (untuk thumbnail)
                           ->paginate(10) // 10 data per halaman
                           ->withQueryString(); // Agar parameter filter tetap ada di link paginasi

        return response()->json($pengaduans);
    }

    /**
     * Menampilkan detail satu pengaduan milik mahasiswa yang sedang login.
     */
    public function show(Request $request, $id_pengaduan)
    {
        $mahasiswa = $request->user();
        try {
            $pengaduan = $mahasiswa->pengaduans()
                ->with([
                    'mahasiswa',
                    'lampirans',
                    'jenisMasukan',
                    'jenisPengaduan',
                    'pihakTerkait',
                    'tanggapans' => function($query) {
                        $query->with(['user', 'lampirans'])
                              ->orderBy('created_at', 'asc');
                    },
                    'diskusiPengaduans' => function($query) {
                        $query->with(['sender', 'lampirans'])
                              ->orderBy('created_at', 'asc');
                    }

                ])
                ->findOrFail($id_pengaduan); // Gagal jika tidak ditemukan

            return response()->json($pengaduan);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Laporan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('[Pengaduan Show Failed] User: '.$request->user()->id_mahasiswa.' - '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal mengambil data laporan.'], 500);
        }
    }

    /**
     * Menyimpan pengaduan baru dari mahasiswa.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul_pengaduan'    => 'required|string|max:255',
            'isi_laporan'        => 'required|string',
            'lokasi'             => 'nullable|string|max:155',
            'id_jenis_masukan'   => 'required|exists:jenis_masukans,id_jenis_masukan',
            'id_jenis_pengaduan' => 'nullable|exists:jenis_pengaduans,id_jenis_pengaduan',
            'id_pihak_terkait'   => 'nullable|required_with:id_jenis_pengaduan|exists:pihak_terkait,id_pihak_terkait',
            'lampiran'           => 'nullable|array|max:5',
            'lampiran.*'         => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
        ]);
        // Catatan: Saya mengubah 'excel' menjadi 'xls,xlsx' agar lebih akurat

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
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

        $mahasiswa = $request->user();

        try {
            $pengaduanBaru = DB::transaction(function () use ($request, $mahasiswa) {

                // a. Simpan Pengaduan Utama (Tidak Berubah)
                $pengaduan = Pengaduan::create([
                    'judul_pengaduan'    => $request->judul_pengaduan,
                    'isi_laporan'        => $request->isi_laporan,
                    'lokasi'             => $request->lokasi,
                    'status'             => 'Belum Ditanggapi',
                    'id_mahasiswa'       => $mahasiswa->id_mahasiswa,
                    'id_jenis_masukan'   => $request->id_jenis_masukan,
                    'id_jenis_pengaduan' => $request->id_jenis_pengaduan,
                    'id_pihak_terkait'   => $request->id_pihak_terkait,
                ]);

                if ($request->hasFile('lampiran')) {
                    // Loop melalui setiap file dalam array 'lampiran'
                    foreach ($request->file('lampiran') as $file) {
                        $path = $file->store('lampiran_pengaduan', 'public');
                        $pengaduan->lampirans()->create([
                            'nama_file'   => $file->getClientOriginalName(),
                            'path_file'   => $path,
                            'tipe_file'   => $file->getMimeType(),
                            'ukuran_file' => $file->getSize(),
                        ]);
                    }
                }

                // --- c. Kirim Notifikasi FCM via Job ---
                SendFcmPengaduanNotification::dispatch($pengaduan, $mahasiswa);
                Log::info('[FCM Job Dispatched] Pengaduan ID: ' . $pengaduan->id_pengaduan);

                // --- d. Buat Notifikasi Database (History) ---
                $adminUsers = User::whereIn('jabatan', ['operator', 'administrator'])->get();
                $judulNotif = 'Pengaduan Baru Diterima!';
                $pesanNotif = "Pengaduan baru \"".Str::limit($pengaduan->judul_pengaduan, 50)."\" oleh {$mahasiswa->nama}.";

                foreach ($adminUsers as $admin) {
                    Notifikasi::create([
                        'notifiable_id'    => $admin->id_user,
                        'notifiable_type'  => User::class,
                        'id_pengaduan'     => $pengaduan->id_pengaduan,
                        'tipe_notifikasi'  => 'PENGADUAN_BARU_TO_ADMIN',
                        'judul_notifikasi' => $judulNotif,
                        'pesan_notifikasi' => $pesanNotif,
                        'dibaca'           => false,
                    ]);
                }

                return $pengaduan->load('lampirans');
            });
            // --- Akhir Transaksi ---

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengaduan berhasil dikirim.',
                'data'    => $pengaduanBaru,
            ], 201);

        } catch (Exception $e) {
            Log::error('[Pengaduan Store Failed] User: '.$mahasiswa->id_mahasiswa.' - '.$e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan pengaduan. Terjadi kesalahan internal.',
            ], 500);
        }
    }

    public function update(Request $request, $id_pengaduan)
    {
        $mahasiswa = $request->user();

        // 1. Validasi Baru (Multi-file)
        $validator = Validator::make($request->all(), [
            'judul_pengaduan'    => 'required|string|max:255',
            'isi_laporan'        => 'required|string',
            'lokasi'             => 'nullable|string|max:155',
            'id_jenis_masukan'   => 'required|exists:jenis_masukans,id_jenis_masukan',
            'id_jenis_pengaduan' => 'nullable|exists:jenis_pengaduans,id_jenis_pengaduan',
            'id_pihak_terkait'   => 'nullable|required_with:id_jenis_pengaduan|exists:pihak_terkait,id_pihak_terkait',
            'lampiran'           => 'nullable|array',
            'lampiran.*'         => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx',
            'existing_lampiran_ids'   => 'nullable|array',
            'existing_lampiran_ids.*' => 'integer|exists:lampirans,id_lampiran',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
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

        $newFilesCount = count($request->file('lampiran') ?? []);
        $existingFilesCount = count($request->input('existing_lampiran_ids') ?? []);

        if (($newFilesCount + $existingFilesCount) > 5) {
            // Jika totalnya lebih dari 5, kirim error
             return response()->json([
                'status' => 'error',
                'message' => 'Data tidak valid.',
                'errors' => [
                    'lampiran' => ['Jumlah total lampiran (lama dan baru) tidak boleh lebih dari 5 file.']
                ]
            ], 422);
        }

        try {
            $pengaduan = $mahasiswa->pengaduans()->findOrFail($id_pengaduan);

            if ($pengaduan->status != 'Belum Ditanggapi') {
                return response()->json(['status' => 'error', 'message' => 'Laporan ini sudah ditangani dan tidak bisa diedit.'], 403);
            }

            $pengaduanUpdated = DB::transaction(function () use ($request, $pengaduan, $mahasiswa) {

                // a. Update data teks
                $pengaduan->update($request->only([
                    'judul_pengaduan', 'isi_laporan', 'lokasi',
                    'id_jenis_masukan', 'id_jenis_pengaduan', 'id_pihak_terkait'
                ]));

                // Ambil ID lampiran lama yang ingin dipertahankan dari request
                $idsToKeep = $request->input('existing_lampiran_ids', []);

                // Cari lampiran lama yang TIDAK ADA di daftar 'idsToKeep'
                $filesToDelete = $pengaduan->lampirans()->whereNotIn('id_lampiran', $idsToKeep)->get();

                foreach ($filesToDelete as $fileLama) {
                    Storage::disk('public')->delete($fileLama->path_file);
                    $fileLama->delete();
                }

                if ($request->hasFile('lampiran')) {
                    foreach ($request->file('lampiran') as $file) {
                        $path = $file->store('lampiran_pengaduan', 'public');
                        $pengaduan->lampirans()->create([
                            'nama_file'   => $file->getClientOriginalName(),
                            'path_file'   => $path,
                            'tipe_file'   => $file->getMimeType(),
                            'ukuran_file' => $file->getSize(),
                        ]);
                    }
                }

                // d. Kirim Notifikasi (Logika notif Anda sudah benar)
                $adminUsers = User::whereIn('jabatan', ['operator', 'administrator'])->get();
                $judulNotif = "Laporan Diperbarui: {$pengaduan->judul_pengaduan}";
                $pesanNotif = "Laporan \"".Str::limit($pengaduan->judul_pengaduan, 50)."\" telah diperbarui oleh {$mahasiswa->nama}.";

                foreach ($adminUsers as $admin) {
                    Notifikasi::create([
                        'notifiable_id'    => $admin->id_user,
                        'notifiable_type'  => User::class,
                        'id_pengaduan'     => $pengaduan->id_pengaduan,
                        'tipe_notifikasi'  => 'PENGADUAN_DIPERBARUI_TO_ADMIN',
                        'judul_notifikasi' => $judulNotif,
                        'pesan_notifikasi' => $pesanNotif,
                    ]);
                }
                SendFcmLaporanDiperbaruiNotification::dispatch($pengaduan, $mahasiswa);
                Log::info('[FCM Job Diperbarui Dispatched] Pengaduan ID: ' . $pengaduan->id_pengaduan);

                return $pengaduan->fresh()->load(['lampirans', 'jenisMasukan', 'jenisPengaduan', 'pihakTerkait']);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Laporan berhasil diperbarui.',
                'data' => $pengaduanUpdated
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Laporan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            Log::error('[Pengaduan Update Failed] User: '.$mahasiswa->id_mahasiswa.' - '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui laporan.'], 500);
        }
    }

    /**
     * Menghapus pengaduan milik mahasiswa yang sedang login.
     * Hanya diperbolehkan jika status pengaduan "Belum Ditanggapi".
     */
    public function destroy(Request $request, $id_pengaduan)
    {
        try {
            // 1. Dapatkan mahasiswa yang sedang login
            $mahasiswa = $request->user();

            // 2. Cari pengaduan, pastikan milik mahasiswa ini
            $pengaduan = $mahasiswa->pengaduans()->findOrFail($id_pengaduan);

            // 3. Periksa Keamanan (Sangat Penting!)
            // Hanya izinkan hapus jika statusnya "Belum Ditanggapi"
            if ($pengaduan->status != 'Belum Ditanggapi') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal menghapus: Laporan ini sudah dalam proses penanganan.'
                ], 403); // 403 Forbidden
            }

            foreach ($pengaduan->lampirans as $lampiran) {
                Storage::disk('public')->delete($lampiran->path_file);
            }

            // 5. Hapus pengaduan
            $pengaduan->delete();

            // 6. Kirim respon sukses
            return response()->json([
                'status' => 'success',
                'message' => 'Laporan berhasil dihapus.'
            ], 200); // 200 OK (atau 204 No Content)

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Laporan tidak ditemukan atau Anda tidak berhak menghapusnya.'], 404);
        } catch (Exception $e) {
            Log::error('[Pengaduan Destroy Failed] User: '.$mahasiswa->id_mahasiswa.' - '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus laporan. Terjadi kesalahan server.'], 500);
        }
    }

    /**
     * Mahasiswa mengirim pesan diskusi baru.
     */
    public function kirimDiskusi(Request $request, $id_pengaduan)
    {
        $validator = Validator::make($request->all(), [
            'isi_pesan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $mahasiswa = $request->user();

        try {
            // ✅ Pastikan laporan milik mahasiswa login
            $pengaduan = $mahasiswa->pengaduans()->findOrFail($id_pengaduan);

            // ✅ Simpan pesan diskusi baru
            $diskusi = $pengaduan->diskusiPengaduans()->create([
                'sender_id'   => $mahasiswa->id_mahasiswa,
                'sender_type' => Mahasiswa::class,
                'isi_pesan'   => $request->isi_pesan,
            ]);

            $judulNotif = "Pesan Baru di Laporan {$pengaduan->judul_pengaduan}";
            $pesanNotif = "{$mahasiswa->nama}: " . \Illuminate\Support\Str::limit($diskusi->isi_pesan, 100);

            if (empty($pengaduan->id_user_penanggap_terakhir)) {
                // Belum ada penanggap → semua operator + admin dapat
                $targetUsers = User::whereIn('jabatan', ['operator', 'administrator'])->get();
            } else {
                // Sudah ada penanggap → hanya penanggap terakhir + admin
                $targetUsers = User::where('id_user', $pengaduan->id_user_penanggap_terakhir)
                    ->orWhere('jabatan', 'administrator')
                    ->get();
            }

            foreach ($targetUsers as $target) {
                $exists = Notifikasi::where([
                    'notifiable_id'   => $target->id_user,
                    'notifiable_type' => User::class,
                    'id_pengaduan'    => $pengaduan->id_pengaduan,
                    'tipe_notifikasi' => 'PESAN_DISKUSI_BARU_TO_ADMIN',
                ])
                ->where('created_at', '>=', now()->subSeconds(10)) // mencegah spam ganda
                ->exists();

                if (!$exists) {
                    Notifikasi::create([
                        'notifiable_id'    => $target->id_user,
                        'notifiable_type'  => User::class,
                        'id_pengaduan'     => $pengaduan->id_pengaduan,
                        'tipe_notifikasi'  => 'PESAN_DISKUSI_BARU_TO_ADMIN',
                        'judul_notifikasi' => $judulNotif,
                        'pesan_notifikasi' => $pesanNotif,
                        'dibaca'           => false,
                    ]);

                    \Log::info("[DISKUSI] ✅ Notifikasi dibuat untuk {$target->jabatan} (ID: {$target->id_user})");
                } else {
                    \Log::info("[DISKUSI] ⚠️ Duplikat notifikasi dilewati untuk {$target->jabatan} (ID: {$target->id_user})");
                }
            }

            SendFcmDiskusiNotification::dispatch($pengaduan, $mahasiswa, $diskusi);

            \Log::info('[DISKUSI] Pesan dikirim oleh mahasiswa ID: '.$mahasiswa->id_mahasiswa.' ke laporan ID: '.$pengaduan->id_pengaduan);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesan berhasil dikirim.',
                'data' => $diskusi->load('sender'),
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Laporan tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('[Kirim Diskusi Gagal] '.$e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim pesan.',
            ], 500);
        }
    }

    public function indexDashboard(Request $request)
    {
        try {
            $mahasiswa = $request->user(); // mahasiswa login

            // Ambil semua laporan mahasiswa tanpa pagination
            $query = $mahasiswa->pengaduans()
                ->with(['jenisMasukan', 'jenisPengaduan', 'pihakTerkait', 'lampirans'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',

                // Counters
                'count_belum'  => $query->where('status', 'Belum Ditanggapi')->count(),
                'count_proses' => $query->where('status', 'Diproses')->count(),
                'count_selesai'=> $query->where('status', 'Selesai')->count(),
                'count_ditolak'=> $query->where('status', 'Ditolak')->count(),

                // 5 laporan terbaru
                'recent' => $query->take(5)->values(),

            ], 200);

        } catch (\Exception $e) {
            Log::error('[Dashboard Mahasiswa Error] '.$e->getMessage());

            return response()->json([
                'status'  => 'error',
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

        // Column DB
        $column = match ($type) {
            'jenis_masukan' => 'id_jenis_masukan',
            'jenis_pengaduan' => 'id_jenis_pengaduan',
            'pihak_terkait' => 'id_pihak_terkait',
        };

        // Eloquent relation
        $relation = match ($type) {
            'jenis_masukan' => 'jenisMasukan',
            'jenis_pengaduan' => 'jenisPengaduan',
            'pihak_terkait' => 'pihakTerkait',
        };

        // Rentang waktu
        $fromDate = match ($request->range) {
            '7days'   => now()->subDays(7),
            '4weeks'  => now()->subWeeks(4),
            '6months' => now()->subMonths(6),
        };

        $data = \App\Models\Pengaduan::where('id_mahasiswa', $user->id_mahasiswa)
            ->where('created_at', '>=', $fromDate)
            ->with($relation)
            ->select($column, \DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->get();

        return response()->json([
            'labels' => $data->map(function ($d) use ($type, $relation) {
                if ($type === 'pihak_terkait') return $d->$relation->nama_pihak ?? '-';
                if ($type === 'jenis_masukan') return $d->$relation->jenis_masukan ?? '-';
                if ($type === 'jenis_pengaduan') return $d->$relation->jenis_pengaduan ?? '-';
            }),
            'data' => $data->pluck('total'),
        ]);
    }
}
