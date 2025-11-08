<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');
        $tokenString = $header ? str_replace('Bearer ', '', $header) : null;

        if ($tokenString) {
            $token = PersonalAccessToken::findToken($tokenString);

            if ($token && $token->expires_at && now()->greaterThan($token->expires_at)) {
                $userId = $token->tokenable_id ?? 'unknown';

                Log::info("🧹 Token expired untuk user ID: $userId. Menghapus semua token user ini.");

                // Hapus semua token milik user yang sudah expired
                $deleted = DB::table('personal_access_tokens')
                    ->where('tokenable_id', $userId)
                    ->delete();

                Log::info("✅ $deleted token dihapus untuk user ID: $userId.");

                return response()->json([
                    'status' => 'error',
                    'message' => 'Token sudah kadaluarsa. Silakan login ulang.'
                ], 401);
            }
        }

        return $next($request);
    }
}
