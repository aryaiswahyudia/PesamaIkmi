<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Exception;

class MahasiswaProfileController extends Controller
{
    public function updateFcmToken(Request $request)
    {
        $mahasiswa = $request->user();

        // 🧠 Mode Logout → hapus token FCM
        if ($request->boolean('fcm_token_null')) {
            try {
                $mahasiswa->fcm_token = null;
                $mahasiswa->save();

                Log::info("✅ FCM token dihapus (logout) untuk Mahasiswa ID: {$mahasiswa->id_mahasiswa}");

                return response()->json([
                    'status' => 'success',
                    'message' => 'FCM token cleared (logout).',
                ], 200);
            } catch (\Exception $e) {
                Log::error("❌ Gagal hapus FCM token (logout) untuk Mahasiswa ID {$mahasiswa->id_mahasiswa}: " . $e->getMessage());

                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to clear FCM token.',
                ], 500);
            }
        }

        // 🧩 Mode Login → update token baru
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = $request->fcm_token;

        try {
            // 🧹 Hapus token duplikat dari mahasiswa lain
            Mahasiswa::where('fcm_token', $token)
                ->where('id_mahasiswa', '!=', $mahasiswa->id_mahasiswa)
                ->update(['fcm_token' => null]);

            // 🔄 Simpan token baru
            $mahasiswa->fcm_token = $token;
            $mahasiswa->save();

            Log::info("✅ FCM Token diperbarui untuk Mahasiswa ID: {$mahasiswa->id_mahasiswa}");

            return response()->json([
                'status' => 'success',
                'message' => 'FCM token updated.',
            ], 200);

        } catch (\Exception $e) {
            Log::error("❌ Gagal update FCM token untuk Mahasiswa ID {$mahasiswa->id_mahasiswa}: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update FCM token.',
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => $request->user(),
        ]);
    }

        public function updateProfile(Request $request)
    {
        $mahasiswa = $request->user();

        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('mahasiswas')->ignore($mahasiswa->id_mahasiswa, 'id_mahasiswa')
            ],
            'email' => [
                'required', 'string', 'email', 'max:100',
                Rule::unique('mahasiswas')->ignore($mahasiswa->id_mahasiswa, 'id_mahasiswa')
            ],
            'no_telepon' => 'required|string|max:20',
            'alamat' => 'required|string',
            'angkatan' => 'required|string|max:50',
            'prodi' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $dataToUpdate = $request->only([
                'nama', 'username', 'email', 'no_telepon',
                'alamat', 'angkatan', 'prodi', 'kelas'
            ]);
            // 2. Cek jika email diganti
            if ($mahasiswa->email != $request->email) {
                $dataToUpdate['email_verified_at'] = now(); // <-- INI PERBAIKANNYA
            }

            // 3. Handle Upload Foto (jika ada)
            if ($request->hasFile('foto')) {
                if ($mahasiswa->foto && $mahasiswa->getRawOriginal('foto') != 'default_mahasiswa.png') {
                    Storage::disk('public')->delete($mahasiswa->getRawOriginal('foto'));
                }

                // Simpan foto baru
                $path = $request->file('foto')->store('foto_profil', 'public');
                $dataToUpdate['foto'] = $path;
            }

            // 4. Simpan semua perubahan
            $mahasiswa->update($dataToUpdate);

            // 'fresh()' mengambil data baru dari DB (termasuk URL foto yang baru)
            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui.',
                'data' => $mahasiswa->fresh()
            ], 200);

        } catch (Exception $e) {
            Log::error('Gagal update profil mahasiswa: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui profil.'], 500);
        }
    }
}
