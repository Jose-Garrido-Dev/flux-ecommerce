<?php

namespace App\Filament\Resources\Products\Concerns;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductPresenter;
use App\Domain\Catalog\Services\ProductWriter;
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
        $publish = ($data['status'] ?? null) === ProductStatus::Active->value;
        if ($publish) {
            $data['status'] = $record?->status?->value ?? ProductStatus::Draft->value; // se publica al final, con validación
        }
        if (isset($data['description'])) {
            $data['description'] = app(ProductPresenter::class)->sanitize($data['description']);
        }

        try {
            return DB::transaction(function () use ($record, $data, $variantData, $publish, $writer) {
                $product = $record ?? new Product;
                $product->fill(Arr::except($data, ['categories', 'attributeValues']));
                $product->save();

                if ($variantData !== null && ! $product->has_variants) {
                    $writer->saveDefaultVariant($product, $variantData);
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
            default => "data.$key",
        };
    }
}
