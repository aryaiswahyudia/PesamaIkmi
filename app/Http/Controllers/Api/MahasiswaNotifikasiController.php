<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notifikasi;

class MahasiswaNotifikasiController extends Controller
{
    /**
     * Menampilkan daftar notifikasi untuk mahasiswa yang sedang login.
     */
    public function index(Request $request)
    {
        $mahasiswa = $request->user();

        // Ambil notifikasi milik mahasiswa, urutkan dari yang terbaru, dan paginasi
        $notifikasis = $mahasiswa->notifikasis()
                            ->orderBy('created_at', 'desc')
                            ->paginate(20);

        return response()->json($notifikasis);
    }

    /**
     * Menandai satu notifikasi sebagai "dibaca".
     */
    public function markAsRead(Request $request, $id_notifikasi)
    {
        $mahasiswa = $request->user();

        // Cari notifikasi, pastikan milik mahasiswa ini, lalu update
        $notifikasi = $mahasiswa->notifikasis()->findOrFail($id_notifikasi);

        if (!$notifikasi->dibaca) {
            $notifikasi->dibaca = true;
            $notifikasi->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi ditandai sebagai dibaca.',
            'data' => $notifikasi
        ]);
    }

    public function destroy(Request $request, $id_notifikasi)
    {
        $mahasiswa = $request->user();

        try {
            // Cari notifikasi, pastikan milik mahasiswa ini, lalu hapus
            $notifikasi = $mahasiswa->notifikasis()->findOrFail($id_notifikasi);
            $notifikasi->delete();

            // 204 No Content adalah respons standar untuk delete sukses
            return response()->json(null, 204);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus notifikasi.'], 500);
        }
    }

    /**
     * Menghapus semua notifikasi untuk mahasiswa yang sedang login.
     */
    public function clearAll(Request $request)
    {
        $mahasiswa = $request->user();
        $mahasiswa->notifikasis()->delete(); // Hapus semua notifikasi

        return response()->json([
            'status' => 'success',
            'message' => 'Semua notifikasi telah dihapus.'
        ], 200);
    }
}
