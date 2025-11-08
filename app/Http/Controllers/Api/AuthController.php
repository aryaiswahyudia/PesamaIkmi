<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MahasiswaVerificationNotification;
use App\Notifications\ForgotPasswordNotification;
use App\Models\PasswordResetToken;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Exception;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Data yang dikirim tidak valid.', 'errors' => $validator->errors()], 422);
        }

        try {
            $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            // =======================================================
            // Prioritas 1: Cek di tabel Mahasiswa - TIDAK BERUBAH
            // =======================================================
            $mahasiswa = Mahasiswa::where($loginField, $request->login)->first();

            // Jika user ditemukan sebagai mahasiswa
            if ($mahasiswa) {
                // Cek password. Jika salah, langsung gagalkan. - TIDAK BERUBAH
                if (!Hash::check($request->password, $mahasiswa->password)) {
                    return $this->sendFailedLoginResponse();
                }
                if (is_null($mahasiswa->email_verified_at)) {
                    // Jika NULL, kirim error 403 (Akses Ditolak)
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Akun Anda belum diverifikasi. Silakan cek email Anda atau daftar ulang.'
                    ], 403);
                }
                return $this->sendSuccessLoginResponse($mahasiswa, 'mahasiswa');
            }

            // =======================================================
            // Prioritas 2: Cek di tabel Users (Admin/Operator) - TIDAK BERUBAH
            // =======================================================
            $admin = User::where($loginField, $request->login)->first();

            // Jika user ditemukan sebagai admin
            if ($admin) {
                // (Logika pengecekan password dan role admin TIDAK BERUBAH)
                if (!Hash::check($request->password, $admin->password)) {
                    return $this->sendFailedLoginResponse();
                }
                if (!in_array($admin->jabatan, ['administrator', 'operator'])) {
                    return response()->json(['status' => 'error', 'message' => 'Akun ini tidak memiliki hak akses.'], 403);
                }
                // Login sebagai admin berhasil - TIDAK BERUBAH
                return $this->sendSuccessLoginResponse($admin, 'admin');
            }

            // Jika tidak ditemukan di kedua tabel - TIDAK BERUBAH
            return $this->sendFailedLoginResponse();

        } catch (Exception $e) {
            // Error server - TIDAK BERUBAH
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * Helper untuk membuat response login sukses.
     */
    protected function sendSuccessLoginResponse($user, string $userType)
    {
        $tokenName = 'auth-token-' . $userType . '-' . $user->username;

        // 1️⃣ Buat token seperti biasa
        $tokenResult = $user->createToken($tokenName, ['*']);
        $plainTextToken = $tokenResult->plainTextToken;

        // 2️⃣ Atur kadaluarsa manual → 6 bulan dari sekarang
        $tokenResult->accessToken->forceFill([
            'expires_at' => now()->addMonths(6), // ✅ Berlaku 6 bulan
        ])->save();

        // 3️⃣ Kirim response ke frontend
        return response()->json([
            'status'     => 'success',
            'message'    => 'Login berhasil',
            'token'      => $plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at, // biar FE tahu tanggal kadaluarsa
            'user_type'  => $userType,
            'user'       => $user,
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan.'], 401);
        }

        // 🔥 Hapus FCM token baik untuk Admin/Operator maupun Mahasiswa
        if ($user instanceof \App\Models\User || $user instanceof \App\Models\Mahasiswa) {
            $user->fcm_token = null;
            $user->save();
        }

        // 🔐 Hapus token akses aktif
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil. Token dihapus.',
        ], 200);
    }


    protected function sendFailedLoginResponse()
    {
        return response()->json(['status'  => 'error', 'message' => 'Username Dan Password Salah'], 401);
    }

    /**
     * Menangani permintaan registrasi khusus untuk mahasiswa.
     */
    public function registerMahasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'nim' => 'required|string|max:50|unique:mahasiswas,nim',
            'username' => ['required', 'string', 'max:100', 'unique:mahasiswas,username', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:mahasiswas,email', 'unique:users,email'],
            'password' => 'required|string|min:8|confirmed',
            'no_telepon' => 'required|string|max:20',
            'alamat' => 'required|string|max:500',
            'angkatan' => 'required|string|max:50',
            'prodi' => 'required|string|max:100',
            'kelas' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Data yang dikirim tidak valid.', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($request) {
                $mahasiswa = Mahasiswa::create([
                    'nama' => $request->nama,
                    'nim' => $request->nim,
                    'username' => $request->username,
                    'email' => $request->email,
                    'password' => $request->password,
                    'no_telepon' => $request->no_telepon,
                    'alamat' => $request->alamat,
                    'angkatan' => $request->angkatan,
                    'prodi' => $request->prodi,
                    'kelas' => $request->kelas,
                ]);

                $token = random_int(100000, 999999);

                PasswordResetToken::updateOrInsert(
                    ['email' => $mahasiswa->email],
                    ['token' => $token, 'created_at' => now()]
                );

                Notification::send($mahasiswa, new MahasiswaVerificationNotification($token));
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Registrasi berhasil. Silakan cek email Anda untuk kode verifikasi.'
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registrasi gagal. Terjadi kesalahan pada server.',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyMahasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:mahasiswas,email',
            'token' => 'required|string|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $tokenRecord = PasswordResetToken::where('email', $request->email)->where('token', $request->token)->first();

        if (!$tokenRecord) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid.'], 400);
        }

        if (now()->diffInMinutes($tokenRecord->created_at) > 5) {
            $tokenRecord->delete();
            return response()->json(['status' => 'error', 'message' => 'Token kedaluwarsa. Silakan minta token baru.'], 400);
        }

        $mahasiswa = Mahasiswa::where('email', $request->email)->first();
        if ($mahasiswa->email_verified_at) {
             return response()->json(['status' => 'info', 'message' => 'Akun ini sudah terverifikasi.'], 200);
        }

        $mahasiswa->email_verified_at = now();
        $mahasiswa->save();
        $tokenRecord->delete();

        return $this->sendSuccessLoginResponse($mahasiswa, 'mahasiswa');
    }

    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:mahasiswas,email', // Pastikan email terdaftar
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Cari mahasiswa berdasarkan email
        $mahasiswa = Mahasiswa::where('email', $request->email)->first();

        // Penting: Hanya kirim ulang jika akun BELUM terverifikasi
        if ($mahasiswa && !is_null($mahasiswa->email_verified_at)) {
            return response()->json([
                'status' => 'info',
                'message' => 'Akun ini sudah terverifikasi. Silakan login.'
            ], 400); // Bad request karena akun sudah aktif
        }

        // Jika user ditemukan dan belum terverifikasi
        if ($mahasiswa) {
            try {
                // Buat token 6 digit BARU (sama seperti di register)
                $token = random_int(100000, 999999);

                // Simpan/Update token di tabel password_reset_tokens
                PasswordResetToken::updateOrInsert(
                    ['email' => $mahasiswa->email],
                    ['token' => $token, 'created_at' => now()]
                );

                // Kirim email notifikasi VERIFIKASI (bukan forgot password)
                Notification::send($mahasiswa, new MahasiswaVerificationNotification($token));

                return response()->json([
                    'status' => 'success',
                    'message' => 'Kode verifikasi baru telah dikirim ke email Anda.'
                ], 200);

            } catch (Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengirim ulang email. Silakan coba lagi nanti.',
                    'error_details' => $e->getMessage() // Hapus di production
                ], 500);
            }
        } else {
            // Seharusnya tidak terjadi karena ada validasi 'exists', tapi sebagai pengaman
            return response()->json([
                'status' => 'error',
                'message' => 'Email tidak ditemukan.'
            ], 404);
        }
    }

    public function forgotPassword(Request $request)
    {
        // 1. Validasi input email
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 2. Cari user di kedua tabel (Mahasiswa atau Admin/User)
        $user = Mahasiswa::where('email', $request->email)->first();
        if (!$user) {
            $user = User::where('email', $request->email)->first();
        }

        // 3. Jika email tidak terdaftar sama sekali
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email tidak terdaftar di sistem kami.'
            ], 404);
        }

        // 4. Buat token 6 digit dan kirim email
        try {
            $token = random_int(100000, 999999);

            // Simpan/Update token di database
            PasswordResetToken::updateOrInsert(
                ['email' => $user->email], // Kunci pencarian
                ['token' => $token, 'created_at' => now()] // Data yang diupdate/dibuat
            );

            // Kirim email notifikasi
            Notification::send($user, new ForgotPasswordNotification($token));

            return response()->json([
                'status' => 'success',
                'message' => 'Kode reset password telah dikirim ke email Anda.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim email. Silakan coba lagi nanti.',
                'error_details' => $e->getMessage() // Hapus ini di production
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        // 1. Validasi input (termasuk konfirmasi password)
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'token' => 'required|string|digits:6',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' akan otomatis cek 'password_confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 2. Verifikasi token
        $tokenRecord = PasswordResetToken::where('email', $request->email)
                                         ->where('token', $request->token)
                                         ->first();

        // 3. Cek jika token salah
        if (!$tokenRecord) {
            return response()->json(['status' => 'error', 'message' => 'Token tidak valid.'], 400);
        }

        // 4. Cek jika token kedaluwarsa (15 menit)
        if (now()->diffInMinutes($tokenRecord->created_at) > 5) {
            $tokenRecord->delete(); // Hapus token kedaluwarsa
            return response()->json(['status' => 'error', 'message' => 'Token kedaluwarsa.'], 400);
        }

        // 5. Cari user yang akan di-reset (Mahasiswa atau Admin/User)
        $user = Mahasiswa::where('email', $request->email)->first();
        if (!$user) {
            $user = User::where('email', $request->email)->first();
        }

        // 6. Jika user tidak ditemukan (seharusnya tidak mungkin jika token ada, tapi sebagai pengaman)
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Email tidak terdaftar.'], 404);
        }

        // 7. Update password dan hapus token dalam satu transaksi aman
        try {
            DB::transaction(function () use ($user, $request, $tokenRecord) {
                // Update password (Model akan auto-hash karena $casts)
                $user->password = $request->password;
                $user->save();

                // Hapus token yang sudah dipakai
                $tokenRecord->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Password Anda telah berhasil direset. Silakan login.'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mereset password. Terjadi kesalahan server.',
                'error_details' => $e->getMessage() // Hapus di production
            ], 500);
        }
    }
}
