<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
    }

    private function variant(int $stock = 0)
    {
        $variant = $this->product('Taza '.uniqid(), [['price' => 5000]])->variants()->first();
        if ($stock > 0) {
            $this->inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, $stock, 'Compra inicial');
        }

        return $variant->fresh();
    }

    private function level($variant, ?Warehouse $warehouse = null): InventoryLevel
    {
        return InventoryLevel::query()->where(['variant_id' => $variant->id, 'warehouse_id' => ($warehouse ?? Warehouse::default())->id])->firstOrFail();
    }

    public function test_new_variants_get_inventory_record_and_level_in_default_warehouse(): void
    {
        $variant = $this->variant();

        $this->assertTrue($variant->inventoryItem->track_stock);
        $this->assertSame(0, $this->level($variant)->on_hand);
        $this->assertSame(0, $this->inventory->available($variant));
    }

    public function test_movements_record_before_and_after_and_outputs_cannot_exceed_available(): void
    {
        $variant = $this->variant(10);
        $out = $this->inventory->move($variant, Warehouse::default(), MovementType::Shrinkage, 3, 'Producto dañado');

        $this->assertSame(-3, $out->quantity);
        $this->assertSame(10, $out->before_on_hand);
        $this->assertSame(7, $out->after_on_hand);
        $this->assertSame(7, $this->level($variant)->on_hand);

        $this->inventory->reserve($variant, 5, 'cart', 'c1');
        try {
            $this->inventory->move($variant, Warehouse::default(), MovementType::ManualOut, 3);
            $this->fail('Debía rechazar la salida: solo quedan 2 libres');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Solo hay 2 unidades disponibles', $e->errors()['quantity'][0]);
        }
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        $movement = $this->inventory->move($this->variant(), Warehouse::default(), MovementType::ManualIn, 5);

        try {
            $movement->update(['quantity' => 50]);
            $this->fail('El modelo debía impedir la edición');
        } catch (LogicException) {
        }
        try {
            $movement->delete();
            $this->fail('El modelo debía impedir el borrado');
        } catch (LogicException) {
        }

        if (DB::getDriverName() === 'pgsql') {
            // Ni siquiera con SQL directo: lo impide un trigger de la base de datos
            $this->expectException(QueryException::class);
            DB::table('stock_movements')->where('id', $movement->id)->update(['quantity' => 50]);
        }
        $this->assertSame(5, $movement->fresh()->quantity);
    }

    public function test_reversal_creates_counter_movement_only_once(): void
    {
        $variant = $this->variant();
        $wrong = $this->inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, 100, 'Se digitó 100 en vez de 10');

        $reversal = $this->inventory->reverse($wrong, 'Error de digitación');
        $this->assertSame(MovementType::Reversal, $reversal->type);
        $this->assertSame(-100, $reversal->quantity);
        $this->assertSame($wrong->id, $reversal->reverses_movement_id);
        $this->assertSame(0, $this->level($variant)->on_hand);
        $this->assertSame(100, $wrong->fresh()->quantity, 'El movimiento original no cambia');

        $this->expectException(ValidationException::class);
        $this->inventory->reverse($wrong, 'otra vez');
    }

    public function test_count_adjusts_difference(): void
    {
        $variant = $this->variant(10);
        $movement = $this->inventory->countTo($variant, Warehouse::default(), 8, 'Conteo mensual');

        $this->assertSame(MovementType::AdjustOut, $movement->type);
        $this->assertSame(-2, $movement->quantity);
        $this->assertNull($this->inventory->countTo($variant, Warehouse::default(), 8));
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $variant = $this->variant(10);
        $store = Warehouse::query()->create(['name' => 'Tienda Providencia', 'code' => 'PROV', 'priority' => 1]);

        $transfer = $this->inventory->transfer(Warehouse::default(), $store, [['variant_id' => $variant->id, 'quantity' => 4]]);

        $this->assertSame(6, $this->level($variant)->on_hand);
        $this->assertSame(4, $this->level($variant, $store)->on_hand);
        $this->assertSame(2, StockMovement::query()->where('stock_transfer_id', $transfer->id)->count());
        $this->assertSame(10, $this->inventory->available($variant->fresh()));

        $this->expectException(ValidationException::class);
        $this->inventory->transfer(Warehouse::default(), $store, [['variant_id' => $variant->id, 'quantity' => 7]]);
    }

    public function test_reservation_is_idempotent_and_consumption_does_not_discount_twice(): void
    {
        $variant = $this->variant(3);

        $first = $this->inventory->reserve($variant, 2, 'cart', 'abc');
        $again = $this->inventory->reserve($variant, 2, 'cart', 'abc');
        $this->assertTrue($first->is($again), 'Repetir la reserva no la duplica');
        $this->assertSame(2, $this->level($variant)->reserved);
        $this->assertSame(1, $this->inventory->available($variant->fresh()));

        $this->assertTrue($this->inventory->consume($first, 'P-1001'));
        $this->assertFalse($this->inventory->consume($first, 'P-1001'), 'Un webhook repetido no descuenta de nuevo');

        $level = $this->level($variant);
        $this->assertSame(1, $level->on_hand);
        $this->assertSame(0, $level->reserved);
        $this->assertSame(1, StockMovement::query()->where('type', MovementType::SaleOut)->count());
    }

    public function test_cannot_reserve_more_than_available_unless_backorder_allowed(): void
    {
        $variant = $this->variant(1);

        try {
            $this->inventory->reserve($variant, 2, 'cart', 'x');
            $this->fail('No había stock suficiente');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }

        $variant->inventoryItem->update(['allow_backorder' => true]);
        $this->assertNotNull($this->inventory->reserve($variant, 2, 'cart', 'x'));

        $untracked = $this->variant();
        $untracked->inventoryItem->update(['track_stock' => false]);
        $this->assertNull($this->inventory->reserve($untracked, 99, 'cart', 'x'), 'Sin control de stock no se reserva');
        $this->assertNull($this->inventory->available($untracked->fresh()));
    }

    public function test_expired_reservations_are_released_by_scheduled_command(): void
    {
        $variant = $this->variant(1);
        $reservation = $this->inventory->reserve($variant, 1, 'cart', 'viejo', minutes: 30);

        $this->travel(29)->minutes();
        $this->artisan('inventory:expire-reservations')->assertSuccessful();
        $this->assertSame('active', $reservation->fresh()->status);

        $this->travel(2)->minutes();
        $this->artisan('inventory:expire-reservations')->expectsOutputToContain('liberadas: 1')->assertSuccessful();

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(0, $this->level($variant)->reserved);
        $this->assertSame(1, $this->inventory->available($variant->fresh()));

        // Un pago que llega tarde no puede usar una reserva vencida
        $this->expectException(ValidationException::class);
        $this->inventory->consume($reservation);
    }

    public function test_scheduler_runs_expiration_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'inventory:expire-reservations'));

        $this->assertNotNull($event, 'La tarea debe estar en el scheduler');
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_sequential_last_unit_only_sold_once(): void
    {
        $variant = $this->variant(1);
        $ok = 0;
        for ($i = 0; $i < 20; $i++) {
            try {
                $this->inventory->reserve($variant, 1, 'cart', "c$i");
                $ok++;
            } catch (ValidationException) {
            }
        }
        $this->assertSame(1, $ok);
        $this->assertSame(1, StockReservation::query()->where('status', 'active')->count());
    }
}
