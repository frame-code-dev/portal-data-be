<?php

namespace App\Services;

use App\Models\User;
use Exception;

/**
 * Standard RFC 7519 JSON Web Token (JWT) Service
 * Supports HMAC-SHA256 (HS256) signature verification and timing-safe claims validation.
 */
class JwtService
{
    private string $secretKey;

    public function __construct()
    {
        $rawKey = (string) (env('JWT_SECRET') ?: config('app.key'));
        
        if (str_starts_with($rawKey, 'base64:')) {
            $rawKey = base64_decode(substr($rawKey, 7)) ?: $rawKey;
        }

        $this->secretKey = $rawKey;
    }

    /**
     * Generate an RFC 7519 compliant JWT token for a user.
     */
    public function createToken(User $user, ?int $ttl = null): string
    {
        $ttl = $ttl ?? (int) env('JWT_TTL', 86400); // Default 24 hours

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $now = time();
        $payload = [
            'iss' => (string) config('app.url', 'http://localhost'),
            'sub' => (string) $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role ?? 'admin',
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secretKey, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    /**
     * Validate and decode an RFC 7519 JWT token.
     * Returns the payload array if valid, or null if invalid / expired.
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // 1. Verify Header
        $headerJson = $this->base64UrlDecode($headerEncoded);
        if (!$headerJson) {
            return null;
        }
        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
            return null;
        }

        // 2. Verify Signature (Timing-safe comparison)
        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secretKey, true);
        $actualSignature = $this->base64UrlDecode($signatureEncoded);
        if (!$actualSignature || !hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        // 3. Verify Payload
        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        if (!$payloadJson) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        // 4. Validate Expiration (exp) claim
        if (isset($payload['exp']) && time() > (int) $payload['exp']) {
            return null; // Expired token
        }

        // 5. Validate Not Before (nbf) claim if present
        if (isset($payload['nbf']) && time() < (int) $payload['nbf']) {
            return null;
        }

        return $payload;
    }

    /**
     * URL-safe Base64 encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe Base64 decode
     */
    private function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded !== false ? $decoded : null;
    }
}
