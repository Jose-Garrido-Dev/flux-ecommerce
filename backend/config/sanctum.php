<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    // La tienda no usa cookies de sesión de Sanctum: el servidor Next.js envía el token del cliente.
    // Sin guardias "stateful", una sesión del panel nunca autentica la API de la tienda.
    'stateful' => [],

    'guard' => [],

    // Vencimiento de los tokens de clientes, en minutos (30 días)
    'expiration' => 60 * 24 * 30,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'flux_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
