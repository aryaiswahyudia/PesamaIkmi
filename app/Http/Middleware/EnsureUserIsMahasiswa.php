<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Mahasiswa; // <-- Import model Mahasiswa

class EnsureUserIsMahasiswa
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // $request->user() akan berisi model yang login (bisa User atau Mahasiswa)
        // Kita cek apakah model tersebut adalah instance dari Mahasiswa
        if ($request->user() && $request->user() instanceof Mahasiswa) {
            return $next($request); // Lanjutkan jika benar
        }

        // Jika bukan, beri respon error 403 (Forbidden / Dilarang)
        return response()->json([
            'status' => 'error',
            'message' => 'Akses ditolak. Hanya untuk mahasiswa.'
        ], 403);
    }
}
