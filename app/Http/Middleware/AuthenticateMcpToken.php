<?php

namespace App\Http\Middleware;

use App\Models\McpAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->query('token');

        if (! is_string($plainToken) || strlen($plainToken) !== 64 || ! ctype_alnum($plainToken)) {
            abort(401);
        }

        $accessToken = McpAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $accessToken instanceof McpAccessToken || ! hash_equals($accessToken->token_encrypted, $plainToken)) {
            abort(401);
        }

        Auth::guard()->setUser($accessToken->user);
        $accessToken->forceFill(['last_used_at' => now()])->save();

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
