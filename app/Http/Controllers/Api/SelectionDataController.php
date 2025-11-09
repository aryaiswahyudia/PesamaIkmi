<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisMasukan;
use App\Models\JenisPengaduan;
use App\Models\PihakTerkait;

class SelectionDataController extends Controller
{
    /**
     * Mengambil daftar semua Jenis Masukan.
     */
    public function getJenisMasukans()
    {
        $jenisMasukans = JenisMasukan::orderBy('jenis_masukan', 'asc')->get();
        return response()->json($jenisMasukans);
    }

    /**
     * Mengambil daftar semua Jenis Pengaduan.
     */
    public function getJenisPengaduans()
    {
        $jenisPengaduans = JenisPengaduan::orderBy('jenis_pengaduan', 'asc')->get();
        return response()->json($jenisPengaduans);
    }

    /**
     * Mengambil daftar semua Pihak Terkait.
     */
    public function getPihakTerkait()
    {
        $pihakTerkait = PihakTerkait::orderBy('nama_pihak', 'asc')->get();
        return response()->json($pihakTerkait);
    }
}
