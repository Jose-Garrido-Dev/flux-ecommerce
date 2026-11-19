<?php

namespace App\Filament\Resources\Products\Concerns;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\ProductPresenter;
use App\Domain\Catalog\Services\ProductWriter;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guardado del formulario de producto en una sola transacción:
 * datos del producto + variante por defecto (productos simples) + reglas de publicación.
 * Los errores de reglas del dominio se muestran junto al campo correspondiente.
 */
trait SavesProduct
{
    protected function saveProduct(?Product $record, array $data): Product
    {
        $writer = app(ProductWriter::class);
        $variantData = Arr::pull($data, 'default_variant');
        $inventory = Arr::pull($data, 'inventory');
        $publish = ($data['status'] ?? null) === ProductStatus::Active->value;
        if ($publish) {
            $data['status'] = $record?->status?->value ?? ProductStatus::Draft->value; // se publica al final, con validación
        }
        if (isset($data['description'])) {
            $data['description'] = app(ProductPresenter::class)->sanitize($data['description']);
        }

        try {
            return DB::transaction(function () use ($record, $data, $variantData, $inventory, $publish, $writer) {
                $product = $record ?? new Product;
                $product->fill(Arr::except($data, ['categories', 'attributeValues']));
                $product->save();

                if ($variantData !== null && ! $product->has_variants) {
                    $variant = $writer->saveDefaultVariant($product, $variantData);
                    if (is_array($inventory)) {
                        $this->saveInventory($variant, $inventory, $record === null);
                    }
                }

                if ($product->primary_category_id) {
                    $product->categories()->syncWithoutDetaching([$product->primary_category_id]);
                }

                if ($publish) {
                    $writer->publish($product);
                }

                return $product;
            });
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => [
                $this->errorKey($key) => $messages,
            ])->all());
        }
    }

    /** Ficha de inventario de la variante por defecto y, al crear, el stock inicial como movimiento. */
    protected function saveInventory(ProductVariant $variant, array $inventory, bool $creating): void
    {
        $service = app(InventoryService::class);
        $track = (bool) ($inventory['track_stock'] ?? true);
        $service->item($variant)->update([
            'track_stock' => $track,
            'allow_backorder' => $track && (bool) ($inventory['allow_backorder'] ?? false),
            'min_stock' => $track ? (int) ($inventory['min_stock'] ?? 0) : 0,
        ]);
        $initial = (int) ($inventory['initial_stock'] ?? 0);
        if ($creating && $track && $initial > 0) {
            $warehouse = (int) ($inventory['warehouse_id'] ?? 0) ?: Warehouse::default()->id;
            $service->move($variant, $warehouse, MovementType::ManualIn, $initial, 'Stock inicial');
        }
    }

    protected function inventoryFormData(Product $product): array
    {
        $variant = $product->variants()->where('is_default', true)->first();

        return $variant ? app(InventoryService::class)->item($variant)->only(['track_stock', 'allow_backorder', 'min_stock']) : [];
    }

    /**
     * Filament sincroniza "categorías secundarias" después de guardar y eso quitaría la principal
     * de la tabla pivote (que se usa para filtrar por categoría en la tienda). Se vuelve a agregar.
     */
    protected function keepPrimaryCategoryAttached(): void
    {
        $product = $this->getRecord();
        if ($product->primary_category_id) {
            $product->categories()->syncWithoutDetaching([$product->primary_category_id]);
        }
    }

    protected function defaultVariantFormData(Product $product): array
    {
        $variant = $product->variants()->where('is_default', true)->with('prices')->first();
        if (! $variant) {
            return [];
        }
        $price = $variant->priceIn('CLP');

        return [
            ...$variant->only(['sku', 'barcode', 'weight_g', 'length_mm', 'width_mm', 'height_mm']),
            ...($price?->only(['price_amount', 'compare_at_amount', 'cost_amount', 'sale_amount', 'sale_starts_at', 'sale_ends_at']) ?? []),
        ];
    }

    private function errorKey(string $key): string
    {
        $variantFields = [...ProductWriter::VARIANT_FIELDS, ...ProductWriter::PRICE_FIELDS];

        return match (true) {
            in_array($key, $variantFields, true) => "data.default_variant.$key",
            $key === 'status' => 'data.status',
            $key === 'quantity' => 'data.inventory.initial_stock',
            default => "data.$key",
        };
    }
}
