<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User; // <-- Import model User

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cek apakah dia model User
        if ($request->user() && $request->user() instanceof User) {

            // Tambahan: Cek jabatannya (lebih aman)
            $jabatan = $request->user()->jabatan;
            if ($jabatan == 'administrator' || $jabatan == 'operator') {
                return $next($request); // Lanjutkan jika benar
            }
        }

        // Jika bukan, beri respon error 403
        return response()->json([
            'status' => 'error',
            'message' => 'Akses ditolak. Hanya untuk administrator/operator.'
        ], 403);
    }
}
