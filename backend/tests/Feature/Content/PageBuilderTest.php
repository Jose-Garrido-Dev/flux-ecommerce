<?php

namespace Tests\Feature\Content;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\BlockSchema;
use App\Domain\Content\Services\MediaUsage;
use App\Domain\Content\Services\PageService;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\RelationManagers\VersionsRelationManager;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

/** Fase 9: páginas creadas y publicadas desde el panel sin código, versiones, restauración y vista previa en borrador. */
class PageBuilderTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private const API = '/api/storefront/v1';

    private PageService $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->pages = app(PageService::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('marketing');

        return $user;
    }

    private function media(): Media
    {
        return Media::query()->create(['disk' => 'public', 'path' => 'contenido/foto.jpg', 'mime' => 'image/jpeg', 'size' => 1000, 'width' => 1600, 'height' => 900, 'alt' => 'Vitrina', 'checksum' => str_repeat('c', 64)]);
    }

    public function test_a_page_is_created_edited_and_published_from_the_panel_without_code(): void
    {
        Bus::fake([RevalidateStorefront::class]);
        $this->actingAs($this->admin());
        $image = $this->media();

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Quiénes somos', 'type' => 'institutional', 'slug' => 'nosotros'])
            ->call('create')->assertHasNoFormErrors();
        $page = Page::query()->where('slug', 'nosotros')->firstOrFail();
        $this->assertSame(1, $page->versions()->count());
        $this->getJson(self::API.'/pages/nosotros')->assertNotFound();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'Hechos en Chile', 'style' => 'image', 'image' => $image->id, 'button_label' => 'Ver catálogo', 'button_link' => '/catalogo']],
                ['type' => 'rich_text', 'data' => ['title' => 'Nuestra historia', 'body' => "Empezamos en **2019**.\n\n<script>alert(1)</script>"]],
                ['type' => 'faq', 'data' => ['items' => [['question' => '¿Hacen envíos?', 'answer' => 'Sí, a todo Chile.']]]],
            ]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, $page->versions()->count());
        $this->getJson(self::API.'/pages/nosotros')->assertNotFound('Guardar no publica');

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->callAction('publish')->assertNotified('Página publicada');
        Bus::assertDispatched(RevalidateStorefront::class, fn ($job) => in_array('page:nosotros', $job->tags, true));

        $data = $this->getJson(self::API.'/pages/nosotros')->assertOk()->json('data');
        $this->assertSame('Quiénes somos', $data['title']);
        $this->assertSame(2, $data['version']);
        $this->assertSame(['hero', 'rich_text', 'faq'], array_column($data['blocks'], 'type'));
        $this->assertSame(['path' => 'contenido/foto.jpg', 'alt' => 'Vitrina', 'width' => 1600, 'height' => 900], $data['blocks'][0]['data']['image']);
        $html = $data['blocks'][1]['data']['html'];
        $this->assertStringContainsString('<strong>2019</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html, 'El HTML escrito a mano se muestra como texto');
        $this->assertStringContainsString('<p>Sí, a todo Chile.</p>', $data['blocks'][2]['data']['items'][0]['html']);
        $this->assertArrayNotHasKey('id', $data['blocks'][0]['data']['image'] ?? [], 'La tienda no recibe ids internos');
    }

    public function test_saving_creates_versions_restore_copies_and_versions_never_change(): void
    {
        $page = $this->pages->create(['title' => 'Envíos', 'slug' => 'envios'], [['type' => 'rich_text', 'data' => ['body' => 'Versión uno']]]);
        $this->pages->publish($page);
        $v2 = $this->pages->saveVersion($page, [['type' => 'rich_text', 'data' => ['body' => 'Versión dos']]]);
        $this->assertSame(2, $v2->number);
        $this->assertSame($v2->id, $this->pages->saveVersion($page, [['type' => 'rich_text', 'data' => ['body' => 'Versión dos']]])->id, 'Sin cambios no crea versión');

        // Publicado sigue siendo la versión 1 hasta publicar
        $this->assertStringContainsString('Versión uno', $this->getJson(self::API.'/pages/envios')->json('data.blocks.0.data.html'));
        $this->assertTrue($page->fresh()->hasUnpublishedChanges());

        // Restaurar la 1 crea la 3 con el mismo contenido; la 2 sigue intacta
        $v1 = $page->versions()->where('number', 1)->firstOrFail();
        $v3 = $this->pages->restore($page, $v1);
        $this->assertSame(3, $v3->number);
        $this->assertSame(1, $v3->restored_from);
        $this->assertSame($v1->blocks, $v3->blocks);
        $this->assertSame('Versión dos', $v2->fresh()->blocks[0]['data']['body']);

        $this->assertThrows(fn () => $v1->update(['note' => 'cambio']), LogicException::class);
        if (DB::getDriverName() === 'pgsql') {
            // Dentro de una transacción anidada (punto de guardado) para que el error no anule la transacción de la prueba
            $this->assertThrows(fn () => DB::transaction(fn () => DB::table('page_versions')->where('id', $v1->id)->update(['note' => 'x'])), QueryException::class, 'no se modifican');
        }

        // Publicar una versión anterior desde el historial
        $this->actingAs($admin = $this->admin());
        Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class])
            ->callTableAction('publishVersion', $v2)->assertNotified('Versión 2 publicada');
        $this->assertStringContainsString('Versión dos', $this->getJson(self::API.'/pages/envios')->json('data.blocks.0.data.html'));
        Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class])
            ->callTableAction('restore', $v2)->assertNotified('Versión 2 restaurada como versión 4');
        $this->assertSame(4, $page->versions()->max('number'));
    }

    public function test_draft_preview_with_a_signed_link_that_expires(): void
    {
        $page = $this->pages->create(['title' => 'Cyber', 'type' => 'landing', 'slug' => 'cyber'], [['type' => 'rich_text', 'data' => ['body' => 'Borrador secreto']]]);
        $this->getJson(self::API.'/pages/cyber')->assertNotFound();

        $token = $this->pages->previewToken($page);
        $response = $this->getJson(self::API."/preview/$token")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue($response->json('data.preview'));
        $this->assertStringContainsString('Borrador secreto', $response->json('data.blocks.0.data.html'));

        $this->getJson(self::API.'/preview/'.substr($token, 0, -2).'xx')->assertNotFound();
        [$payload] = explode('.', $token);
        $this->getJson(self::API.'/preview/'.$payload.'.'.str_repeat('a', 43))->assertNotFound();
        $this->travel(PageService::PREVIEW_MINUTES + 1)->minutes();
        $this->getJson(self::API."/preview/$token")->assertNotFound();
    }

    public function test_blocks_are_validated_before_saving(): void
    {
        $page = $this->pages->create(['title' => 'Prueba', 'slug' => 'prueba']);
        $invalid = [
            [['type' => 'no_existe', 'data' => []], 'Bloque 1: tipo de bloque desconocido.'],
            [['type' => 'hero', 'data' => []], 'Bloque 1 (Portada (hero)): «Titular» es obligatorio.'],
            [['type' => 'hero', 'data' => ['title' => 'Hola', 'button_link' => 'javascript:alert(1)']], 'debe ser una ruta de la tienda'],
            [['type' => 'hero', 'data' => ['title' => 'Hola', 'button_link' => '//malo.test']], 'debe ser una ruta de la tienda'],
            [['type' => 'hero', 'data' => ['title' => str_repeat('a', 121)]], 'admite hasta 120 caracteres'],
            [['type' => 'banner', 'data' => ['image' => 999999]], 'no existe en la biblioteca'],
            [['type' => 'products', 'data' => ['source' => 'category']], '«Categoría» es obligatorio'],
            [['type' => 'products', 'data' => ['source' => 'featured', 'limit' => 99]], 'debe estar entre 1 y 24'],
            [['type' => 'video', 'data' => ['url' => 'https://videos.example/x.mp4']], 'YouTube o Vimeo'],
            [['type' => 'faq', 'data' => ['items' => [['question' => '¿Algo?']]]], '«Preguntas» 1: «Respuesta» es obligatorio'],
            [['type' => 'countdown', 'data' => ['title' => 'Cyber', 'ends_at' => 'mañana tal vez']], 'no es una fecha válida'],
        ];
        foreach ($invalid as [$block, $message]) {
            try {
                $this->pages->saveVersion($page, [$block]);
                $this->fail('Debió rechazar: '.json_encode($block));
            } catch (ValidationException $e) {
                $this->assertSame(422, $e->status);
                $this->assertStringContainsString($message, collect($e->errors())->flatten()->implode(' '));
            }
        }
        $this->assertSame(1, $page->versions()->count());
        $this->assertThrows(fn () => $this->pages->publish($page), ValidationException::class, 'Agrega al menos un bloque');

        // Los campos desconocidos se descartan y los valores por defecto se completan
        $v = $this->pages->saveVersion($page, [['type' => 'products', 'data' => ['source' => 'newest', 'onclick' => 'x']]]);
        $this->assertSame(['source' => 'newest', 'limit' => 8, 'layout' => 'carousel'], $v->blocks[0]['data']);
    }

    public function test_slugs_and_the_single_home(): void
    {
        $this->assertThrows(fn () => $this->pages->create(['title' => 'Carrito', 'slug' => 'carrito']), ValidationException::class, 'la usa la tienda');
        $this->pages->create(['title' => 'Términos y condiciones']);
        $this->assertTrue(Page::query()->where('slug', 'terminos-y-condiciones')->exists());
        $this->assertThrows(fn () => $this->pages->create(['title' => 'Otra', 'slug' => 'terminos-y-condiciones']), ValidationException::class, 'Ya existe una página');
        $this->pages->create(['title' => 'Portada', 'type' => 'home']);
        $this->assertThrows(fn () => $this->pages->create(['title' => 'Portada 2', 'type' => 'home']), ValidationException::class, 'Ya existe una portada');
    }

    public function test_home_page_and_dynamic_blocks(): void
    {
        $this->assertNull($this->getJson(self::API.'/home')->json('data.page'), 'Sin portada publicada la tienda usa la fija');
        $taza = $this->product('Taza', [['price' => 9990]]);
        $taza->update(['featured' => true]);
        $category = Category::query()->create(['name' => 'Cocina', 'slug' => 'cocina', 'on_home' => true]);
        $home = $this->pages->create(['title' => 'Portada', 'type' => 'home'], [
            ['type' => 'products', 'data' => ['title' => 'Destacados', 'source' => 'featured']],
            ['type' => 'products', 'data' => ['title' => 'Vacío', 'source' => 'on_sale']],
            ['type' => 'categories', 'data' => ['title' => 'Categorías']],
            ['type' => 'countdown', 'data' => ['title' => 'Terminó', 'ends_at' => now()->subDay()->toIso8601String()]],
            ['type' => 'countdown', 'data' => ['title' => 'Cyber', 'ends_at' => now()->addDay()->toIso8601String()]],
            ['type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
        ]);
        $this->pages->publish($home);

        $page = $this->getJson(self::API.'/home')->assertOk()->json('data.page');
        $this->assertSame(['products', 'categories', 'countdown', 'video'], array_column($page['blocks'], 'type'), 'Bloques sin contenido o vencidos se omiten');
        $this->assertSame('Taza', $page['blocks'][0]['data']['products'][0]['name']);
        $this->assertSame('cocina', $page['blocks'][1]['data']['categories'][0]['slug']);
        $this->assertSame('Cyber', $page['blocks'][2]['data']['title']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $page['blocks'][3]['data']['embed']);
        $this->getJson(self::API.'/pages/portada')->assertNotFound('La portada no se sirve como página suelta');

        $category->update(['status' => 'archived']);
        $this->assertSame(['products', 'countdown', 'video'], array_column($this->getJson(self::API.'/home')->json('data.page.blocks'), 'type'));
    }

    public function test_an_image_used_by_a_page_cannot_be_deleted_from_the_library(): void
    {
        $image = $this->media();
        $page = $this->pages->create(['title' => 'Nosotros', 'slug' => 'nosotros'], [['type' => 'banner', 'data' => ['image' => $image->id]]]);
        $this->assertSame(['Página: Nosotros'], app(MediaUsage::class)->for($image));
        $this->assertSame([$image->id], BlockSchema::mediaIds($page->latestVersion()->first()->blocks));
    }

    public function test_the_block_schema_is_shared_with_the_storefront(): void
    {
        $copy = base_path('../storefront/src/lib/cms-blocks.json');
        if (! is_file($copy)) {
            $this->markTestSkipped('El storefront no está junto al backend en este entorno.');
        }
        $this->assertSame(file_get_contents(resource_path('cms/blocks.json')), file_get_contents($copy), 'Copia storefront/src/lib/cms-blocks.json desde backend/resources/cms/blocks.json');
    }

    public function test_publishing_asks_the_storefront_to_refresh_its_cache(): void
    {
        config(['flux.revalidate_secret' => 'secreto', 'flux.storefront_internal_url' => 'http://tienda.interna:3000']);
        Http::fake(['tienda.interna:3000/*' => Http::response(['revalidated' => []])]);
        $page = $this->pages->create(['title' => 'Portada', 'type' => 'home'], [['type' => 'rich_text', 'data' => ['body' => 'Hola']]]);
        $this->pages->publish($page);
        Http::assertSent(fn ($request) => $request->url() === 'http://tienda.interna:3000/api/revalidate'
            && $request->header('x-revalidate-secret')[0] === 'secreto'
            && in_array('home', $request['tags'], true));
    }
}
