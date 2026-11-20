<?php

namespace Tests\Feature\Admin;

use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

class OrdersAdminTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function order(int $quantity = 2): Order
    {
        $this->stockedProduct('Taza', 10000, 5);
        $number = $this->checkout($this->addToCart('TAZA-0', $quantity)->json('token'), $this->checkoutData(), uniqid())->json('data.number');

        return Order::query()->where('number', $number)->firstOrFail();
    }

    public function test_finance_confirms_the_transfer_from_the_order(): void
    {
        $order = $this->order();
        $this->actingAs($this->user('finance'));
        $this->get('/admin/orders')->assertOk()->assertSee($order->number);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Av. Providencia')->assertSee('Pendiente de pago');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('confirmPayment', data: ['amount' => 100, 'reference' => ''])
            ->assertHasActionErrors(['amount']);
        $this->assertSame('pending', $order->fresh()->payment_status);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('confirmPayment', data: ['amount' => $order->grand_total, 'reference' => 'OP-1'])
            ->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame(3, InventoryLevel::query()->sole()->on_hand);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('confirmPayment')->assertActionHidden('cancel');
    }

    public function test_sales_can_cancel_but_not_confirm_payments(): void
    {
        $order = $this->order();
        $this->actingAs($this->user('sales'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('confirmPayment')
            ->callAction('cancel', data: ['reason' => 'El cliente desistió'])
            ->assertHasNoActionErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, InventoryLevel::query()->sole()->reserved);

        Livewire::test(ListOrders::class)->filterTable('status', 'cancelled')->assertCanSeeTableRecords([$order]);
    }

    public function test_roles_without_orders_cannot_open_sales_screens(): void
    {
        $order = $this->order();
        $this->actingAs($this->user('catalog-manager'));
        foreach (['/admin/orders', '/admin/orders/'.$order->id, '/admin/customers', '/admin/shipping-methods', '/admin/payment-settings'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_customer_block_closes_sessions(): void
    {
        $token = $this->flushHeaders()->postJson(self::API.'/auth/register', [
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl',
            'password' => 'clave1234', 'password_confirmation' => 'clave1234', 'accepts_terms' => true,
        ])->json('data.token');
        $customer = Customer::query()->sole();
        $this->actingAs($this->user('store-admin'));
        $this->get('/admin/customers')->assertOk()->assertSee('ana@correo.cl');
        $this->get('/admin/customers/'.$customer->id)->assertOk();

        Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])->callAction('block');
        $this->assertSame('blocked', $customer->fresh()->status);
        $this->flushHeaders()->withHeaders(['Authorization' => "Bearer $token"])->getJson(self::API.'/me')->assertUnauthorized();
        $this->flushHeaders()->postJson(self::API.'/auth/login', ['email' => 'ana@correo.cl', 'password' => 'clave1234'])->assertUnprocessable();
    }

    public function test_shipping_methods_and_payment_settings(): void
    {
        $this->actingAs($this->user('store-admin'));
        Livewire::test(ManageShippingMethods::class)
            ->callAction('create', data: ['name' => 'Despacho RM', 'code' => 'RM', 'type' => 'flat', 'status' => 'active', 'amount' => 2990, 'region_ids' => [], 'position' => 1])
            ->assertHasNoActionErrors();
        $this->assertSame(2990, ShippingMethod::query()->where('code', 'rm')->value('amount'));

        $used = ShippingMethod::query()->where('code', 'domicilio')->firstOrFail();
        $this->order();
        $this->actingAs($this->user('super-admin'));
        Livewire::test(ManageShippingMethods::class)->callTableAction(DeleteAction::class, $used);
        $this->assertModelExists($used);

        Livewire::test(PaymentSettingsPage::class)
            ->fillForm(['bank_transfer_enabled' => true, 'account_rut' => '11.111.111-2'])
            ->call('save')->assertHasFormErrors(['account_rut']);
        Livewire::test(PaymentSettingsPage::class)
            ->fillForm(['bank_transfer_enabled' => true, 'bank_name' => 'Banco Estado', 'account_type' => 'Cuenta vista', 'account_number' => '999',
                'account_holder' => 'Mi Tienda SpA', 'account_rut' => '76.123.456-0', 'bank_transfer_hours' => 24])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Banco Estado', app(SettingsRepository::class)->get('payments', 'bank_name'));
        $this->assertSame(24, app(SettingsRepository::class)->get('payments', 'bank_transfer_hours'));
    }
}
