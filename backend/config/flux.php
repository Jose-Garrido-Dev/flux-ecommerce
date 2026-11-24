<?php

return [
    // Versión del núcleo Flux (se actualiza en cada release)
    'version' => '0.1.0',

    // Zona horaria para mostrar fechas (la base guarda UTC)
    'display_timezone' => env('FLUX_DISPLAY_TIMEZONE', 'America/Santiago'),

    'storefront_url' => env('FLUX_STOREFRONT_URL', 'http://localhost:3000'),

    // Dirección del storefront desde el servidor (para avisarle que refresque su caché). En Docker: http://storefront:3000
    'storefront_internal_url' => env('FLUX_STOREFRONT_INTERNAL_URL', env('FLUX_STOREFRONT_URL', 'http://localhost:3000')),

    // Secreto compartido con Next.js para revalidar páginas
    'revalidate_secret' => env('FLUX_REVALIDATE_SECRET'),

    // Solicitudes por minuto por IP a la API pública
    'storefront_rate_limit' => (int) env('FLUX_STOREFRONT_RATE_LIMIT', 120),
];
