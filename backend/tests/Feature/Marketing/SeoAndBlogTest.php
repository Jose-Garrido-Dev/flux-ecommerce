<?php

namespace Tests\Feature\Marketing;

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\Redirect;
use App\Domain\Content\Services\PageService;
use App\Domain\Content\Services\RedirectService;
use App\Domain\System\Services\SettingsRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

class SeoAndBlogTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private const API = '/api/storefront/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_sitemap_lists_only_visible_content(): void
    {
        $visible = $this->product('Polera', [['price' => 9990]]);
        $draft = $this->product('Borrador', [['price' => 9990]]);
        $draft->update(['status' => 'draft']);
        Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        Category::query()->create(['name' => 'Oculta', 'slug' => 'oculta', 'status' => 'archived']);
        $pages = app(PageService::class);
        $pages->publish($pages->create(['title' => 'Nosotros', 'slug' => 'nosotros'], [['type' => 'rich_text', 'data' => ['body' => 'Hola']]]));
        $pages->create(['title' => 'Borrador', 'slug' => 'pagina-borrador']);
        BlogPost::query()->create(['title' => 'Guía de tallas', 'slug' => 'guia-de-tallas', 'body' => 'Texto', 'status' => 'published', 'published_at' => now()->subDay()]);
        BlogPost::query()->create(['title' => 'Programado', 'slug' => 'programado', 'body' => 'Texto', 'status' => 'published', 'published_at' => now()->addDay()]);

        $paths = array_column($this->getJson(self::API.'/seo/sitemap')->assertOk()->json('data'), 'path');
        foreach (['/', '/catalogo', '/categoria/ropa', '/nosotros', '/blog', '/blog/guia-de-tallas', '/producto/'.$visible->slug] as $path) {
            $this->assertContains($path, $paths);
        }
        foreach (['/categoria/oculta', '/pagina-borrador', '/blog/programado', '/producto/'.$draft->slug] as $path) {
            $this->assertNotContains($path, $paths);
        }
        $this->assertSame(count($paths), count(array_unique($paths)));
    }

    public function test_redirects_validate_loops_flatten_chains_and_count_hits(): void
    {
        $service = app(RedirectService::class);
        $a = $service->save(null, 'https://mitienda.cl/tienda/polera-negra/', '/producto/polera');
        $this->assertSame('/tienda/polera-negra', $a->from_path, 'Se guarda solo la ruta, sin dominio ni barra final');

        $this->assertThrows(fn () => $service->save(null, '/producto/polera', '/tienda/polera-negra'), ValidationException::class, 'ciclo');
        $this->assertThrows(fn () => $service->save(null, '/api/storefront', '/'), ValidationException::class, 'la usa el sistema');
        $this->assertThrows(fn () => $service->save(null, '/x', 'javascript:alert(1)'), ValidationException::class, 'ruta de la tienda');
        $this->assertThrows(fn () => $service->save(null, '/tienda/polera-negra', '/catalogo'), ValidationException::class, 'Ya existe');

        // Cadena: /producto/polera → /producto/polera-nueva; la antigua apunta directo al final
        $service->save(null, '/producto/polera', '/producto/polera-nueva');
        $this->assertSame('/producto/polera-nueva', $a->fresh()->to_path);

        $this->assertSame([
            ['from' => '/producto/polera', 'to' => '/producto/polera-nueva', 'code' => 301],
            ['from' => '/tienda/polera-negra', 'to' => '/producto/polera-nueva', 'code' => 301],
        ], $this->getJson(self::API.'/seo/redirects')->json('data'));

        $this->postJson(self::API.'/seo/redirects/hit', ['path' => '/tienda/polera-negra'])->assertJsonPath('data.counted', true);
        $this->postJson(self::API.'/seo/redirects/hit', ['path' => '/no-existe'])->assertJsonPath('data.counted', false);
        $this->assertSame(1, $a->fresh()->hits);
    }

    public function test_changing_a_slug_creates_an_automatic_redirect(): void
    {
        $product = $this->product('Polera', [['price' => 9990]]);
        $old = $product->slug;
        $product->update(['slug' => 'polera-algodon']);
        $this->assertDatabaseHas('redirects', ['from_path' => "/producto/$old", 'to_path' => '/producto/polera-algodon', 'is_automatic' => true, 'code' => 301]);

        // Volver al nombre original: la redirección que apuntaba ahí se elimina, sin ciclos
        $product->update(['slug' => $old]);
        $this->assertDatabaseMissing('redirects', ['from_path' => "/producto/$old"]);
        $this->assertDatabaseHas('redirects', ['from_path' => '/producto/polera-algodon', 'to_path' => "/producto/$old"]);

        $category = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $category->update(['slug' => 'vestuario']);
        $this->assertSame('/categoria/vestuario', Redirect::query()->where('from_path', '/categoria/ropa')->value('to_path'));

        $pages = app(PageService::class);
        $draft = $pages->create(['title' => 'Borrador', 'slug' => 'borrador']);
        $draft->update(['slug' => 'otro']);
        $this->assertDatabaseMissing('redirects', ['from_path' => '/borrador']); // Una página nunca publicada no deja redirección
    }

    public function test_blog_only_shows_published_posts_with_safe_html(): void
    {
        $news = BlogCategory::query()->create(['name' => 'Novedades', 'slug' => 'novedades']);
        $post = BlogPost::query()->create([
            'title' => 'Cómo elegir tu talla', 'slug' => 'como-elegir-tu-talla', 'body' => "## Medidas\n\nMide tu **pecho**.\n\n<script>alert(1)</script>",
            'category_id' => $news->id, 'author_name' => 'Equipo Flux', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
        BlogPost::query()->create(['title' => 'Borrador', 'slug' => 'borrador', 'body' => 'x', 'status' => 'draft']);
        BlogPost::query()->create(['title' => 'Futuro', 'slug' => 'futuro', 'body' => 'x', 'status' => 'published', 'published_at' => now()->addDay()]);

        $list = $this->getJson(self::API.'/blog/posts')->assertOk();
        $this->assertSame(['como-elegir-tu-talla'], array_column($list->json('data'), 'slug'));
        $this->assertSame([['name' => 'Novedades', 'slug' => 'novedades']], $list->json('categories'));
        $this->assertSame([], $this->getJson(self::API.'/blog/posts?category=otra')->json('data'));

        $data = $this->getJson(self::API.'/blog/posts/como-elegir-tu-talla')->assertOk()->json('data');
        $this->assertStringContainsString('<strong>pecho</strong>', $data['html']);
        $this->assertStringNotContainsString('<script>', $data['html']);
        $this->assertSame('Equipo Flux', $data['author']);
        $this->getJson(self::API.'/blog/posts/borrador')->assertNotFound();
        $this->getJson(self::API.'/blog/posts/futuro')->assertNotFound();

        $post->update(['slug' => 'guia-de-tallas']);
        $this->assertSame('/blog/guia-de-tallas', Redirect::query()->where('from_path', '/blog/como-elegir-tu-talla')->value('to_path'));
    }

    public function test_whatsapp_button_settings(): void
    {
        $this->assertNull($this->getJson(self::API.'/store')->json('data.whatsapp'));
        app(SettingsRepository::class)->putMany('whatsapp', ['enabled' => true, 'phone' => '+56 9 1234 5678', 'message' => 'Hola, tengo una consulta', 'position' => 'left', 'pages' => 'product']);
        $this->assertSame([
            'phone' => '56912345678', 'message' => 'Hola, tengo una consulta', 'product_message' => '', 'position' => 'left', 'pages' => 'product', 'label' => 'Escríbenos por WhatsApp',
        ], $this->getJson(self::API.'/store')->json('data.whatsapp'));
        app(SettingsRepository::class)->putMany('whatsapp', ['phone' => 'abc']);
        $this->assertNull($this->getJson(self::API.'/store')->json('data.whatsapp'), 'Sin un teléfono válido no se muestra');
    }
}
