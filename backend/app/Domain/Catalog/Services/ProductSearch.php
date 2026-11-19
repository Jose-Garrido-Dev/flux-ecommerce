<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Búsqueda y filtrado del catálogo público.
 *
 * Filtros: dentro de un mismo atributo se combinan con O (rojo o azul) y entre
 * atributos distintos con Y (rojo y talla M). Las facetas de un atributo se
 * cuentan ignorando su propio filtro, para que el cliente pueda sumar opciones.
 */
class ProductSearch
{
    public const SORTS = ['relevance', 'newest', 'price_asc', 'price_desc', 'name'];

    public const MAX_PER_PAGE = 48;

    /**
     * @param  array{category?: ?Category, brand?: ?Brand, q?: ?string, sort?: ?string, featured?: bool,
     *               min_price?: ?int, max_price?: ?int, filters?: array<string, list<string>>,
     *               page?: int, per_page?: int, exclude_ids?: list<int>}  $criteria
     */
    public function search(array $criteria): LengthAwarePaginator
    {
        $query = $this->baseQuery($criteria);
        $this->applySort($query, $criteria['sort'] ?? 'relevance');

        $perPage = min(max((int) ($criteria['per_page'] ?? 24), 1), self::MAX_PER_PAGE);

        return $query
            ->with(ProductPresenter::CARD_RELATIONS)
            ->paginate($perPage, ['products.*'], 'page', max((int) ($criteria['page'] ?? 1), 1));
    }

    /** @return list<array{code: string, name: string, display: string, values: list<array>}> */
    public function facets(array $criteria): array
    {
        $attributes = Attribute::query()
            ->where('is_filterable', true)
            ->orderBy('position')
            ->with('values')
            ->get();

        $facets = [];
        foreach ($attributes as $attribute) {
            $others = $criteria;
            unset($others['filters'][$attribute->code]);

            $productIds = $this->baseQuery($others)->select('products.id');
            $counts = $this->valueCounts($productIds, $attribute->id);

            $values = $attribute->values
                ->filter(fn ($v) => ($counts[$v->id] ?? 0) > 0)
                ->map(fn ($v) => [
                    'value' => $v->value,
                    'label' => $v->label,
                    'color_hex' => $v->color_hex,
                    'count' => (int) $counts[$v->id],
                    'selected' => in_array($v->value, $criteria['filters'][$attribute->code] ?? [], true),
                ])->values()->all();

            if ($values !== []) {
                $facets[] = [
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'display' => $attribute->display,
                    'values' => $values,
                ];
            }
        }

        return $facets;
    }

    public function baseQuery(array $criteria): Builder
    {
        $query = Product::query()->visible();

        if ($category = $criteria['category'] ?? null) {
            $ids = $category->selfAndDescendantIds();
            $query->where(fn (Builder $q) => $q
                ->whereIn('primary_category_id', $ids)
                ->orWhereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $ids)));
        }

        if ($brand = $criteria['brand'] ?? null) {
            $query->where('brand_id', $brand->id);
        }

        if ($criteria['featured'] ?? false) {
            $query->where('featured', true);
        }

        if ($criteria['on_sale'] ?? false) {
            $now = now()->toDateTimeString();
            $query->whereExists(fn (QueryBuilder $q) => $q->selectRaw('1')
                ->from('product_variants as ov')
                ->join('variant_prices as op', 'op.variant_id', '=', 'ov.id')
                ->whereColumn('ov.product_id', 'products.id')
                ->where('ov.status', 'active')
                ->whereNotNull('op.sale_amount')
                ->where(fn ($w) => $w->whereNull('op.sale_starts_at')->orWhere('op.sale_starts_at', '<=', $now))
                ->where(fn ($w) => $w->whereNull('op.sale_ends_at')->orWhere('op.sale_ends_at', '>', $now)));
        }

        if ($ids = $criteria['exclude_ids'] ?? []) {
            $query->whereNotIn('products.id', $ids);
        }

        if ($term = trim((string) ($criteria['q'] ?? ''))) {
            $this->applyTextSearch($query, $term);
        }

        $min = $criteria['min_price'] ?? null;
        $max = $criteria['max_price'] ?? null;
        if ($min !== null || $max !== null) {
            $query->whereExists(function (QueryBuilder $q) use ($min, $max) {
                $q->selectRaw('1')
                    ->from('product_variants as pv')
                    ->join('variant_prices as vp', 'vp.variant_id', '=', 'pv.id')
                    ->whereColumn('pv.product_id', 'products.id')
                    ->where('pv.status', 'active')
                    ->where('vp.currency', 'CLP');
                [$sql, $bindings] = $this->effectivePriceSql('vp');
                if ($min !== null) {
                    $q->whereRaw("$sql >= ?", [...$bindings, $min]);
                }
                if ($max !== null) {
                    $q->whereRaw("$sql <= ?", [...$bindings, $max]);
                }
            });
        }

        foreach ($criteria['filters'] ?? [] as $code => $values) {
            $values = array_values(array_filter((array) $values, 'strlen'));
            if ($values === []) {
                continue;
            }
            // Parámetros que no son atributos filtrables (p. ej. utm_source) se ignoran
            $attributeId = DB::table('attributes')->where('code', $code)->where('is_filterable', true)->value('id');
            if (! $attributeId) {
                continue;
            }
            $valueIds = DB::table('attribute_values')
                ->where('attribute_id', $attributeId)
                ->whereIn('value', $values)
                ->pluck('id')->all();

            // Un valor inexistente no debe devolver todo el catálogo
            $valueIds = $valueIds ?: [0];

            $query->where(fn (Builder $q) => $q
                ->whereExists(fn (QueryBuilder $s) => $s->selectRaw('1')
                    ->from('product_variants as fv')
                    ->join('variant_attribute_values as fvav', 'fvav.variant_id', '=', 'fv.id')
                    ->whereColumn('fv.product_id', 'products.id')
                    ->where('fv.status', 'active')
                    ->whereIn('fvav.attribute_value_id', $valueIds))
                ->orWhereExists(fn (QueryBuilder $s) => $s->selectRaw('1')
                    ->from('product_attribute_values as fpav')
                    ->whereColumn('fpav.product_id', 'products.id')
                    ->whereIn('fpav.attribute_value_id', $valueIds)));
        }

        return $query;
    }

    private function applyTextSearch(Builder $query, string $term): void
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $words = array_slice(array_filter(preg_split('/\s+/', Str::limit($term, 100, ''))), 0, 6);

        foreach ($words as $word) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
            // ESCAPE explícito: así "100%" busca el símbolo % y no un comodín (PostgreSQL y SQLite).
            // Se usa "!" porque la barra invertida la interpreta distinto cada driver.
            $match = fn ($q, string $column) => $q->whereRaw("$column $like ? ESCAPE '!'", [$pattern]);
            $query->where(fn (Builder $q) => $q
                ->where(fn ($w) => $match($w, 'products.name'))
                ->orWhere(fn ($w) => $match($w, 'products.short_description'))
                ->orWhereHas('brand', fn (Builder $b) => $match($b, 'brands.name'))
                ->orWhereHas('variants', fn (Builder $v) => $match($v, 'product_variants.sku')));
        }
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'relevance';

        if (in_array($sort, ['price_asc', 'price_desc'], true)) {
            [$sql, $bindings] = $this->effectivePriceSql('sp');
            $sub = DB::table('product_variants as sv')
                ->join('variant_prices as sp', 'sp.variant_id', '=', 'sv.id')
                ->whereColumn('sv.product_id', 'products.id')
                ->where('sv.status', 'active')
                ->where('sp.currency', 'CLP')
                ->selectRaw("min($sql)", $bindings);
            $query->orderBy($sub, $sort === 'price_asc' ? 'asc' : 'desc');
        } elseif ($sort === 'name') {
            $query->orderBy('products.name');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('products.published_at');
        } else {
            $query->orderByDesc('products.featured')->orderByDesc('products.published_at');
        }

        $query->orderBy('products.id');
    }

    /** SQL del precio vigente (oferta si está activa) para la tabla de precios con alias dado. */
    private function effectivePriceSql(string $alias): array
    {
        $now = now()->toDateTimeString();

        return [
            "CASE WHEN $alias.sale_amount IS NOT NULL
                AND ($alias.sale_starts_at IS NULL OR $alias.sale_starts_at <= ?)
                AND ($alias.sale_ends_at IS NULL OR $alias.sale_ends_at > ?)
              THEN $alias.sale_amount ELSE $alias.price_amount END",
            [$now, $now],
        ];
    }

    /** @return array<int, int> attribute_value_id => cantidad de productos */
    private function valueCounts(Builder $productIds, int $attributeId): array
    {
        $fromVariants = DB::table('variant_attribute_values as cvav')
            ->join('product_variants as cv', 'cv.id', '=', 'cvav.variant_id')
            ->where('cv.status', 'active')
            ->where('cvav.attribute_id', $attributeId)
            ->whereIn('cv.product_id', (clone $productIds)->toBase())
            ->select(['cv.product_id', 'cvav.attribute_value_id']);

        $fromProducts = DB::table('product_attribute_values as cpav')
            ->where('cpav.attribute_id', $attributeId)
            ->whereNotNull('cpav.attribute_value_id')
            ->whereIn('cpav.product_id', (clone $productIds)->toBase())
            ->select(['cpav.product_id', 'cpav.attribute_value_id']);

        return DB::query()
            ->fromSub($fromVariants->union($fromProducts), 'pairs')
            ->selectRaw('attribute_value_id, count(distinct product_id) as total')
            ->groupBy('attribute_value_id')
            ->pluck('total', 'attribute_value_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
