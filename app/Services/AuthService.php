<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    public function login(string $username, string $password): array
    {
        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new Exception("Username atau password salah.", 401);
        }

        // Generate Standard RFC 7519 JWT Token
        $token = $this->jwtService->createToken($user);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role ?? 'admin',
            ],
        ];
    }

    public function logout(User $user): void
    {
        // Stateless JWT logout - frontend clears the stored token
        if (method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }
    }
}
