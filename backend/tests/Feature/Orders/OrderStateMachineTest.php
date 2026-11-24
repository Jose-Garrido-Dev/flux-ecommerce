<?php

namespace Tests\Feature\Orders;

use App\Domain\Orders\Mail\OrderCancelledMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Domain\Orders\Services\OrderTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

/** Fase 8: reglas de estado del pedido. Toda transición prohibida responde 422 con el motivo en español. */
class OrderStateMachineTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private function order(array $attributes): Order
    {
        return new Order(['status' => 'pending', 'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled', ...$attributes]);
    }

    /** @return array<string, array{array, string}> */
    public static function forbidden(): array
    {
        return [
            'pendiente sin pago a preparación' => [['status' => 'pending'], 'processing'],
            'pendiente a completado' => [['status' => 'pending'], 'completed'],
            'pendiente pagado a cancelado' => [['status' => 'pending', 'payment_status' => 'paid'], 'cancelled'],
            'en preparación pagado a cancelado' => [['status' => 'processing', 'payment_status' => 'paid'], 'cancelled'],
            'en preparación despachado a cancelado' => [['status' => 'processing', 'payment_status' => 'refunded', 'fulfillment_status' => 'partially_shipped'], 'cancelled'],
            'en preparación sin entregar a completado' => [['status' => 'processing', 'payment_status' => 'paid', 'fulfillment_status' => 'shipped'], 'completed'],
            'en preparación a pendiente' => [['status' => 'processing', 'payment_status' => 'paid'], 'pending'],
            'completado a cancelado' => [['status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'delivered'], 'cancelled'],
            'completado a preparación' => [['status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'delivered'], 'processing'],
            'cancelado a mano reactivado' => [['status' => 'cancelled', 'payment_status' => 'cancelled'], 'processing'],
            'cancelado a pendiente' => [['status' => 'cancelled', 'payment_status' => 'expired'], 'pending'],
            'cancelado a completado' => [['status' => 'cancelled', 'payment_status' => 'expired'], 'completed'],
            'estado inexistente' => [['status' => 'pending'], 'archivado'],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_forbidden_transitions_throw_a_422_in_spanish(array $attributes, string $to): void
    {
        $order = $this->order($attributes);
        $this->assertFalse(OrderStateMachine::can($order, $to));
        try {
            OrderStateMachine::assert($order, $to);
            $this->fail("Debió rechazar {$attributes['status']} → $to");
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertMatchesRegularExpression('/[áéíóúñ«]|pedido|pago/u', $e->errors()['status'][0]);
        }
    }

    public function test_allowed_transitions(): void
    {
        $this->assertTrue(OrderStateMachine::can($this->order([]), 'cancelled'));
        $this->assertTrue(OrderStateMachine::can($this->order([]), 'processing', ['payment_status' => 'paid']));
        $this->assertTrue(OrderStateMachine::can($this->order(['status' => 'processing', 'payment_status' => 'paid']), 'completed', ['fulfillment_status' => 'delivered']));
        $this->assertTrue(OrderStateMachine::can($this->order(['status' => 'processing', 'payment_status' => 'paid']), 'cancelled', ['payment_status' => 'refunded']));
        $this->assertTrue(OrderStateMachine::can($this->order(['status' => 'cancelled', 'payment_status' => 'expired']), 'processing', ['payment_status' => 'paid']));
    }

    public function test_customer_cancels_a_pending_order_from_the_link_and_gets_an_email(): void
    {
        Mail::fake();
        $this->prepareStore();
        $this->stockedProduct('Taza', 10000, 5);
        $number = $this->checkout($this->addToCart('TAZA-0', 2)->json('token'), $this->checkoutData())->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        $token = OrderTokens::for($order->uuid);

        $data = $this->postJson(self::API."/orders/$token/cancel")->assertOk()->json('data');
        $this->assertSame('cancelled', $data['status']);
        $this->assertFalse($data['can_cancel']);
        Mail::assertQueued(OrderCancelledMail::class, fn ($m) => $m->hasTo('ana@correo.cl') && $m->reason === 'Lo cancelaste desde la tienda.');
        $this->assertTrue($order->events()->where('type', 'cancelled')->where('actor_type', 'customer')->exists());

        // Dos veces: ya está cancelado
        $this->postJson(self::API."/orders/$token/cancel")->assertStatus(422);
    }

    public function test_a_paid_order_cannot_be_cancelled_by_the_customer(): void
    {
        Mail::fake();
        $this->prepareStore();
        $this->stockedProduct('Taza', 10000, 5);
        $number = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData())->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        app(OrderPayments::class)->confirm($order, $order->grand_total);

        $token = OrderTokens::for($order->uuid);
        $this->getJson(self::API."/orders/$token")->assertOk()->assertJsonPath('data.can_cancel', false);
        $this->postJson(self::API."/orders/$token/cancel")->assertStatus(422);
        $this->assertSame('processing', $order->fresh()->status);
    }
}
