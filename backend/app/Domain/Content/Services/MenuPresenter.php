<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Models\Page;

/** Menús para la tienda con los enlaces resueltos. Un enlace a algo que ya no está visible se omite. */
class MenuPresenter
{
    /** @return array<string, list<array{label: string, href: string, children: list<array{label: string, href: string}>}>> */
    public function all(): array
    {
        $menus = Menu::query()->with('items')->get()->keyBy('location');
        $out = [];
        foreach (array_keys(Menu::LOCATIONS) as $location) {
            $items = $menus->get($location)?->items ?? collect();
            $out[$location] = $this->tree($items, null, 0);
        }

        return $out;
    }

    private function tree($items, ?int $parentId, int $depth): array
    {
        return $items->where('parent_id', $parentId)->sortBy('position')->map(function (MenuItem $item) use ($items, $depth) {
            $href = $this->href($item);
            if ($href === null) {
                return null;
            }

            return ['label' => $item->label, 'href' => $href, 'children' => $depth === 0 ? $this->tree($items, $item->id, 1) : []];
        })->filter()->values()->all();
    }

    public function href(MenuItem $item): ?string
    {
        $target = (string) $item->link_target;

        return match ($item->link_type) {
            'catalog' => '/catalogo',
            'category' => ($c = Category::query()->active()->find((int) $target)) ? '/categoria/'.$c->slug : null,
            'brand' => ($b = Brand::query()->active()->find((int) $target)) ? '/marca/'.$b->slug : null,
            'page' => ($p = Page::query()->published()->find((int) $target)) ? $p->storefrontPath() : null,
            'url' => BlockSchema::validLink($target) ? $target : null,
            default => null,
        };
    }
}
