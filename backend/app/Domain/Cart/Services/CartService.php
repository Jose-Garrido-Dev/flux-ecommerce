<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Services\Tax;
use App\Domain\Pricing\Models\TaxClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Carrito de invitado (por token) o de cliente. Los precios y el stock se recalculan
 * en cada lectura: el precio guardado en la línea es solo referencia para avisar cambios.
 */
class CartService
{
    public const MAX_QUANTITY = 99;

    public const MAX_LINES = 100;

    public function __construct(private readonly InventoryService $inventory) {}

    /** Crea un carrito y devuelve [carrito, token]. El token solo se conoce aquí: se guarda su hash. */
    public function create(?Customer $customer = null): array
    {
        $token = Str::random(48);
        $cart = Cart::query()->create([
            'token_hash' => hash('sha256', $token),
            'customer_id' => $customer?->id,
            'email' => $customer?->email,
            'last_activity_at' => now(),
        ]);

        return [$cart, $token];
    }

    public function findByToken(?string $token): ?Cart
    {
        if (! $token || strlen($token) > 100) {
            return null;
        }

        return Cart::query()->where('token_hash', hash('sha256', $token))->where('status', 'active')->first();
    }

    public function forCustomer(Customer $customer): ?Cart
    {
        return Cart::query()->where('customer_id', $customer->id)->where('status', 'active')->latest('id')->first();
    }

    public function setQuantity(Cart $cart, string $sku, int $quantity, bool $add = false): Cart
    {
        $variant = $this->sellableVariant($sku);

        return DB::transaction(function () use ($cart, $variant, $quantity, $add) {
            $item = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variant->id)->lockForUpdate()->first();
            $target = $add ? ($item?->quantity ?? 0) + $quantity : $quantity;

            if ($target <= 0) {
                $item?->delete();
            } else {
                if ($target > self::MAX_QUANTITY) {
                    throw ValidationException::withMessages(['quantity' => 'Puedes llevar hasta '.self::MAX_QUANTITY.' unidades de un producto.']);
                }
                if (! $item && $cart->items()->count() >= self::MAX_LINES) {
                    throw ValidationException::withMessages(['sku' => 'El carrito llegó al máximo de productos distintos.']);
                }
                $this->assertAvailable($variant, $target);
                CartItem::query()->updateOrCreate(
                    ['cart_id' => $cart->id, 'variant_id' => $variant->id],
                    ['quantity' => $target, 'unit_price_snapshot' => $variant->priceIn('CLP')->effectiveAmount()],
                );
            }
            $cart->update(['last_activity_at' => now()]);

            return $cart->fresh();
        });
    }

    public function remove(Cart $cart, string $sku): Cart
    {
        $variantId = ProductVariant::query()->where('sku', Str::upper($sku))->value('id');
        CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variantId)->delete();
        $cart->update(['last_activity_at' => now()]);

        return $cart->fresh();
    }

    /** Al ingresar, el carrito de invitado se une al del cliente (se suman cantidades, respetando el stock). */
    public function attachToCustomer(Cart $guest, Customer $customer): Cart
    {
        $existing = $this->forCustomer($customer);
        if (! $existing || $existing->is($guest)) {
            $guest->update(['customer_id' => $customer->id, 'email' => $customer->email]);

            return $guest->fresh();
        }

        return DB::transaction(function () use ($guest, $existing) {
            foreach ($guest->items()->with('variant')->get() as $item) {
                try {
                    $this->setQuantity($existing, $item->variant->sku, $item->quantity, add: true);
                } catch (ValidationException) {
                    // Si no cabe por stock, queda la cantidad que ya tenía el cliente
                }
            }
            $guest->items()->delete();
            $guest->update(['status' => 'abandoned']);
            // El carrito activo pasa a ser el del cliente, pero con el token del navegador actual
            $token = $guest->token_hash;
            $guest->update(['token_hash' => hash('sha256', Str::random(48))]);
            $existing->update(['token_hash' => $token, 'last_activity_at' => now()]);

            return $existing->fresh();
        });
    }

    /**
     * Resumen del carrito con precios y stock vigentes.
     *
     * @return array{items: list<array>, count: int, subtotal: int, tax_total: int, warnings: list<string>}
     */
    public function summary(Cart $cart): array
    {
        $cart->loadMissing(['items.variant' => fn ($q) => $q->with([
            'product.taxClass', 'product.media', 'prices', 'attributeValues.attribute', 'image', 'inventoryItem', 'levels.warehouse',
        ])]);
        $defaultRate = $this->defaultRateBp();

        $items = [];
        $warnings = [];
        foreach ($cart->items as $item) {
            $line = $this->line($item->variant, $item->quantity, $defaultRate);
            $line['previous_unit_price'] = $item->unit_price_snapshot !== $line['unit_price'] ? $item->unit_price_snapshot : null;
            if ($line['previous_unit_price'] !== null && $line['purchasable']) {
                $warnings[] = "Cambió el precio de {$line['name']}.";
            }
            if (! $line['purchasable']) {
                $warnings[] = "{$line['name']} ya no está disponible.";
            } elseif ($line['max_quantity'] !== null && $item->quantity > $line['max_quantity']) {
                $warnings[] = "Solo quedan {$line['max_quantity']} unidades de {$line['name']}.";
            }
            $items[] = $line;
        }
        $valid = array_filter($items, fn ($l) => $l['purchasable']);

        return [
            'items' => $items,
            'count' => array_sum(array_column($items, 'quantity')),
            'subtotal' => array_sum(array_column($valid, 'line_total')),
            'tax_total' => array_sum(array_column($valid, 'tax_amount')),
            'warnings' => $warnings,
        ];
    }

    /** Línea con precio vigente, IVA y disponibilidad. Es la misma lógica que usa el checkout. */
    public function line(ProductVariant $variant, int $quantity, ?int $defaultRate = null): array
    {
        $product = $variant->product;
        $price = $variant->priceIn('CLP');
        $unit = $price?->effectiveAmount() ?? 0;
        $rate = $product->taxClass?->effectiveRateBp() ?? ($defaultRate ?? $this->defaultRateBp());
        $available = $this->inventory->available($variant);
        $backorder = (bool) $variant->inventoryItem?->allow_backorder;
        $purchasable = $price !== null && $variant->status === 'active' && $product->isVisible()
            && ($available === null || $backorder || $available > 0);
        $gross = $unit * $quantity;
        $image = $variant->image ?? $product->media->first();

        return [
            'sku' => $variant->sku,
            'product_slug' => $product->slug,
            'name' => $product->name,
            'variant_label' => $variant->attributeValues->sortBy(fn ($v) => $v->attribute?->position)->pluck('label')->implode(' / ') ?: null,
            'image' => $image?->toPublicArray($product->name),
            'quantity' => $quantity,
            'unit_price' => $unit,
            'compare_at' => $price?->strikethroughAmount(),
            'line_total' => $gross,
            'tax_rate_bp' => $rate,
            'tax_amount' => Tax::included($gross, $rate),
            'purchasable' => $purchasable,
            'max_quantity' => ($available === null || $backorder) ? null : max(0, min($available, self::MAX_QUANTITY)),
            'stock' => $this->inventory->stockStatus($variant),
            'unit_cost' => $price?->cost_amount,
            'variant_id' => $variant->id,
        ];
    }

    public function defaultRateBp(): int
    {
        return TaxClass::query()->where('is_default', true)->first()?->effectiveRateBp() ?? 1900;
    }

    private function sellableVariant(string $sku): ProductVariant
    {
        $variant = ProductVariant::query()->where('sku', Str::upper(trim($sku)))->active()
            ->with(['product', 'prices', 'inventoryItem', 'levels.warehouse'])->first();
        if (! $variant || ! Product::query()->visible()->whereKey($variant->product_id)->exists() || ! $variant->priceIn('CLP')) {
            throw ValidationException::withMessages(['sku' => 'Este producto no está disponible.']);
        }

        return $variant;
    }

    private function assertAvailable(ProductVariant $variant, int $quantity): void
    {
        $available = $this->inventory->available($variant);
        if ($available === null || $variant->inventoryItem?->allow_backorder) {
            return;
        }
        if ($quantity > $available) {
            throw ValidationException::withMessages(['quantity' => $available > 0
                ? "Solo quedan $available unidades disponibles."
                : 'Este producto está agotado.']);
        }
    }
}
