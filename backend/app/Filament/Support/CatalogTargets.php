<?php

namespace App\Filament\Support;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Filament\Forms\Components\Select;

/** Selectores de productos, categorías y marcas para cupones y promociones. */
final class CatalogTargets
{
    public static function products(string $name, string $label): Select
    {
        return Select::make($name)->label($label)->multiple()->searchable()
            ->getSearchResultsUsing(fn (string $search) => Product::query()
                ->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%'))
                ->orderBy('name')->limit(30)->pluck('name', 'id')->all())
            ->getOptionLabelsUsing(fn (array $values) => Product::query()->whereKey($values)->pluck('name', 'id')->all())
            ->noSearchResultsMessage('No hay productos con ese nombre.')
            ->searchPrompt('Escribe para buscar un producto');
    }

    public static function categories(string $name, string $label): Select
    {
        return Select::make($name)->label($label)->multiple()->searchable()
            ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')->all())
            ->helperText('Incluye sus subcategorías.');
    }

    public static function brands(string $name, string $label): Select
    {
        return Select::make($name)->label($label)->multiple()->searchable()
            ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    /** @return array<int, int> */
    public static function ids(mixed $value): array
    {
        return array_values(array_unique(array_map('intval', array_filter((array) $value))));
    }
}
