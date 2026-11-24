<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Content\Services\ThemeService;
use App\Domain\Marketing\Services\WhatsAppSettings;
use App\Domain\System\Services\SettingsRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** Datos públicos de la tienda para el storefront. Nunca expone datos internos. */
class StoreController extends Controller
{
    public function show(SettingsRepository $settings, ThemeService $themes, WhatsAppSettings $whatsapp): JsonResponse
    {
        $business = $settings->group('business');

        return response()->json(['data' => [
            'name' => $business['store_name'] ?? config('app.name'),
            'tagline' => $business['tagline'] ?? null,
            'currency' => 'CLP',
            'locale' => 'es-CL',
            'announcements' => collect(explode('|', (string) ($business['announcement'] ?? '')))
                ->map(fn ($m) => trim($m))->filter()->values()->all(),
            'contact' => [
                'email' => $business['contact_email'] ?? null,
                'phone' => $business['contact_phone'] ?? null,
            ],
            'social' => collect(['instagram', 'facebook', 'tiktok'])
                ->mapWithKeys(fn ($k) => [$k => $business[$k] ?? null])
                ->filter()->all(),
            // Tema activo (Fase 9): la tienda lo aplica como variables CSS. null = tema base del storefront.
            'theme' => $themes->publicTheme(),
            // Botón de WhatsApp (Fase 10). null = desactivado
            'whatsapp' => $whatsapp->public(),
        ]]);
    }
}
