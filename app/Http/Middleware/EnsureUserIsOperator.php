<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cek jika user login DAN jabatannya 'operator'
        if ($request->user() && $request->user()->jabatan == 'operator') {
            return $next($request);
        }

        // Jika tidak, tolak akses
        return response()->json([
            'status' => 'error',
            'message' => 'Akses ditolak. Hanya operator yang dapat melakukan tindakan ini.'
        ], 403); // 403 Forbidden
    }
}
