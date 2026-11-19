<?php

namespace Tests\Feature\Language;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Shipping\Models\Region;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

/**
 * Regla del proyecto: todo lo que ve una persona está en español.
 * Recorre cada pantalla del panel con registros reales y falla si aparece texto visible en inglés
 * (etiquetas generadas a partir del nombre del campo, textos por defecto sin traducir, etc.).
 */
class SpanishOnlyAdminTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    public function test_every_admin_page_is_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => 'active', 'name' => 'Ana Pérez']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $this->attribute('color', ['negro', 'blanco']);
        $product = $this->product('Polera', [['price' => 9990, 'options' => ['color' => 'negro']], ['price' => 9990, 'options' => ['color' => 'blanco']]]);
        $category = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $brand = Brand::query()->create(['name' => 'Marca', 'slug' => 'marca']);
        $records = [
            'products' => $product, 'categories' => $category, 'brands' => $brand,
            'attributes' => Attribute::query()->first(), 'users' => $admin, 'regions' => Region::query()->first(),
        ];

        $urls = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin') || ! in_array('GET', $route->methods(), true)
                || str_contains($uri, 'logout') || str_contains($uri, 'login') || str_contains($uri, 'password')) {
                continue;
            }
            if (str_contains($uri, '{record}')) {
                $segment = explode('/', $uri)[1] ?? '';
                if (! isset($records[$segment])) {
                    continue;
                }
                $uri = str_replace('{record}', (string) $records[$segment]->getRouteKey(), $uri);
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            $urls[] = '/'.$uri;
        }
        // Pestañas de relaciones del producto
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=0';
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=1';
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=2';

        $problems = [];
        foreach (array_unique($urls) as $url) {
            $response = $this->get($url);
            if ($response->status() !== 200) {
                continue;
            }
            foreach (self::englishFragments($response->getContent()) as $fragment) {
                $problems[] = "$url → \"$fragment\"";
            }
        }

        $this->assertSame([], array_values(array_unique($problems)), "Texto en inglés visible en el panel:\n".implode("\n", array_unique($problems)));
    }

    public function test_guest_login_page_is_in_spanish(): void
    {
        $this->assertSame([], self::englishFragments($this->get('/admin/login')->getContent()));
    }

    /** @return list<string> fragmentos de texto visible que contienen palabras en inglés */
    public static function englishFragments(string $html): array
    {
        // Solo texto visible: se quitan scripts, estilos, svg y atributos técnicos
        $html = preg_replace('#<(script|style|svg|template)\b[^>]*>.*?</\1>#si', ' ', $html);
        $texts = [];
        preg_match_all('/>([^<>]+)</', $html, $m);
        $texts = $m[1];
        // Atributos que se leen en pantalla o con lector de pantalla
        preg_match_all('/\s(?:placeholder|title|aria-label|alt)="([^"]*)"/i', $html, $attrs);
        $texts = array_merge($texts, $attrs[1]);

        // Lista compartida con la revisión de la tienda (packages/qa/palabras-ingles.json)
        $list = json_decode(file_get_contents(base_path('../packages/qa/palabras-ingles.json')), true);
        $english = array_flip(array_diff($list['ingles'], $list['permitidas']));
        $found = [];
        foreach ($texts as $text) {
            $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
            if ($text === '' || str_contains($text, '{{') || str_contains($text, '$wire') || str_contains($text, '=>')) {
                continue;
            }
            foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($text)) as $word) {
                if (isset($english[$word])) {
                    $found[] = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 80);
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }
}
