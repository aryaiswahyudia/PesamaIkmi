<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PengaduanController;
use App\Http\Controllers\Api\SelectionDataController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\NotifikasiController; // Ini untuk Admin
use App\Http\Controllers\Api\AdminPengaduanController;
use App\Http\Controllers\Api\MahasiswaProfileController;
use App\Http\Controllers\Api\MahasiswaNotifikasiController;
use App\Http\Controllers\Api\ChangePasswordController;
use App\Http\Controllers\Api\MD\AdminMahasiswaController;
use App\Http\Controllers\Api\MD\AdminUserController;
use App\Http\Controllers\Api\MD\AdminJenisMasukanController;
use App\Http\Controllers\Api\MD\AdminJenisPengaduanController;
use App\Http\Controllers\Api\MD\AdminPihakTerkaitController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// == ROUTE PUBLIK ==
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'registerMahasiswa']);
Route::post('/register/verify', [AuthController::class, 'verifyMahasiswa']);
Route::post('/register/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::prefix('options')->group(function () {
    Route::get('/jenis-masukans', [SelectionDataController::class, 'getJenisMasukans']);
    Route::get('/jenis-pengaduans', [SelectionDataController::class, 'getJenisPengaduans']);
    Route::get('/pihak-terkait', [SelectionDataController::class, 'getPihakTerkait']);
});

// == ROUTE KHUSUS MAHASISWA ==
Route::middleware(['auth:sanctum', 'check.token.expiry', 'is.mahasiswa'])->prefix('mahasiswa')->group(function () {

    Route::get('/profile', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'user' => $request->user() // Sebaiknya 'data' => $request->user()
        ]);
    });

    Route::get('/dashboard', [PengaduanController::class, 'indexDashboard']);
    Route::get('/dashboard/chart', [PengaduanController::class, 'chart']);

    Route::post('/update-fcm-token', [MahasiswaProfileController::class, 'updateFcmToken']);
    Route::post('/update-profile', [MahasiswaProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword']);

    // --- Pengaduan ---
    Route::get('/pengaduan', [PengaduanController::class, 'index']);
    Route::get('/pengaduan/{id_pengaduan}', [PengaduanController::class, 'show']);
    Route::post('/pengaduan', [PengaduanController::class, 'store']);
    Route::post('/pengaduan/{id_pengaduan}', [PengaduanController::class, 'update']);
    Route::delete('/pengaduan/{id_pengaduan}', [PengaduanController::class, 'destroy']);

    // --- Diskusi ---
    Route::post('/pengaduan/{id_pengaduan}/diskusi', [PengaduanController::class, 'kirimDiskusi']);

    Route::get('/notifikasi', [MahasiswaNotifikasiController::class, 'index']);
    Route::delete('/notifikasi/clear-all', [MahasiswaNotifikasiController::class, 'clearAll']);
    Route::put('/notifikasi/{id_notifikasi}/read', [MahasiswaNotifikasiController::class, 'markAsRead']);
    Route::delete('/notifikasi/{id_notifikasi}', [MahasiswaNotifikasiController::class, 'destroy']);
});


// == ROUTE KHUSUS ADMIN / OPERATOR ==
Route::middleware(['auth:sanctum', 'check.token.expiry', 'is.admin'])->prefix('admin')->group(function () {
    //
    Route::get('/profile', function (Request $request) {
        return response()->json(['data' => $request->user()]);
    });

    //
    Route::get('/dashboard', [AdminPengaduanController::class, 'indexDashboard']);
    Route::get('/dashboard/chart', [AdminPengaduanController::class, 'chart']);

    //
    Route::post('/update-profile', [UserProfileController::class, 'updateProfile']);
    Route::post('/change-password', [ChangePasswordController::class, 'changePassword']);

    // --- Pengaduan ---
    Route::get('/pengaduan', [AdminPengaduanController::class, 'index']);
    Route::get('/pengaduan/{id_pengaduan}', [AdminPengaduanController::class, 'show']);
    Route::post('/pengaduan/{id_pengaduan}/tanggapan', [AdminPengaduanController::class, 'storeTanggapan'])->middleware('is.operator');
    Route::post('/pengaduan/{id_pengaduan}/diskusi', [AdminPengaduanController::class, 'kirimDiskusi']);

    Route::post('/update-fcm-token', [UserProfileController::class, 'updateFcmToken']);

    // --- Notifikasi (Khusus Admin) ---
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::delete('/notifikasi/clear-all', [NotifikasiController::class, 'clearAll']);
    Route::put('/notifikasi/{id_notifikasi}/read', [NotifikasiController::class, 'markAsRead']);
    Route::delete('/notifikasi/{id_notifikasi}', [NotifikasiController::class, 'destroy']);

    // --- Manajemen Mahasiswa ---
    Route::get('/mahasiswa', [AdminMahasiswaController::class, 'index']);
    Route::post('/mahasiswa', [AdminMahasiswaController::class, 'store']);
    Route::post('/mahasiswa/{id}', [AdminMahasiswaController::class, 'update']); // pakai POST untuk formdata
    Route::delete('/mahasiswa/{id}', [AdminMahasiswaController::class, 'destroy']);

    // --- Manajemen User(OP/Admin) ---
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::post('/users/{id_user}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id_user}', [AdminUserController::class, 'destroy']);

    // --- Manajemen Jenis Masukan ---
    Route::get('/jenis-masukan', [AdminJenisMasukanController::class, 'index']);
    Route::post('/jenis-masukan', [AdminJenisMasukanController::class, 'store']);
    Route::post('/jenis-masukan/{id}', [AdminJenisMasukanController::class, 'update']);
    Route::delete('/jenis-masukan/{id}', [AdminJenisMasukanController::class, 'destroy']);

    // --- Manajemen Jenis Pengaduan ---
    Route::get('/jenis-pengaduan', [AdminJenisPengaduanController::class, 'index']);
    Route::post('/jenis-pengaduan', [AdminJenisPengaduanController::class, 'store']);
    Route::post('/jenis-pengaduan/{id}', [AdminJenisPengaduanController::class, 'update']);
    Route::delete('/jenis-pengaduan/{id}', [AdminJenisPengaduanController::class, 'destroy']);

    // --- Manajemen Pihak Terkait---
    Route::get('/pihak-terkait', [AdminPihakTerkaitController::class, 'index']);
    Route::post('/pihak-terkait', [AdminPihakTerkaitController::class, 'store']);
    Route::post('/pihak-terkait/{id}', [AdminPihakTerkaitController::class, 'update']); // seperti mahasiswa
    Route::delete('/pihak-terkait/{id}', [AdminPihakTerkaitController::class, 'destroy']);
});

// Contoh route untuk logout (bisa diakses keduanya)
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

