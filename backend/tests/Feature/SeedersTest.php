<?php

namespace Tests\Feature;

use App\Domain\Shipping\Models\Region;
use App\Domain\System\Models\ModuleStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_data_is_complete_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(16, Region::count());
        $this->assertSame('CL-AP', Region::orderBy('position')->first()->code);
        $this->assertSame('CL-MA', Region::orderByDesc('position')->first()->code);
        $this->assertSame(9, Role::count());
        $this->assertSame(34, ModuleStatus::count());
    }

    public function test_roles_get_expected_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $finance = Role::findByName('finance');
        $this->assertTrue($finance->hasPermissionTo('refunds.process'));
        $this->assertFalse($finance->hasPermissionTo('products.update'));

        $storeAdmin = Role::findByName('store-admin');
        $this->assertTrue($storeAdmin->hasPermissionTo('settings.manage'));
        $this->assertFalse($storeAdmin->hasPermissionTo('system.update'));
        $this->assertFalse($storeAdmin->hasPermissionTo('products.delete'));
    }
}
