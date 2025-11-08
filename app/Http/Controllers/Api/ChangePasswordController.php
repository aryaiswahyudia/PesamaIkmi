<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

class ChangePasswordController extends Controller
{
    /**
     * Mengganti password pengguna yang sedang login.
     * Dapat digunakan oleh Mahasiswa dan Admin/Operator.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user(); // Ini bisa Mahasiswa atau User (Admin/Op)

        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed', // 'confirmed' memastikan field_confirmation cocok
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            // 2. Verifikasi Password Saat Ini
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini salah.'
                ], 403); // 403 Forbidden
            }

            // 3. Simpan Password Baru
            $user->password = Hash::make($request->new_password);
            $user->save();

            // 4. Kirim Respon Sukses
            Log::info('Password berhasil diganti untuk ID: ' . ($user->id_mahasiswa ?? $user->id_user));

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diubah.'
            ], 200);

        } catch (Exception $e) {
            $id = $user->id_mahasiswa ?? $user->id_user;
            Log::error("[Change Password Failed] ID $id: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengubah password. Terjadi kesalahan internal.'
            ], 500);
        }
    }
    
}
