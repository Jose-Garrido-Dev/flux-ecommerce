<?php

namespace Tests\Feature\Returns;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Payments\Models\Refund;
use App\Domain\Returns\Mail\ReturnMail;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

/** Fase 8: devoluciones y cambios de punta a punta, con plazo, cantidades, stock, reembolso y correos. */
class ReturnFlowTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private ReturnService $returns;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
        $this->returns = app(ReturnService::class);
    }

    /** Pedido pagado y entregado: 2 tazas ($10.000 c/u) y 1 polera ($12.000). */
    private function deliveredOrder(bool $deliver = true): Order
    {
        $this->stockedProduct('Taza', 10000, 5);
        $this->stockedProduct('Polera', 12000, 5);
        $token = $this->addToCart('TAZA-0', 2)->json('token');
        $this->addToCart('POLERA-0', 1, $token);
        $number = $this->checkout($token, $this->checkoutData())->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        app(OrderPayments::class)->confirm($order, $order->grand_total);
        if ($deliver) {
            $shipments = app(ShipmentService::class);
            $shipment = $shipments->create($order->fresh(), []);
            $shipments->updateStatus($shipment, 'shipped');
            $shipments->updateStatus($shipment, 'delivered');
        }

        return $order->fresh('items');
    }

    private function item(Order $order, string $sku): int
    {
        return $order->items->firstWhere('sku', $sku)->id;
    }

    private function stock(string $sku): int
    {
        return (int) app(InventoryService::class)->available(ProductVariant::query()->where('sku', $sku)->firstOrFail());
    }

    public function test_customer_requests_a_return_from_the_link_and_the_store_resolves_it_with_a_refund(): void
    {
        $order = $this->deliveredOrder();
        $this->assertSame('completed', $order->status);
        $token = OrderTokens::for($order->uuid);
        $taza = $this->item($order, 'TAZA-0');

        $policy = $this->getJson(self::API."/orders/$token")->assertOk()->json('data.return_policy');
        $this->assertTrue($policy['can_request']);
        $this->assertSame(10, $policy['days']);

        $response = $this->postJson(self::API."/orders/$token/returns", [
            'type' => 'return', 'reason' => 'defective', 'notes' => 'Llegó trizada', 'items' => [['id' => $taza, 'quantity' => 1]],
        ])->assertCreated();
        $this->assertSame('requested', $response->json('data.return.status'));
        $this->assertSame('Taza', $response->json('data.return.items.0.name'));
        $this->assertSame(1, collect($response->json('data.order.items'))->firstWhere('id', $taza)['returnable_quantity']);
        Mail::assertQueued(ReturnMail::class, fn (ReturnMail $m) => $m->kind === 'requested' && $m->hasTo('ana@correo.cl'));
        Mail::assertQueued(ReturnMail::class, fn (ReturnMail $m) => $m->kind === 'new_admin' && $m->hasTo('ventas@tienda.test'));

        // No se puede pedir más de lo que queda
        $this->postJson(self::API."/orders/$token/returns", ['type' => 'return', 'reason' => 'size', 'items' => [['id' => $taza, 'quantity' => 2]]])
            ->assertStatus(422)->assertJsonPath('errors.items.0', 'La cantidad supera lo que se puede devolver de ese producto.');
        // Ni un ítem de otro pedido
        $this->postJson(self::API."/orders/$token/returns", ['type' => 'return', 'reason' => 'size', 'items' => [['id' => 999999, 'quantity' => 1]]])->assertStatus(422);

        $return = ReturnRequest::query()->firstOrFail();
        $this->assertSame($order->number.'-D1', $return->label());

        // Rechazar o aprobar sin texto: 422
        $this->assertThrows(fn () => $this->returns->reject($return, ''), ValidationException::class, 'El rechazo necesita un motivo');
        $this->assertThrows(fn () => $this->returns->approve($return, ' '), ValidationException::class);
        // No se recibe antes de aprobar
        $this->assertThrows(fn () => $this->returns->receive($return, []), ValidationException::class, 'no puede pasar a');

        $this->returns->startReview($return);
        $this->returns->approve($return, 'Envíala por Starken a Av. Italia 100.');
        Mail::assertQueued(ReturnMail::class, fn (ReturnMail $m) => $m->kind === 'approved');
        $this->getJson(self::API."/orders/$token")->assertJsonPath('data.returns.0.instructions', 'Envíala por Starken a Av. Italia 100.');

        $itemId = $return->items()->value('id');
        $this->assertThrows(fn () => $this->returns->receive($return, [$itemId => ['condition' => 'damaged', 'restock' => true]]), ValidationException::class, 'Un producto dañado no vuelve al stock');
        $this->assertThrows(fn () => $this->returns->receive($return, []), ValidationException::class, 'Indica en qué condición');

        $before = $this->stock('TAZA-0');
        $this->returns->receive($return, [$itemId => ['condition' => 'new', 'restock' => true]]);
        $this->assertSame($before + 1, $this->stock('TAZA-0'));
        $this->assertTrue(StockMovement::query()->where('reference_type', 'return')->where('reference_id', $return->uuid)->where('quantity', 1)->exists());

        // Resolución: reembolso por defecto de lo pagado por esa unidad, ligado a la devolución
        $this->returns->process($return->fresh(), null, null);
        $return->refresh();
        $this->assertSame('processed', $return->status);
        $this->assertSame(10000, $return->refund_amount);
        $refund = Refund::query()->where('return_id', $return->id)->firstOrFail();
        $this->assertSame(10000, $refund->amount);
        Mail::assertQueued(ReturnMail::class, fn (ReturnMail $m) => $m->kind === 'processed');
        $this->assertThrows(fn () => $this->returns->process($return, null, null), ValidationException::class);

        $this->returns->close($return);
        $this->assertThrows(fn () => $this->returns->cancel($return->fresh()), ValidationException::class);
        $data = $this->getJson(self::API."/orders/$token")->json('data.returns.0');
        $this->assertSame('closed', $data['status']);
        $this->assertSame(10000, $data['refund_amount']);
        $this->assertFalse($data['can_cancel']);
        $this->assertTrue($order->events()->where('type', 'return_status')->where('to_status', 'received')->exists());
    }

    public function test_rejection_needs_a_reason_and_frees_the_units(): void
    {
        $order = $this->deliveredOrder();
        $polera = $this->item($order, 'POLERA-0');
        $return = $this->returns->request($order, [$polera => 1], 'return', 'size');
        $this->assertSame(0, $this->returns->returnable($order)[$polera]);
        $this->returns->reject($return, 'El producto fue usado.');
        Mail::assertQueued(ReturnMail::class, fn (ReturnMail $m) => $m->kind === 'rejected');
        $this->assertSame(1, $this->returns->returnable($order)[$polera], 'Un rechazo libera las unidades');
        $this->assertThrows(fn () => $this->returns->approve($return->fresh(), 'X'), ValidationException::class);
    }

    public function test_damaged_items_do_not_go_back_to_stock_and_exchanges_need_a_resolution(): void
    {
        $order = $this->deliveredOrder();
        $return = $this->returns->request($order, [$this->item($order, 'POLERA-0') => 1], 'exchange', 'size', 'Quiero talla M');
        $this->returns->approve($return, 'Tráela a la tienda.');
        $before = $this->stock('POLERA-0');
        $this->returns->receive($return, [$return->items()->value('id') => ['condition' => 'damaged']]);
        $this->assertSame($before, $this->stock('POLERA-0'));
        $this->assertThrows(fn () => $this->returns->process($return->fresh(), null, ''), ValidationException::class, 'Describe el cambio');
        $this->returns->process($return->fresh(), null, 'Enviamos una polera talla M.');
        $this->assertSame(0, Refund::query()->count(), 'Un cambio no reembolsa');
    }

    public function test_window_rules_for_the_customer(): void
    {
        $order = $this->deliveredOrder(deliver: false);
        $token = OrderTokens::for($order->uuid);
        $taza = $this->item($order, 'TAZA-0');
        $body = ['type' => 'return', 'reason' => 'size', 'items' => [['id' => $taza, 'quantity' => 1]]];

        // Sin entregar todavía
        $this->postJson(self::API."/orders/$token/returns", $body)->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Podrás pedir una devolución cuando recibas tu pedido.');
        // La tienda sí puede registrarla (por ejemplo, la trae a la tienda)
        $this->assertSame('requested', $this->returns->request($order, [$taza => 1], 'return', 'other', actor: 'user')->status);

        $shipments = app(ShipmentService::class);
        $shipment = $shipments->create($order, []);
        $shipments->updateStatus($shipment, 'shipped');
        $shipments->updateStatus($shipment, 'delivered');
        $shipment->update(['delivered_at' => now()->subDays(11)]);
        $this->postJson(self::API."/orders/$token/returns", $body)->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'El plazo para pedir una devolución venció el '.now()->subDays(1)->format('d-m-Y').'.');

        app(SettingsRepository::class)->putMany('returns', ['days' => 30]);
        $this->postJson(self::API."/orders/$token/returns", $body)->assertCreated();
        // Con los cambios desactivados solo se aceptan devoluciones
        app(SettingsRepository::class)->putMany('returns', ['allow_exchange' => false]);
        $this->postJson(self::API."/orders/$token/returns", [...$body, 'type' => 'exchange'])->assertStatus(422)->assertJsonValidationErrors('type');

        app(SettingsRepository::class)->putMany('returns', ['enabled' => false]);
        $this->getJson(self::API."/orders/$token")->assertJsonPath('data.return_policy.can_request', false);
    }

    public function test_customer_cancels_a_request_from_the_link(): void
    {
        $order = $this->deliveredOrder();
        $token = OrderTokens::for($order->uuid);
        $uuid = $this->postJson(self::API."/orders/$token/returns", ['type' => 'return', 'reason' => 'size', 'items' => [['id' => $this->item($order, 'TAZA-0'), 'quantity' => 2]]])
            ->assertCreated()->json('data.return.uuid');
        $this->postJson(self::API."/orders/$token/returns/$uuid/cancel")->assertOk()->assertJsonPath('data.returns.0.status', 'cancelled');
        $this->postJson(self::API."/orders/$token/returns/$uuid/cancel")->assertStatus(422);
        $this->postJson(self::API.'/orders/'.OrderTokens::for((string) Str::uuid())."/returns/$uuid/cancel")->assertNotFound();
    }
}
