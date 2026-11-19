<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function userWithRole(?string $role, string $status = 'active'): User
    {
        $user = User::factory()->create(['status' => $status]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    public static function adminPages(): array
    {
        return [
            ['/admin'], ['/admin/users'], ['/admin/module-statuses'], ['/admin/audit-logs'],
            ['/admin/regions'], ['/admin/business-settings'],
            ['/admin/products'], ['/admin/products/create'], ['/admin/categories'], ['/admin/categories/create'],
            ['/admin/brands'], ['/admin/attributes'], ['/admin/attributes/create'], ['/admin/import-products'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_super_admin_can_open_every_page(string $url): void
    {
        $this->actingAs($this->userWithRole('super-admin'))->get($url)->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_user_without_role_cannot_enter_panel(): void
    {
        $this->actingAs($this->userWithRole(null))->get('/admin')->assertForbidden();
    }

    public function test_disabled_user_cannot_enter_panel(): void
    {
        $this->actingAs($this->userWithRole('store-admin', 'disabled'))->get('/admin')->assertForbidden();
    }

    public function test_permissions_limit_pages_by_role(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)->get('/admin')->assertOk();
        $this->actingAs($sales)->get('/admin/users')->assertForbidden();
        $this->actingAs($sales)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($sales)->get('/admin/business-settings')->assertForbidden();
    }

    public function test_store_admin_manages_business_but_not_system(): void
    {
        $admin = $this->userWithRole('store-admin');

        $this->actingAs($admin)->get('/admin/business-settings')->assertOk();
        $this->actingAs($admin)->get('/admin/module-statuses')->assertForbidden();
    }
}
