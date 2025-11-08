<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisMasukan;
use App\Models\JenisPengaduan;
use App\Models\PihakTerkait;
use Illuminate\Support\Facades\Cache;

class SelectionDataController extends Controller
{
    /**
     * Mengambil daftar semua Jenis Masukan (Pengaduan, Saran, dll.).
     * Hasilnya di-cache selama 60 menit.
     */
    public function getJenisMasukans()
    {
        $jenisMasukans = Cache::remember('jenis_masukan_options', 60 * 60, function () {
            return JenisMasukan::orderBy('jenis_masukan', 'asc')->get();
        });
        return response()->json($jenisMasukans);
    }

    /**
     * Mengambil daftar semua Jenis Pengaduan (Akademik, Fasilitas, dll.).
     * Hasilnya di-cache selama 60 menit.
     */
    public function getJenisPengaduans()
    {
        $jenisPengaduans = Cache::remember('jenis_pengaduan_options', 60 * 60, function () {
            return JenisPengaduan::orderBy('jenis_pengaduan', 'asc')->get();
        });
        return response()->json($jenisPengaduans);
    }

    /**
     * Mengambil daftar semua Pihak Terkait.
     * Hasilnya di-cache selama 60 menit.
     */
    public function getPihakTerkait() // Nama fungsi disesuaikan
    {
        $pihakTerkait = Cache::remember('pihak_terkait_options', 60 * 60, function () {
            return PihakTerkait::orderBy('nama_pihak', 'asc')->get();
        });
        return response()->json($pihakTerkait);
    }
}
