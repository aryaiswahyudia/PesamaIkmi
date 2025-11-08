<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notifikasi; // <-- Impor model Notifikasi

class NotifikasiController extends Controller
{
    /**
     * Menampilkan daftar notifikasi untuk admin yang sedang login.
     * Dibuat paginasi, 20 notifikasi per halaman.
     */
    public function index(Request $request)
    {
        $admin = $request->user(); // Dapatkan admin yang sedang login

        $notifikasis = $admin->notifikasis() // Ambil notifikasi milik admin tsb
                            ->orderBy('created_at', 'desc') // Urutkan dari yang terbaru
                            ->paginate(20); // Paginasi

        return response()->json($notifikasis);
    }

    /**
     * Menandai satu notifikasi sebagai "dibaca".
     * Ini dipanggil saat admin menekan notifikasi.
     */
    public function markAsRead(Request $request, $id_notifikasi)
    {
        $admin = $request->user();

        // Cari notifikasi, pastikan milik admin ini, lalu update
        $notifikasi = $admin->notifikasis()->findOrFail($id_notifikasi);

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

    /**
     * Menghapus satu notifikasi.
     */
    public function destroy(Request $request, $id_notifikasi)
    {
        $admin = $request->user();

        // Cari notifikasi, pastikan milik admin ini, lalu hapus
        $notifikasi = $admin->notifikasis()->findOrFail($id_notifikasi);
        $notifikasi->delete();

        // 204 No Content adalah respons standar untuk delete sukses
        return response()->json(null, 204);
    }

    /**
     * Menghapus SEMUA notifikasi untuk admin yang sedang login.
     */
    public function clearAll(Request $request)
    {
        $admin = $request->user();
        $admin->notifikasis()->delete(); // Hapus semua notifikasi

        return response()->json([
            'status' => 'success',
            'message' => 'Semua notifikasi telah dihapus.'
        ], 200);
    }
}
