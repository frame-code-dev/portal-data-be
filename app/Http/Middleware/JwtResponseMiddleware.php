<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtResponseMiddleware
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    /**
     * Intercept and encode JSON API response data into an RFC 7519 JWT string.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process successful JsonResponses
        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            $original = $response->getData(true);

            if (is_array($original) && ($original['success'] ?? false) === true) {
                // If it's auth/login and token is already a top-level string in data, skip double wrapping
                if (isset($original['data']['token']) && is_string($original['data']['token'])) {
                    return $response;
                }

                $payloadToEncode = [
                    'data' => $original['data'] ?? null,
                    'dataset' => $original['dataset'] ?? null,
                    'pagination' => $original['pagination'] ?? null,
                    'summary' => $original['summary'] ?? null,
                ];

                $jwtString = $this->jwtService->encodePayload($payloadToEncode);

                $response->setData([
                    'success' => true,
                    'message' => $original['message'] ?? 'OK',
                    'data' => $jwtString,
                ]);
            }
        }

        return $response;
    }
}
