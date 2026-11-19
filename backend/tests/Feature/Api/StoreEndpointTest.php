<?php

namespace Tests\Feature\Api;

use App\Domain\System\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_public_business_data_only(): void
    {
        app(SettingsRepository::class)->putMany('business', [
            'store_name' => 'Tienda Flux',
            'tagline' => 'Calidad en cada detalle',
            'announcement' => 'Despacho a todo Chile |  | Cuotas sin interés ',
            'instagram' => 'https://instagram.com/tienda',
            'contact_email' => 'hola@tienda.test',
            'rut' => '12.345.678-5',
            'legal_name' => 'Flux SpA',
        ]);

        $response = $this->getJson('/api/storefront/v1/store')
            ->assertOk()
            ->assertJsonPath('data.name', 'Tienda Flux')
            ->assertJsonPath('data.tagline', 'Calidad en cada detalle')
            ->assertJsonPath('data.currency', 'CLP')
            ->assertJsonPath('data.contact.email', 'hola@tienda.test')
            ->assertJsonPath('data.announcements', ['Despacho a todo Chile', 'Cuotas sin interés'])
            ->assertJsonPath('data.social.instagram', 'https://instagram.com/tienda');

        $this->assertStringNotContainsString('12.345.678-5', $response->getContent());
    }

    public function test_falls_back_to_app_name(): void
    {
        config(['app.name' => 'Mi Tienda']);

        $this->getJson('/api/storefront/v1/store')->assertOk()->assertJsonPath('data.name', 'Mi Tienda');
    }

    public function test_is_rate_limited(): void
    {
        config(['flux.storefront_rate_limit' => 2]);

        $this->getJson('/api/storefront/v1/store')->assertOk();
        $this->getJson('/api/storefront/v1/store')->assertOk();
        $this->getJson('/api/storefront/v1/store')->assertStatus(429);
    }
}
