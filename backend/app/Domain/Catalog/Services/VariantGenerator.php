<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generador de combinaciones (módulo 2, pestaña D).
 * Ej.: Color {Negro, Blanco} x Talla {S, M} = 4 variantes con SKU propio.
 * Las combinaciones que ya existen no se duplican.
 */
class VariantGenerator
{
    public const MAX_COMBINATIONS = 300;

    public function __construct(private readonly ProductWriter $writer) {}

    /**
     * @param  array<int, list<int>>  $selection  attribute_id => [attribute_value_id, ...]
     * @param  array{price_amount: int, compare_at_amount?: ?int, cost_amount?: ?int, sku_prefix?: ?string, weight_g?: ?int}  $defaults
     * @return array{created: int, skipped: int}
     */
    public function generate(Product $product, array $selection, array $defaults): array
    {
        $selection = array_filter(array_map(fn ($ids) => array_values(array_unique(array_map('intval', (array) $ids))), $selection));
        if ($selection === []) {
            throw ValidationException::withMessages(['selection' => 'Elige al menos un atributo con valores.']);
        }

        $values = AttributeValue::query()
            ->whereIn('id', array_merge(...array_values($selection)))
            ->with('attribute')->get()->keyBy('id');

        // Cada valor debe pertenecer al atributo indicado y el atributo debe servir para variantes
        foreach ($selection as $attributeId => $ids) {
            foreach ($ids as $id) {
                $v = $values->get($id);
                if (! $v || $v->attribute_id !== (int) $attributeId || ! $v->attribute->is_variant_axis) {
                    throw ValidationException::withMessages(['selection' => 'Hay valores que no corresponden a un atributo de variantes.']);
                }
            }
        }

        $combinations = [[]];
        foreach ($selection as $attributeId => $ids) {
            $next = [];
            foreach ($combinations as $combo) {
                foreach ($ids as $id) {
                    $next[] = $combo + [(int) $attributeId => $id];
                }
            }
            $combinations = $next;
        }

        if (count($combinations) > self::MAX_COMBINATIONS) {
            throw ValidationException::withMessages([
                'selection' => 'Son '.count($combinations).' combinaciones; el máximo por vez es '.self::MAX_COMBINATIONS.'.',
            ]);
        }

        return DB::transaction(function () use ($product, $combinations, $values, $defaults) {
            $existing = $product->variants()->with('attributeValues')->get();
            $existingKeys = $existing->map(fn (ProductVariant $v) => $this->key($v->attributeValues->pluck('id', 'pivot.attribute_id')->all()))->all();

            // La variante "sin opciones" de un producto simple se reemplaza al generar combinaciones
            $placeholder = $existing->count() === 1 && $existing->first()->attributeValues->isEmpty() ? $existing->first() : null;
            $placeholderPrice = $placeholder?->priceIn('CLP');

            $prefix = Str::upper(Str::slug($defaults['sku_prefix'] ?? '', '') ?: Str::slug(Str::limit($product->name, 12, ''), ''));
            $position = (int) $existing->max('position') + 1;
            $created = 0;
            $skipped = 0;

            foreach ($combinations as $combo) {
                if (in_array($this->key($combo), $existingKeys, true)) {
                    $skipped++;

                    continue;
                }
                $suffix = collect($combo)->map(fn ($id) => Str::upper(Str::slug($values[$id]->value, '')))->implode('-');

                $variant = new ProductVariant([
                    'product_id' => $product->id,
                    'is_default' => false,
                    'position' => $position++,
                ]);
                $this->writer->saveVariant($variant, [
                    'sku' => $this->writer->uniqueSku("$prefix-$suffix"),
                    'status' => 'active',
                    'weight_g' => $defaults['weight_g'] ?? $placeholder?->weight_g,
                    'price_amount' => $defaults['price_amount'] ?? $placeholderPrice?->price_amount,
                    'compare_at_amount' => $defaults['compare_at_amount'] ?? null,
                    'cost_amount' => $defaults['cost_amount'] ?? $placeholderPrice?->cost_amount,
                ]);
                foreach ($combo as $attributeId => $valueId) {
                    $variant->attributeValues()->attach($valueId, ['attribute_id' => $attributeId]);
                }
                $created++;
            }

            if ($placeholder && $created > 0) {
                $blocked = $this->writer->variantDeletionBlocker($placeholder);
                if ($blocked) {
                    $placeholder->update(['is_default' => false, 'status' => 'inactive']);
                } else {
                    $product->media()->newPivotStatement()->where('variant_id', $placeholder->id)->update(['variant_id' => null]);
                    $placeholder->delete();
                }
            }

            $this->writer->syncHasVariants($product->fresh());

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /** @param  array<int, int>  $combo */
    private function key(array $combo): string
    {
        ksort($combo);

        return json_encode($combo);
    }
}
