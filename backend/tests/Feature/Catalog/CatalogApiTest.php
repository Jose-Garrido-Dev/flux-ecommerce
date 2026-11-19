<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private const URL = '/api/storefront/v1/catalog/products';

    public function test_only_published_products_are_listed(): void
    {
        $this->product('Visible', [['price' => 1000]]);
        $this->product('Borrador', [['price' => 1000]], ['status' => ProductStatus::Draft]);
        $this->product('Programado', [['price' => 1000]], ['published_at' => now()->addDay()]);
        $this->product('Archivado', [['price' => 1000]], ['status' => ProductStatus::Archived]);
        $this->product('Sin variantes activas', [['price' => 1000, 'status' => 'inactive']]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible')
            ->assertJsonPath('meta.total', 1);

        $this->getJson(self::URL.'/borrador')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson(self::URL.'/programado')->assertNotFound();
    }

    public function test_never_exposes_costs_or_internal_fields(): void
    {
        $p = $this->product('Polera', [['price' => 12990]]);
        $p->variants->first()->prices()->update(['cost_amount' => 4000]);

        $body = $this->getJson(self::URL.'/polera')->assertOk()->getContent();

        $this->assertStringNotContainsString('cost', $body);
        $this->assertStringNotContainsString('4000', $body);
        $this->assertStringNotContainsString('"status"', $body);
    }

    public function test_price_shows_active_sale_and_minimum_across_variants(): void
    {
        $this->attribute('talla', ['m', 'xl']);
        $this->product('Polerón', [
            ['price' => 30000, 'options' => ['talla' => 'm'], 'sale' => 24000],
            ['price' => 32000, 'options' => ['talla' => 'xl']],
        ]);

        $this->getJson(self::URL)
            ->assertJsonPath('data.0.price.amount', 24000)
            ->assertJsonPath('data.0.price.compare_at', 30000)
            ->assertJsonPath('data.0.price.discount_percent', 20)
            ->assertJsonPath('data.0.price.varies', true);
    }

    public function test_expired_sale_is_not_applied(): void
    {
        $p = $this->product('Taza', [['price' => 9990, 'sale' => 7990]]);
        $p->variants->first()->prices()->update([
            'sale_starts_at' => now()->subDays(5),
            'sale_ends_at' => now()->subDay(),
        ]);

        $this->getJson(self::URL.'/taza')
            ->assertJsonPath('data.price.amount', 9990)
            ->assertJsonPath('data.price.compare_at', null);
    }

    public function test_filters_combine_or_within_attribute_and_and_across(): void
    {
        $this->attribute('color', ['rojo', 'azul', 'verde']);
        $this->attribute('talla', ['s', 'm']);
        $this->product('A', [['price' => 1000, 'options' => ['color' => 'rojo', 'talla' => 's']]]);
        $this->product('B', [['price' => 1000, 'options' => ['color' => 'azul', 'talla' => 'm']]]);
        $this->product('C', [['price' => 1000, 'options' => ['color' => 'verde', 'talla' => 's']]]);

        $names = fn ($r) => collect($r->json('data'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['A', 'B'], $names($this->getJson(self::URL.'?filter[color]=rojo,azul')));
        $this->assertSame(['A'], $names($this->getJson(self::URL.'?filter[color]=rojo,azul&filter[talla]=s')));
        // Un valor inexistente no devuelve todo el catálogo
        $this->assertSame([], $names($this->getJson(self::URL.'?filter[color]=morado')));
        // Un parámetro que no es atributo (p. ej. campañas) se ignora
        $this->assertSame(['A', 'B', 'C'], $names($this->getJson(self::URL.'?filter[utm_source]=instagram')));
    }

    public function test_facets_count_products_and_ignore_own_filter(): void
    {
        $this->attribute('color', ['rojo', 'azul']);
        $this->product('A', [['price' => 1000, 'options' => ['color' => 'rojo']], ['price' => 1000, 'options' => ['color' => 'azul']]]);
        $this->product('B', [['price' => 1000, 'options' => ['color' => 'azul']]]);

        $facet = collect($this->getJson(self::URL.'?filter[color]=rojo')->json('facets'))->firstWhere('code', 'color');
        $counts = collect($facet['values'])->pluck('count', 'value')->all();

        $this->assertSame(['rojo' => 1, 'azul' => 2], $counts);
        $this->assertTrue(collect($facet['values'])->firstWhere('value', 'rojo')['selected']);
    }

    public function test_sort_by_effective_price(): void
    {
        $this->product('Cara', [['price' => 50000]]);
        $this->product('Oferta', [['price' => 60000, 'sale' => 10000]]);
        $this->product('Media', [['price' => 20000]]);

        $asc = collect($this->getJson(self::URL.'?sort=price_asc')->json('data'))->pluck('name')->all();
        $desc = collect($this->getJson(self::URL.'?sort=price_desc')->json('data'))->pluck('name')->all();

        $this->assertSame(['Oferta', 'Media', 'Cara'], $asc);
        $this->assertSame(['Cara', 'Media', 'Oferta'], $desc);
    }

    public function test_price_range_filter_uses_effective_price(): void
    {
        $this->product('Cara', [['price' => 50000]]);
        $this->product('Oferta', [['price' => 60000, 'sale' => 10000]]);

        $this->getJson(self::URL.'?max_price=20000')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Oferta');
    }

    public function test_category_includes_subcategories(): void
    {
        $ropa = $this->category('Ropa');
        $poleras = $this->category('Poleras', $ropa);
        $hogar = $this->category('Hogar');
        $this->product('Polera', [['price' => 1000]], ['primary_category_id' => $poleras->id]);
        $this->product('Taza', [['price' => 1000]], ['primary_category_id' => $hogar->id]);

        $this->getJson(self::URL.'?category=ropa')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Polera');

        $this->getJson(self::URL.'?category=no-existe')->assertNotFound();

        $this->getJson('/api/storefront/v1/catalog/categories/poleras')
            ->assertOk()
            ->assertJsonPath('data.breadcrumbs.0.slug', 'ropa')
            ->assertJsonPath('data.breadcrumbs.1.slug', 'poleras');
    }

    public function test_hidden_parent_hides_subcategory(): void
    {
        $ropa = $this->category('Ropa');
        $this->category('Poleras', $ropa);
        $ropa->update(['status' => 'inactive']);

        $this->getJson('/api/storefront/v1/catalog/categories/poleras')->assertNotFound();
        $this->getJson('/api/storefront/v1/catalog/categories')->assertJsonCount(0, 'data');
    }

    public function test_text_search_matches_name_and_sku_and_escapes_wildcards(): void
    {
        $this->product('Polera Esencial', [['price' => 1000]]);
        $this->product('Taza 100% cerámica', [['price' => 1000]]);

        $this->getJson(self::URL.'?q=esencial')->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?q=POLERAESENCIAL-0')->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?q=100%25')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Taza 100% cerámica');
        $this->getJson(self::URL.'?q=%25')->assertJsonCount(1, 'data');
    }

    public function test_product_detail_has_options_variants_and_sanitized_description(): void
    {
        $this->attribute('color', ['rojo', 'azul']);
        $p = $this->product('Polera', [
            ['price' => 1000, 'options' => ['color' => 'rojo']],
            ['price' => 1200, 'options' => ['color' => 'azul']],
        ]);
        $p->update(['description' => '<p onclick="alert(1)">Hola <script>alert(2)</script><a href="javascript:x">link</a></p>']);

        $res = $this->getJson(self::URL.'/polera')->assertOk();

        $res->assertJsonPath('data.options.0.code', 'color')
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.1.options.color', 'azul')
            ->assertJsonPath('data.variants.1.price.amount', 1200)
            ->assertJsonPath('data.tax.label', 'IVA incluido');

        $html = $res->json('data.description_html');
        $this->assertSame('<p>Hola alert(2)link</p>', $html);
    }

    public function test_validates_query_parameters(): void
    {
        $this->getJson(self::URL.'?sort=hackeo')->assertUnprocessable();
        $this->getJson(self::URL.'?per_page=500')->assertUnprocessable();
        $this->getJson(self::URL.'?min_price=-1')->assertUnprocessable();
    }

    public function test_home_returns_fixed_blocks(): void
    {
        $this->product('Destacado', [['price' => 1000]], ['featured' => true]);
        $this->product('Normal', [['price' => 1000]]);

        $this->getJson('/api/storefront/v1/home')
            ->assertOk()
            ->assertJsonCount(1, 'data.featured')
            ->assertJsonCount(2, 'data.newest')
            ->assertJsonCount(0, 'data.on_sale')
            ->assertJsonStructure(['data' => ['categories', 'featured', 'newest', 'on_sale', 'brands']]);
    }

    public function test_home_on_sale_block_and_card_badges(): void
    {
        $color = $this->attribute('color', ['rojo']);
        $color->update(['display' => 'swatch']);
        $color->values()->update(['color_hex' => '#cc0000']);
        $this->product('Oferta', [['price' => 1000, 'sale' => 800, 'options' => ['color' => 'rojo']]]);
        $this->product('Antiguo', [['price' => 1000]], ['published_at' => now()->subDays(90)]);

        $res = $this->getJson('/api/storefront/v1/home')->assertOk();

        $res->assertJsonCount(1, 'data.on_sale')
            ->assertJsonPath('data.on_sale.0.name', 'Oferta')
            ->assertJsonPath('data.on_sale.0.is_new', true)
            ->assertJsonPath('data.on_sale.0.swatches.0.label', 'Rojo');

        $antiguo = collect($res->json('data.newest'))->firstWhere('name', 'Antiguo');
        $this->assertFalse($antiguo['is_new']);
    }

    public function test_storefront_server_with_shared_secret_is_not_rate_limited(): void
    {
        config(['flux.storefront_rate_limit' => 2, 'flux.revalidate_secret' => 'secreto-compartido']);

        foreach (range(1, 4) as $_) {
            $this->getJson('/api/storefront/v1/home', ['X-Flux-Storefront-Key' => 'secreto-compartido'])->assertOk();
        }
        $this->getJson('/api/storefront/v1/home', ['X-Flux-Storefront-Key' => 'otra-clave'])->assertOk();
        $this->getJson('/api/storefront/v1/home', ['X-Flux-Storefront-Key' => 'otra-clave'])->assertOk();
        $this->getJson('/api/storefront/v1/home', ['X-Flux-Storefront-Key' => 'otra-clave'])->assertTooManyRequests();
    }
}
