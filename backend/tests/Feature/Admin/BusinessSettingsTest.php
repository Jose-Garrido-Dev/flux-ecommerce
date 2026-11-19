<?php

namespace Tests\Feature\Admin;

use App\Domain\System\Models\AuditLog;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Pages\BusinessSettings;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('store-admin');
        $this->actingAs($this->admin);
    }

    public function test_saves_business_data_with_formatted_rut_and_audit(): void
    {
        Livewire::test(BusinessSettings::class)
            ->fillForm([
                'store_name' => 'Tienda Flux',
                'rut' => '123456785',
                'region_code' => 'CL-RM',
                'contact_email' => 'hola@tienda.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsRepository::class);
        $this->assertSame('Tienda Flux', $settings->get('business', 'store_name'));
        $this->assertSame('12.345.678-5', $settings->get('business', 'rut'));

        $this->assertTrue(AuditLog::query()
            ->where('action', 'Setting.created')
            ->where('actor_id', $this->admin->id)
            ->exists());
    }

    public function test_rejects_invalid_rut(): void
    {
        Livewire::test(BusinessSettings::class)
            ->fillForm(['store_name' => 'Tienda', 'rut' => '12.345.678-9'])
            ->call('save')
            ->assertHasFormErrors(['rut']);
    }

    public function test_store_name_is_required(): void
    {
        Livewire::test(BusinessSettings::class)
            ->fillForm(['store_name' => ''])
            ->call('save')
            ->assertHasFormErrors(['store_name' => 'required']);
    }
}
