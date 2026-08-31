<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSemphony
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.semphony.api_token');
        $provided = $request->bearerToken();

        if (! is_string($configured) || $configured === '' || ! is_string($provided) || ! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
