<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductPresenter;
use App\Domain\Catalog\Services\ProductSearch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogo público. Solo muestra lo publicado; nunca costos ni datos internos. */
class CatalogController extends Controller
{
    public function __construct(
        private readonly ProductSearch $search,
        private readonly ProductPresenter $presenter,
    ) {}

    public function products(Request $request): JsonResponse
    {
        $input = $request->validate([
            'category' => ['nullable', 'string', 'max:160'],
            'brand' => ['nullable', 'string', 'max:160'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(ProductSearch::SORTS)],
            'featured' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'filter' => ['nullable', 'array', 'max:10'],
            'filter.*' => ['string', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductSearch::MAX_PER_PAGE],
            'facets' => ['nullable', 'boolean'],
        ]);

        $category = isset($input['category'])
            ? Category::query()->active()->where('slug', $input['category'])->first()
            : null;
        $brand = isset($input['brand'])
            ? Brand::query()->active()->where('slug', $input['brand'])->first()
            : null;

        if ((isset($input['category']) && ! $category) || (isset($input['brand']) && ! $brand)) {
            return $this->notFound('La categoría o marca no existe o no está publicada.');
        }

        $criteria = [
            'category' => $category,
            'brand' => $brand,
            'q' => $input['q'] ?? null,
            'sort' => $input['sort'] ?? null,
            'featured' => (bool) ($input['featured'] ?? false),
            'on_sale' => (bool) ($input['on_sale'] ?? false),
            'min_price' => isset($input['min_price']) ? (int) $input['min_price'] : null,
            'max_price' => isset($input['max_price']) ? (int) $input['max_price'] : null,
            // filter[color]=rojo,azul
            'filters' => collect($input['filter'] ?? [])
                ->map(fn ($csv) => array_values(array_filter(array_map('trim', explode(',', $csv)))))
                ->all(),
            'page' => $input['page'] ?? 1,
            'per_page' => $input['per_page'] ?? 24,
        ];

        $page = $this->search->search($criteria);

        return response()->json([
            'data' => collect($page->items())->map(fn (Product $p) => $this->presenter->card($p))->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'facets' => ($input['facets'] ?? true) ? $this->search->facets($criteria) : [],
        ]);
    }

    public function product(string $slug): JsonResponse
    {
        $product = Product::query()->visible()->where('slug', $slug)
            ->with(ProductPresenter::DETAIL_RELATIONS)
            ->first();

        if (! $product) {
            return $this->notFound('El producto no existe o no está publicado.');
        }

        // Primero los elegidos a mano en el panel (pestaña Relacionados); se completa con la misma categoría
        $manual = Product::query()->visible()
            ->joinSub($product->relations()->getQuery()->select(['related_product_id', 'position']), 'rel', 'rel.related_product_id', '=', 'products.id')
            ->orderBy('rel.position')->select('products.*')->distinct()
            ->with(ProductPresenter::CARD_RELATIONS)->limit(8)->get()->unique('id');

        $related = $manual;
        if ($manual->count() < 8) {
            $auto = $this->search->search([
                'category' => $product->primaryCategory?->status === 'active' ? $product->primaryCategory : null,
                'brand' => $product->primaryCategory ? null : $product->brand,
                'exclude_ids' => [$product->id, ...$manual->modelKeys()],
                'per_page' => 8 - $manual->count(),
            ]);
            $related = $manual->concat($auto->items());
        }

        return response()->json([
            'data' => $this->presenter->detail($product),
            'related' => $related->map(fn (Product $p) => $this->presenter->card($p))->values()->all(),
        ]);
    }

    /** Árbol de categorías del menú (activas y marcadas "en menú"). */
    public function categories(): JsonResponse
    {
        $all = Category::query()->active()->where('in_menu', true)
            ->with('image')
            ->orderBy('depth')->orderBy('position')->orderBy('name')
            ->get();

        return response()->json(['data' => $this->tree($all, null)]);
    }

    public function category(string $slug): JsonResponse
    {
        $category = Category::query()->active()->where('slug', $slug)->with('image')->first();
        if (! $category) {
            return $this->notFound('La categoría no existe o no está publicada.');
        }

        // Una subcategoría solo es visible si toda su rama está activa
        $ancestors = $category->ancestors();
        if ($ancestors->contains(fn (Category $a) => $a->status !== 'active')) {
            return $this->notFound('La categoría no existe o no está publicada.');
        }

        return response()->json(['data' => [
            ...$this->categoryCard($category),
            'description' => $category->description,
            'breadcrumbs' => $ancestors->push($category)
                ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug])->values()->all(),
            'children' => $category->children()->active()->with('image')->get()
                ->map(fn (Category $c) => $this->categoryCard($c))->all(),
        ]]);
    }

    public function brands(): JsonResponse
    {
        return response()->json(['data' => Brand::query()->active()->with('logo')
            ->orderBy('position')->orderBy('name')->get()
            ->map(fn (Brand $b) => $this->brandCard($b))->all()]);
    }

    public function brand(string $slug): JsonResponse
    {
        $brand = Brand::query()->active()->where('slug', $slug)->with('logo')->first();
        if (! $brand) {
            return $this->notFound('La marca no existe o no está publicada.');
        }

        return response()->json(['data' => [
            ...$this->brandCard($brand),
            'description' => $brand->description,
        ]]);
    }

    /** Bloques fijos de la portada (Fase 2). En la Fase 9 los reemplaza el constructor de páginas. */
    public function home(): JsonResponse
    {
        $card = fn (Product $p) => $this->presenter->card($p);

        return response()->json(['data' => [
            'categories' => Category::query()->active()->where('on_home', true)->with('image')
                ->orderBy('position')->limit(8)->get()
                ->map(fn (Category $c) => $this->categoryCard($c))->all(),
            'featured' => collect($this->search->search(['featured' => true, 'per_page' => 8])->items())->map($card)->all(),
            'newest' => collect($this->search->search(['sort' => 'newest', 'per_page' => 8])->items())->map($card)->all(),
            'on_sale' => collect($this->search->search(['on_sale' => true, 'per_page' => 8])->items())->map($card)->all(),
            'brands' => Brand::query()->active()->where('show_on_home', true)->with('logo')
                ->orderBy('position')->limit(12)->get()
                ->map(fn (Brand $b) => $this->brandCard($b))->all(),
        ]]);
    }

    private function tree($all, ?int $parentId): array
    {
        return $all->where('parent_id', $parentId)->values()
            ->map(fn (Category $c) => [
                ...$this->categoryCard($c),
                'children' => $this->tree($all, $c->id),
            ])->all();
    }

    private function categoryCard(Category $c): array
    {
        return [
            'name' => $c->name,
            'slug' => $c->slug,
            'image' => $c->image?->toPublicArray($c->name),
        ];
    }

    private function brandCard(Brand $b): array
    {
        return [
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => $b->logo?->toPublicArray($b->name),
        ];
    }

    /** Errores en formato application/problem+json (diseño técnico, sección 7.5). */
    private function notFound(string $detail): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'No encontrado',
            'status' => 404,
            'code' => 'not_found',
            'detail' => $detail,
        ], 404, ['Content-Type' => 'application/problem+json']);
    }
}
