<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UserProfileController extends Controller
{
    /**
     * ✅ Update atau hapus FCM token untuk user yang sedang login.
     *
     * Mendukung dua mode:
     * 1. Kirim { "fcm_token": "<token>" } → update token.
     * 2. Kirim { "fcm_token_null": true } → hapus token (biasanya saat logout).
     */
    public function updateFcmToken(Request $request)
    {
        $user = $request->user();

        // 🧠 Mode 1: Logout / hapus token FCM
        if ($request->boolean('fcm_token_null')) {
            try {
                $user->fcm_token = null;
                $user->save();

                Log::info("✅ FCM token dihapus (logout) untuk User ID: {$user->id_user}");

                return response()->json([
                    'status' => 'success',
                    'message' => 'FCM token cleared (logout).',
                ], 200);
            } catch (\Exception $e) {
                Log::error("❌ Gagal hapus FCM token (logout) untuk User ID {$user->id_user}: " . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to clear FCM token.',
                ], 500);
            }
        }

        // 🧩 Mode 2: Update token FCM baru
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
            // 🧹 Bersihkan token duplikat dari user lain
            User::where('fcm_token', $token)
                ->where('id_user', '!=', $user->id_user)
                ->update(['fcm_token' => null]);

            // 🔄 Simpan token baru untuk user ini
            $user->fcm_token = $token;
            $user->save();

            Log::info("✅ FCM Token diperbarui untuk User ID: {$user->id_user}");

            return response()->json([
                'status' => 'success',
                'message' => 'FCM token updated.',
            ], 200);

        } catch (\Exception $e) {
            Log::error("❌ Gagal update FCM token untuk User ID {$user->id_user}: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update FCM token.',
            ], 500);
        }
    }

    /**
     * (Opsional) Contoh fungsi profil jika kamu ingin menambahkan.
     */
    public function profile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user(),
        ]);
    }

        public function updateProfile(Request $request)
    {
        $user = $request->user(); // $user adalah model User (Admin/Op)

        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users')->ignore($user->id_user, 'id_user')
            ],
            'email' => [
                'required', 'string', 'email', 'max:100',
                Rule::unique('users')->ignore($user->id_user, 'id_user')
            ],
            'no_telepon' => 'required|string|max:20',

            // Nama input dari Flutter adalah 'foto'
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            // Ambil data teks
            $dataToUpdate = $request->only([
                'nama', 'username', 'email', 'no_telepon',
            ]);

            // Cek jika email diganti (dan set otomatis verifikasi)
            if ($user->email != $request->email) {
                $dataToUpdate['email_verified_at'] = now();
            }

            // 3. Handle Upload Foto (jika ada)
            if ($request->hasFile('foto')) {

                // Hapus foto lama (baca dari kolom 'foto_profile')
                if ($user->foto_profile && $user->getRawOriginal('foto_profile') != 'default_user.png') {
                    Storage::disk('public')->delete($user->getRawOriginal('foto_profile'));
                }

                // Simpan foto baru
                $path = $request->file('foto')->store('admin_fotos', 'public');

                // Simpan ke kolom 'foto_profile'
                $dataToUpdate['foto_profile'] = $path;
            }
            // =======================================================

            // 4. Simpan semua perubahan
            $user->update($dataToUpdate);

            // 'fresh()' mengambil data baru dari DB (termasuk URL foto yang baru)
            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui.',
                'data' => $user->fresh()
            ], 200);

        } catch (Exception $e) {
            Log::error('Gagal update profil admin: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui profil.'], 500);
        }
    }
}
