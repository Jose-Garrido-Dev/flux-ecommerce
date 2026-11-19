<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Str;

/** Ayudas para armar catálogos mínimos en pruebas. */
trait CatalogBuilder
{
    protected function attribute(string $code, array $values, bool $axis = true): Attribute
    {
        $attr = Attribute::query()->create([
            'code' => $code, 'name' => Str::ucfirst($code), 'is_filterable' => true, 'is_variant_axis' => $axis,
        ]);
        foreach ($values as $i => $v) {
            $attr->values()->create(['value' => $v, 'label' => Str::ucfirst($v), 'position' => $i]);
        }

        return $attr;
    }

    /**
     * @param  array<int, array{price: int, options?: array<string, string>, sale?: int, status?: string}>  $variants
     */
    protected function product(string $name, array $variants, array $attrs = []): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => ProductStatus::Active,
            'published_at' => now()->subDay(),
            ...$attrs,
        ]);

        foreach ($variants as $i => $v) {
            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'sku' => Str::upper(Str::slug($name, '')).'-'.$i,
                'is_default' => $i === 0,
                'position' => $i,
                'status' => $v['status'] ?? 'active',
            ]);
            $variant->prices()->create([
                'price_amount' => $v['price'],
                'sale_amount' => $v['sale'] ?? null,
            ]);
            foreach ($v['options'] ?? [] as $code => $value) {
                $val = AttributeValue::query()
                    ->whereHas('attribute', fn ($q) => $q->where('code', $code))
                    ->where('value', $value)->firstOrFail();
                $variant->attributeValues()->attach($val->id, ['attribute_id' => $val->attribute_id]);
            }
        }

        return $product;
    }

    protected function category(string $name, ?Category $parent = null): Category
    {
        return Category::query()->create(['name' => $name, 'parent_id' => $parent?->id]);
    }
}
