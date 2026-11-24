<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\RedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** SEO: las rutas del sitemap (la tienda arma el XML) y las redirecciones. */
class SeoController extends StorefrontController
{
    public const SITEMAP_LIMIT = 50000;

    /** Rutas públicas con su última modificación. Solo contenido visible. */
    public function sitemap(): JsonResponse
    {
        $iso = fn ($date) => $date?->toIso8601String();
        $urls = collect([['path' => '/', 'lastmod' => null, 'priority' => 1.0], ['path' => '/catalogo', 'lastmod' => null, 'priority' => 0.8]]);
        $urls = $urls->concat(Category::query()->active()->get(['slug', 'updated_at'])->map(fn ($c) => ['path' => '/categoria/'.$c->slug, 'lastmod' => $iso($c->updated_at), 'priority' => 0.7]));
        $urls = $urls->concat(Brand::query()->active()->get(['slug', 'updated_at'])->map(fn ($b) => ['path' => '/marca/'.$b->slug, 'lastmod' => $iso($b->updated_at), 'priority' => 0.5]));
        $urls = $urls->concat(Page::query()->published()->where('type', '!=', 'home')->get(['slug', 'type', 'published_at'])->map(fn ($p) => ['path' => '/'.$p->slug, 'lastmod' => $iso($p->published_at), 'priority' => 0.5]));
        if ($latest = BlogPost::query()->visible()->max('published_at')) {
            $urls->push(['path' => '/blog', 'lastmod' => Carbon::parse($latest)->toIso8601String(), 'priority' => 0.5]);
        }
        $urls = $urls->concat(BlogPost::query()->visible()->get(['slug', 'updated_at'])->map(fn ($p) => ['path' => '/blog/'.$p->slug, 'lastmod' => $iso($p->updated_at), 'priority' => 0.5]));
        $urls = $urls->concat(Product::query()->visible()->orderBy('id')->limit(self::SITEMAP_LIMIT)->get(['slug', 'updated_at'])->map(fn ($p) => ['path' => '/producto/'.$p->slug, 'lastmod' => $iso($p->updated_at), 'priority' => 0.6]));

        return response()->json(['data' => $urls->unique('path')->take(self::SITEMAP_LIMIT)->values()->all()]);
    }

    public function redirects(RedirectService $redirects): JsonResponse
    {
        return response()->json(['data' => $redirects->all()]);
    }

    public function hit(Request $request, RedirectService $redirects): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:300']]);

        return response()->json(['data' => ['counted' => $redirects->hit($data['path'])]]);
    }
}
