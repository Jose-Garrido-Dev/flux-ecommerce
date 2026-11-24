<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Blog público: artículos publicados (una fecha futura = programado, todavía no se ve). */
class BlogController extends StorefrontController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:1000'], 'category' => ['nullable', 'string', 'max:100']]);
        $category = isset($data['category']) ? BlogCategory::query()->where('slug', $data['category'])->first() : null;
        $posts = BlogPost::query()->visible()->with(['category', 'featuredMedia'])
            ->when(isset($data['category']), fn ($q) => $q->where('category_id', $category?->id ?? 0))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(12, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'data' => collect($posts->items())->map(fn (BlogPost $p) => $this->card($p))->all(),
            'meta' => ['current_page' => $posts->currentPage(), 'last_page' => $posts->lastPage(), 'total' => $posts->total()],
            'categories' => BlogCategory::query()->whereHas('posts', fn ($q) => $q->visible())->orderBy('position')->orderBy('name')->get(['name', 'slug'])->toArray(),
            'category' => $category ? ['name' => $category->name, 'slug' => $category->slug] : null,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()->visible()->where('slug', $slug)->with(['category', 'featuredMedia'])->first();
        if (! $post) {
            return $this->problem(404, 'not_found', 'El artículo no existe o no está publicado.');
        }
        $related = BlogPost::query()->visible()->whereKeyNot($post->id)->with(['category', 'featuredMedia'])
            ->when($post->category_id, fn ($q) => $q->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$post->category_id]))
            ->orderByDesc('published_at')->limit(3)->get();

        return response()->json(['data' => [
            ...$this->card($post),
            'html' => (string) Str::markdown($post->body, ['html_input' => 'escape', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]),
            'seo' => ['title' => $post->seo_title ?: $post->title, 'description' => $post->seo_description ?: $post->excerpt],
            'updated_at' => $post->updated_at?->toIso8601String(),
            'related' => $related->map(fn (BlogPost $p) => $this->card($p))->all(),
        ]]);
    }

    private function card(BlogPost $post): array
    {
        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt ?: Str::limit(trim(preg_replace('/[#*_>`\[\]()!-]+/', ' ', $post->body)), 180),
            'category' => $post->category ? ['name' => $post->category->name, 'slug' => $post->category->slug] : null,
            'image' => $post->featuredMedia?->toPublicArray($post->title),
            'author' => $post->author_name,
            'published_at' => $post->published_at?->toIso8601String(),
            'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($post->body)) / 200)),
        ];
    }
}
