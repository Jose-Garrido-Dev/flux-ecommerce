<?php

namespace App\Filament\Support;

use App\Domain\Catalog\Models\ProductVariant;
use Filament\Forms\Components\Select;

/** Selector de variante con búsqueda por nombre de producto, SKU o código de barras. */
final class VariantPicker
{
    public static function make(string $name = 'variant_id', string $label = 'Producto'): Select
    {
        return Select::make($name)->label($label)->searchable()->required()
            ->getSearchResultsUsing(fn (string $search) => self::search($search))
            ->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::query()->with(['product', 'attributeValues'])->find($value)) ? self::label($v) : null)
            ->helperText('Busca por nombre, SKU o código de barras.');
    }

    /** @return array<int, string> */
    public static function search(string $search, int $limit = 30): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
        $op = ProductVariant::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return ProductVariant::query()
            ->with(['product', 'attributeValues'])
            ->where(fn ($q) => $q->where('sku', $op, $like)->orWhere('barcode', $search)
                ->orWhereHas('product', fn ($p) => $p->where('name', $op, $like)))
            ->orderBy('sku')->limit($limit)->get()
            ->mapWithKeys(fn (ProductVariant $v) => [$v->id => self::label($v)])->all();
    }

    public static function label(ProductVariant $variant): string
    {
        $options = $variant->attributeValues->pluck('label')->implode(' / ');

        return $variant->product->name.($options ? " · $options" : '')." ({$variant->sku})";
    }
}
