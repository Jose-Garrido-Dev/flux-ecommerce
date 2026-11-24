<?php

namespace Tests\Feature\Content;

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Theme;
use App\Domain\Content\Services\PageService;
use App\Domain\Content\Services\ThemePresets;
use App\Domain\Content\Services\ThemeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThemesAndMenusTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/storefront/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_six_themes_plus_the_default_exist_and_all_are_readable(): void
    {
        $this->assertEqualsCanonicalizing(['lapislazuli', 'minimalista', 'fashion', 'streetwear', 'tecnologia', 'lujo', 'hogar'], Theme::query()->pluck('code')->all());
        foreach (ThemePresets::all() as $code => $preset) {
            $this->assertSame([], ThemePresets::contrastProblems($preset['tokens']), "El tema $code no cumple el contraste mínimo");
        }
        $this->assertSame('lapislazuli', $this->getJson(self::API.'/store')->json('data.theme.code'));
    }

    public function test_activating_a_theme_changes_the_store_and_only_one_is_active(): void
    {
        $service = app(ThemeService::class);
        $service->activate(Theme::query()->where('code', 'lujo')->firstOrFail());
        $this->assertSame(1, Theme::query()->where('is_active', true)->count());
        $theme = $this->getJson(self::API.'/store')->json('data.theme');
        $this->assertSame('lujo', $theme['code']);
        $this->assertSame('serif', $theme['font_display']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $theme['light']['accent']);
    }

    public function test_color_overrides_are_rejected_when_text_would_not_be_readable(): void
    {
        $service = app(ThemeService::class);
        $theme = Theme::active();
        $this->assertThrows(fn () => $service->saveOverrides($theme, ['accent' => '#ffffff', 'accent-fg' => '#fefefe']), ValidationException::class, 'no se lee bien');
        $this->assertThrows(fn () => $service->saveOverrides($theme, ['accent' => 'red']), ValidationException::class, '#rrggbb');
        $service->saveOverrides($theme, ['accent' => '#0b3d91', 'accent-fg' => '#ffffff', 'bg' => '#000000']);
        $theme = $this->getJson(self::API.'/store')->json('data.theme');
        $this->assertSame('#0b3d91', $theme['light']['accent']);
        $this->assertNotSame('#000000', $theme['light']['bg'], 'Solo se pueden ajustar los colores permitidos');
    }

    public function test_menus_skip_links_whose_target_is_not_visible(): void
    {
        $active = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $hidden = Category::query()->create(['name' => 'Oculta', 'slug' => 'oculta', 'status' => 'archived']);
        $pages = app(PageService::class);
        $published = $pages->create(['title' => 'Nosotros', 'slug' => 'nosotros'], [['type' => 'rich_text', 'data' => ['body' => 'Hola']]]);
        $pages->publish($published);
        $draft = $pages->create(['title' => 'Borrador', 'slug' => 'borrador']);

        $header = Menu::for('header');
        $parent = $header->items()->create(['label' => 'Tienda', 'link_type' => 'catalog', 'position' => 0]);
        $header->items()->create(['label' => 'Ropa', 'link_type' => 'category', 'link_target' => $active->id, 'parent_id' => $parent->id, 'position' => 0]);
        $header->items()->create(['label' => 'Oculta', 'link_type' => 'category', 'link_target' => $hidden->id, 'parent_id' => $parent->id, 'position' => 1]);
        $header->items()->create(['label' => 'Nosotros', 'link_type' => 'page', 'link_target' => $published->id, 'position' => 1]);
        $header->items()->create(['label' => 'Borrador', 'link_type' => 'page', 'link_target' => $draft->id, 'position' => 2]);
        $header->items()->create(['label' => 'Malo', 'link_type' => 'url', 'link_target' => 'javascript:alert(1)', 'position' => 3]);
        Menu::for('footer')->items()->create(['label' => 'Instagram', 'link_type' => 'url', 'link_target' => 'https://instagram.com/tienda', 'position' => 0]);

        $data = $this->getJson(self::API.'/menus')->assertOk()->json('data');
        $this->assertSame([
            ['label' => 'Tienda', 'href' => '/catalogo', 'children' => [['label' => 'Ropa', 'href' => '/categoria/ropa', 'children' => []]]],
            ['label' => 'Nosotros', 'href' => '/nosotros', 'children' => []],
        ], $data['header']);
        $this->assertSame('https://instagram.com/tienda', $data['footer'][0]['href']);
    }
}
