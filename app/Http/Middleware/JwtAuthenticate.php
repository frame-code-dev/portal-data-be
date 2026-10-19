<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token otentikasi tidak ditemukan.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->jwtService->validateToken($token);

        if (!$payload || !isset($payload['sub'])) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau telah kedaluwarsa.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::find($payload['sub']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna untuk token ini tidak ditemukan.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Attach user to current request and auth guard
        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);

        return $next($request);
    }
}
