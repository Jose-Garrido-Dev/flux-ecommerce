<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\VariantPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Operaciones de escritura del catálogo que comparten el panel y la importación.
 * Toda regla de negocio vive aquí (o en los modelos), nunca solo en el formulario.
 */
class ProductWriter
{
    public const VARIANT_FIELDS = ['sku', 'barcode', 'status', 'weight_g', 'length_mm', 'width_mm', 'height_mm', 'image_media_id'];

    public const PRICE_FIELDS = ['price_amount', 'compare_at_amount', 'sale_amount', 'sale_starts_at', 'sale_ends_at', 'cost_amount'];

    /** Crea o actualiza la variante por defecto (productos simples) con su precio en CLP. */
    public function saveDefaultVariant(Product $product, array $data): ProductVariant
    {
        $variant = $product->variants()->where('is_default', true)->first()
            ?? new ProductVariant(['product_id' => $product->id, 'is_default' => true, 'position' => 0]);

        return $this->saveVariant($variant, $data);
    }

    /** Guarda datos de variante y su precio CLP. Convierte errores de reglas de precio en errores de validación. */
    public function saveVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $this->assertUniqueCodes($variant, $data);

        $variant->fill(array_intersect_key($data, array_flip(self::VARIANT_FIELDS)));
        $variant->status ??= 'active';
        $variant->save();

        $priceData = array_intersect_key($data, array_flip(self::PRICE_FIELDS));
        if ($priceData !== []) {
            $this->savePrice($variant, $priceData);
        }

        return $variant;
    }

    public function savePrice(ProductVariant $variant, array $data): VariantPrice
    {
        if (! isset($data['price_amount']) || $data['price_amount'] === '') {
            throw ValidationException::withMessages(['price_amount' => 'El precio de venta es obligatorio.']);
        }
        foreach (['price_amount', 'compare_at_amount', 'sale_amount', 'cost_amount'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && (int) $data[$field] < 0) {
                throw ValidationException::withMessages([$field => 'Los montos no pueden ser negativos.']);
            }
            $data[$field] = isset($data[$field]) && $data[$field] !== '' ? (int) $data[$field] : null;
        }
        if (empty($data['sale_amount'])) {
            $data['sale_starts_at'] = $data['sale_ends_at'] = null;
            $data['sale_amount'] = null;
        }

        $price = VariantPrice::query()->firstOrNew(['variant_id' => $variant->id, 'currency' => 'CLP']);
        $price->fill($data);

        try {
            $price->save();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['price_amount' => $e->getMessage()]);
        }

        return $price;
    }

    /** Sincroniza has_variants: un producto con más de una variante, o con opciones, es "con variantes". */
    public function syncHasVariants(Product $product): void
    {
        $variants = $product->variants()->withCount('attributeValues')->get();
        $has = $variants->count() > 1 || $variants->contains(fn ($v) => $v->attribute_values_count > 0);

        if ($variants->isNotEmpty() && ! $variants->contains('is_default', true)) {
            $variants->first()->update(['is_default' => true]);
        }
        if ($product->has_variants !== $has) {
            $product->forceFill(['has_variants' => $has])->save();
        }
    }

    public function publish(Product $product): void
    {
        if (! $product->variants()->where('status', 'active')->whereHas('prices')->exists()) {
            throw ValidationException::withMessages([
                'status' => "\"{$product->name}\" no tiene ninguna variante activa con precio: no se puede publicar.",
            ]);
        }
        $product->status = ProductStatus::Active;
        $product->published_at ??= now();
        $product->save();
    }

    public function unpublish(Product $product): void
    {
        $product->update(['status' => ProductStatus::Draft]);
    }

    public function archive(Product $product): void
    {
        $product->update(['status' => ProductStatus::Archived]);
    }

    /**
     * Copia un producto como borrador: variantes (SKU con sufijo), precios, imágenes,
     * categorías, especificaciones y relaciones. No copia historial ni reseñas.
     */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $product->load(['variants.prices', 'variants.attributeValues', 'categories', 'attributeValues', 'relations', 'media']);

            $copy = $product->replicate(['uuid', 'slug', 'published_at', 'status']);
            $copy->name = $product->name.' (copia)';
            $copy->slug = $this->uniqueSlug(Str::slug($product->name).'-copia');
            $copy->status = ProductStatus::Draft;
            $copy->save();

            $copy->categories()->sync($product->categories->mapWithKeys(fn ($c) => [$c->id => ['position' => $c->pivot->position]]));
            foreach ($product->attributeValues as $pav) {
                $copy->attributeValues()->create($pav->only(['attribute_id', 'attribute_value_id', 'value_text', 'value_number']));
            }
            foreach ($product->relations as $rel) {
                $copy->relations()->create($rel->only(['related_product_id', 'type', 'position']));
            }

            $variantMap = [];
            foreach ($product->variants as $variant) {
                $new = $variant->replicate(['sku', 'barcode']);
                $new->product_id = $copy->id;
                $new->sku = $this->uniqueSku($variant->sku.'-COPIA');
                $new->barcode = null;
                $new->save();
                $variantMap[$variant->id] = $new->id;

                foreach ($variant->attributeValues as $value) {
                    $new->attributeValues()->attach($value->id, ['attribute_id' => $value->pivot->attribute_id]);
                }
                foreach ($variant->prices as $price) {
                    $new->prices()->create($price->only([...self::PRICE_FIELDS, 'currency']));
                }
            }

            foreach ($product->media as $media) {
                $copy->media()->attach($media->id, [
                    'position' => $media->pivot->position,
                    'is_primary' => $media->pivot->is_primary,
                    'variant_id' => $media->pivot->variant_id ? ($variantMap[$media->pivot->variant_id] ?? null) : null,
                ]);
            }

            return $copy;
        });
    }

    /**
     * Motivo por el que NO se puede borrar físicamente, o null si se puede.
     * Un producto con pedidos debe conservarse por historial (se archiva).
     */
    public function deletionBlocker(Product $product): ?string
    {
        return $this->historyBlocker($product->variants()->select('id'), 'Archívalo en lugar de eliminarlo para conservar el historial.');
    }

    /** Motivo por el que una variante no se puede borrar (pedidos o movimientos de inventario), o null. */
    public function variantDeletionBlocker(ProductVariant $variant): ?string
    {
        return $this->historyBlocker([$variant->id], 'Desactívala en lugar de eliminarla.');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder|array<int>  $variantIds */
    private function historyBlocker($variantIds, string $advice): ?string
    {
        if (Schema::hasTable('order_items') && DB::table('order_items')->whereIn('variant_id', $variantIds)->exists()) {
            return "Tiene pedidos asociados. $advice";
        }
        if (DB::table('stock_movements')->whereIn('variant_id', $variantIds)->exists()
            || DB::table('stock_reservations')->whereIn('variant_id', $variantIds)->where('status', 'active')->exists()) {
            return "Tiene movimientos de inventario. $advice";
        }

        return null;
    }

    public function delete(Product $product): void
    {
        if ($reason = $this->deletionBlocker($product)) {
            throw ValidationException::withMessages(['product' => $reason]);
        }
        DB::transaction(fn () => $product->delete());
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $base !== '' ? Str::limit($base, 180, '') : 'producto';
        $slug = $base;
        for ($i = 2; Product::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    public function uniqueSku(string $base): string
    {
        $base = Str::upper(Str::limit($base, 58, ''));
        $sku = $base;
        for ($i = 2; ProductVariant::query()->where('sku', $sku)->exists(); $i++) {
            $sku = "$base-$i";
        }

        return $sku;
    }

    private function assertUniqueCodes(ProductVariant $variant, array $data): void
    {
        foreach (['sku' => 'SKU', 'barcode' => 'código de barras'] as $field => $label) {
            if (! filled($data[$field] ?? null)) {
                continue;
            }
            $taken = ProductVariant::query()->where($field, $data[$field])
                ->when($variant->exists, fn ($q) => $q->whereKeyNot($variant->id))
                ->with('product:id,name')->first();
            if ($taken) {
                throw ValidationException::withMessages([
                    $field => "El $label \"{$data[$field]}\" ya lo usa \"{$taken->product->name}\".",
                ]);
            }
        }
    }
}
