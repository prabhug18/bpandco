<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ExternalApiToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalApi
{
    /**
     * Handle an incoming request for Third-Party External API endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing or invalid Authorization Bearer token header.'
            ], 401);
        }

        $plainToken = trim(substr($header, 7));
        $tokenModel = ExternalApiToken::findToken($plainToken);

        if (!$tokenModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or revoked API token.'
            ], 401);
        }

        // Update last used timestamp
        $tokenModel->update(['last_used_at' => now()]);

        // Attach token object to request for downstream controller usage
        $request->attributes->set('external_api_token', $tokenModel);

        return $next($request);
    }
}
