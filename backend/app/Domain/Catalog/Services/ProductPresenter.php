<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Models\VariantPrice;

/**
 * Convierte productos en los datos públicos que recibe el storefront.
 * Nunca incluye costos, estados internos ni datos de proveedores.
 */
class ProductPresenter
{
    public const CARD_RELATIONS = ['brand', 'media', 'variants.prices'];

    public const DETAIL_RELATIONS = [
        'brand', 'media', 'primaryCategory', 'taxClass',
        'variants.prices', 'variants.image', 'variants.attributeValues.attribute',
        'attributeValues.attribute', 'attributeValues.value',
    ];

    /** Etiquetas HTML permitidas en la descripción. Todo atributo se elimina. */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h3', 'h4', 'blockquote'];

    public function card(Product $product): array
    {
        $variants = $product->variants->where('status', 'active');
        $images = $product->media->values();

        return [
            'id' => $product->uuid,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand ? ['name' => $product->brand->name, 'slug' => $product->brand->slug] : null,
            'image' => $images->get(0)?->toPublicArray($product->name),
            'hover_image' => $images->get(1)?->toPublicArray($product->name),
            'price' => $this->priceRange($variants->all()),
            'featured' => $product->featured,
            'variant_count' => $variants->count(),
        ];
    }

    public function detail(Product $product): array
    {
        $variants = $product->variants->where('status', 'active')->values();

        return [
            ...$this->card($product),
            'short_description' => $product->short_description,
            'description_html' => $this->sanitize($product->description),
            'images' => $product->media->map(fn (Media $m) => [
                ...$m->toPublicArray($product->name),
                'variant_sku' => $variants->firstWhere('id', $m->pivot->variant_id)?->sku,
            ])->values()->all(),
            'breadcrumbs' => $this->breadcrumbs($product->primaryCategory),
            'tax' => [
                'included' => true,
                'label' => $product->taxClass?->is_exempt ? 'Exento de IVA' : 'IVA incluido',
            ],
            'options' => $this->options($variants->all()),
            'variants' => $variants->map(fn (ProductVariant $v) => [
                'sku' => $v->sku,
                'is_default' => $v->is_default,
                'options' => $v->attributeValues
                    ->mapWithKeys(fn ($val) => [$val->attribute->code => $val->value])
                    ->all(),
                'price' => $this->price($v->priceIn('CLP')),
                'image' => $v->image?->toPublicArray($product->name),
            ])->all(),
            'specifications' => $product->attributeValues
                ->filter(fn ($pav) => $pav->attribute?->visible_in_catalog)
                ->sortBy(fn ($pav) => $pav->attribute->position)
                ->map(fn ($pav) => ['name' => $pav->attribute->name, 'value' => $pav->displayValue()])
                ->values()->all(),
            'seo' => [
                'title' => $product->name,
                'description' => $product->short_description
                    ?: str($this->sanitize($product->description) ?? '')->stripTags()->squish()->limit(155)->value(),
            ],
        ];
    }

    /** Precio de una variante, o null si no tiene precio en CLP (no se puede vender). */
    public function price(?VariantPrice $price): ?array
    {
        if (! $price) {
            return null;
        }
        $amount = $price->effectiveAmount();
        $compare = $price->strikethroughAmount();

        return [
            'currency' => $price->currency,
            'amount' => $amount,
            'compare_at' => $compare,
            'discount_percent' => $compare ? (int) floor(($compare - $amount) * 100 / $compare) : null,
            'sale_ends_at' => $price->saleIsActive() ? $price->sale_ends_at?->toIso8601String() : null,
        ];
    }

    /** Precio "desde" para tarjetas: el menor precio vigente entre variantes activas. */
    private function priceRange(array $variants): ?array
    {
        $prices = collect($variants)
            ->map(fn (ProductVariant $v) => $this->price($v->priceIn('CLP')))
            ->filter()
            ->sortBy('amount')
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            ...$prices->first(),
            'varies' => $prices->pluck('amount')->unique()->count() > 1,
        ];
    }

    /** Ejes de variante (Color, Talla...) con los valores que existen en variantes activas. */
    private function options(array $variants): array
    {
        $axes = [];
        foreach ($variants as $variant) {
            foreach ($variant->attributeValues as $value) {
                $attr = $value->attribute;
                $axes[$attr->code] ??= [
                    'code' => $attr->code,
                    'name' => $attr->name,
                    'display' => $attr->display,
                    'position' => $attr->position,
                    'values' => [],
                ];
                $axes[$attr->code]['values'][$value->value] ??= [
                    'value' => $value->value,
                    'label' => $value->label,
                    'color_hex' => $value->color_hex,
                    'position' => $value->position,
                ];
            }
        }

        return collect($axes)
            ->sortBy('position')
            ->map(function ($axis) {
                $axis['values'] = collect($axis['values'])->sortBy('position')
                    ->map(fn ($v) => collect($v)->except('position')->all())->values()->all();

                return collect($axis)->except('position')->all();
            })
            ->values()->all();
    }

    private function breadcrumbs(?Category $category): array
    {
        if (! $category || $category->status !== 'active') {
            return [];
        }

        return $category->ancestors()
            ->push($category)
            ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug])
            ->values()->all();
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $clean = strip_tags($html, $allowed);

        // Quita cualquier atributo (onclick, style, href...) de las etiquetas permitidas
        return preg_replace('/<(\/?)([a-z0-9]+)\b[^>]*>/i', '<$1$2>', $clean);
    }
}
