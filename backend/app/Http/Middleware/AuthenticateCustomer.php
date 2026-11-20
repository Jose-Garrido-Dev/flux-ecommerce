<?php

namespace App\Http\Middleware;

use App\Domain\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Rutas de "Mi cuenta": exige un token vigente de un cliente activo. */
class AuthenticateCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        $customer = $token?->tokenable;
        if (! $customer instanceof Customer || $customer->status !== 'active' || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json([
                'type' => 'about:blank', 'title' => 'No autenticado', 'status' => 401,
                'code' => 'unauthenticated', 'detail' => 'Tu sesión venció. Ingresa de nuevo.',
            ], 401, ['Content-Type' => 'application/problem+json']);
        }
        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $customer);

        return $next($request);
    }
}
