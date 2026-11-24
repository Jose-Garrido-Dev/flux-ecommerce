<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductPresenter;
use App\Domain\Catalog\Services\ProductSearch;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Arma una página para la tienda: resuelve imágenes, productos, categorías y marcas, convierte el
 * texto con formato a HTML seguro y quita lo que no corresponde mostrar (una cuenta regresiva vencida,
 * un carrusel sin productos visibles). La tienda nunca recibe ids internos de la biblioteca.
 */
class PagePresenter
{
    private Collection $media;

    public function __construct(private readonly ProductSearch $search, private readonly ProductPresenter $products) {}

    public function present(Page $page, PageVersion $version): array
    {
        return [
            'title' => $page->title,
            'slug' => $page->slug,
            'type' => $page->type,
            'seo' => ['title' => $page->seo_title ?: $page->title, 'description' => $page->seo_description],
            'version' => $version->number,
            'blocks' => $this->blocks($version->blocks),
        ];
    }

    public function blocks(array $blocks): array
    {
        $this->media = Media::query()->whereIn('id', BlockSchema::mediaIds($blocks))->get()->keyBy('id');
        $out = [];
        foreach ($blocks as $i => $block) {
            $data = $this->resolve($block['type'], $block['data'] ?? []);
            if ($data !== null) {
                $out[] = ['id' => $block['type'].'-'.($i + 1), 'type' => $block['type'], 'data' => $data];
            }
        }

        return $out;
    }

    private function resolve(string $type, array $d): ?array
    {
        return match ($type) {
            'hero' => [
                ...$this->pick($d, ['title', 'subtitle', 'button_label', 'button_link', 'style']),
                'image' => $this->image($d['image'] ?? null, $d['title'] ?? ''),
                'products' => ($d['style'] ?? 'field') === 'field' ? $this->cards(['featured' => true], 6) ?: $this->cards(['sort' => 'newest'], 6) : [],
            ],
            'banner' => ($image = $this->image($d['image'] ?? null, $d['title'] ?? '')) ? [
                ...$this->pick($d, ['title', 'text', 'button_label', 'link']), 'image' => $image,
            ] : null,
            'image_text' => [
                ...$this->pick($d, ['title', 'button_label', 'link', 'image_side']),
                'html' => $this->markdown($d['text'] ?? ''),
                'image' => $this->image($d['image'] ?? null, $d['title'] ?? ''),
            ],
            'rich_text' => ['title' => $d['title'] ?? null, 'html' => $this->markdown($d['body'] ?? '')],
            'products' => $this->productsBlock($d),
            'categories' => $this->categoriesBlock($d),
            'brands' => $this->brandsBlock($d),
            'marquee' => ['items' => array_values($d['items'] ?? [])],
            'countdown' => isset($d['ends_at']) && Carbon::parse($d['ends_at'])->isFuture()
                ? $this->pick($d, ['title', 'text', 'ends_at', 'button_label', 'link']) : null,
            'testimonials' => ['title' => $d['title'] ?? null, 'items' => array_values($d['items'] ?? [])],
            'faq' => ['title' => $d['title'] ?? null, 'items' => array_map(fn ($q) => [
                'question' => $q['question'], 'html' => $this->markdown($q['answer'] ?? ''),
            ], $d['items'] ?? [])],
            'video' => ($embed = VideoEmbed::parse((string) ($d['url'] ?? ''))) ? ['title' => $d['title'] ?? null, ...$embed] : null,
            default => null,
        };
    }

    private function productsBlock(array $d): ?array
    {
        $limit = (int) ($d['limit'] ?? 8);
        $cards = match ($d['source'] ?? 'featured') {
            'newest' => $this->cards(['sort' => 'newest'], $limit),
            'on_sale' => $this->cards(['on_sale' => true], $limit),
            'category' => ($c = Category::query()->active()->find($d['category'] ?? 0)) ? $this->cards(['category' => $c], $limit) : [],
            'brand' => ($b = Brand::query()->active()->find($d['brand'] ?? 0)) ? $this->cards(['brand' => $b], $limit) : [],
            'manual' => $this->manual($d['product_ids'] ?? []),
            default => $this->cards(['featured' => true], $limit),
        };
        if ($cards === []) {
            return null;
        }

        return [...$this->pick($d, ['title', 'layout', 'link_label', 'link']), 'products' => $cards];
    }

    private function categoriesBlock(array $d): ?array
    {
        $ids = $d['category_ids'] ?? [];
        $query = Category::query()->active()->with('image');
        $categories = $ids === []
            ? $query->where('on_home', true)->orderBy('position')->limit(8)->get()
            : $query->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values();
        if ($categories->isEmpty()) {
            return null;
        }

        return ['title' => $d['title'] ?? null, 'categories' => $categories->map(fn (Category $c) => [
            'name' => $c->name, 'slug' => $c->slug, 'image' => $c->image?->toPublicArray($c->name),
        ])->all()];
    }

    private function brandsBlock(array $d): ?array
    {
        $ids = $d['brand_ids'] ?? [];
        $query = Brand::query()->active()->with('logo');
        $brands = $ids === []
            ? $query->where('show_on_home', true)->orderBy('position')->limit(24)->get()
            : $query->whereIn('id', $ids)->get()->sortBy(fn ($b) => array_search($b->id, $ids))->values();
        if ($brands->isEmpty()) {
            return null;
        }

        return ['title' => $d['title'] ?? null, 'brands' => $brands->map(fn (Brand $b) => [
            'name' => $b->name, 'slug' => $b->slug, 'logo' => $b->logo?->toPublicArray($b->name),
        ])->all()];
    }

    private function cards(array $criteria, int $limit): array
    {
        return collect($this->search->search([...$criteria, 'per_page' => $limit])->items())
            ->map(fn (Product $p) => $this->products->card($p))->all();
    }

    private function manual(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Product::query()->visible()->whereIn('id', $ids)->with(ProductPresenter::CARD_RELATIONS)->get()
            ->sortBy(fn (Product $p) => array_search($p->id, $ids))
            ->map(fn (Product $p) => $this->products->card($p))->values()->all();
    }

    private function image(mixed $id, string $alt): ?array
    {
        $media = is_numeric($id) ? $this->media->get((int) $id) : null;

        return $media?->toPublicArray($alt);
    }

    /** Markdown a HTML: el HTML escrito a mano se muestra como texto y no se permiten enlaces javascript:. */
    private function markdown(string $text): string
    {
        return $text === '' ? '' : (string) Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]);
    }

    private function pick(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys)) + array_fill_keys($keys, null);
    }
}
