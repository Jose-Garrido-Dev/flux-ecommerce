<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Pricing\Models\TaxClass;
use Database\Seeders\TaxClassSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class CatalogRulesTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    public function test_category_cycles_are_rejected(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);

        $this->expectException(InvalidArgumentException::class);
        $a->update(['parent_id' => $c->id]);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $a = $this->category('A');

        $this->expectException(InvalidArgumentException::class);
        $a->update(['parent_id' => $a->id]);
    }

    public function test_moving_a_category_updates_descendant_paths(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $x = $this->category('X');

        $b->update(['parent_id' => $x->id]);

        $this->assertSame("/{$x->id}/", $b->fresh()->path);
        $this->assertSame("/{$x->id}/{$b->id}/", $c->fresh()->path);
        $this->assertSame(2, $c->fresh()->depth);
        $this->assertEqualsCanonicalizing([$x->id, $b->id, $c->id], $x->selfAndDescendantIds());
        $this->assertEqualsCanonicalizing([$a->id], $a->selfAndDescendantIds());
    }

    public function test_category_depth_is_limited(): void
    {
        $parent = null;
        foreach (range(1, Category::MAX_DEPTH) as $i) {
            $parent = $this->category("Nivel $i", $parent);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->category('Demasiado profundo', $parent);
    }

    public function test_price_rules_are_enforced(): void
    {
        $p = $this->product('P', [['price' => 1000]]);
        $price = $p->variants->first()->prices->first();

        foreach ([['compare_at_amount' => 900], ['sale_amount' => 1000], ['sale_starts_at' => now(), 'sale_ends_at' => now()->subHour(), 'sale_amount' => 500]] as $bad) {
            try {
                $price->fresh()->update($bad);
                $this->fail('Debió rechazar '.json_encode(array_keys($bad)));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_brand_names_are_unique_ignoring_case_and_accents(): void
    {
        Brand::query()->create(['name' => 'Ñandú']);

        $this->expectException(UniqueConstraintViolationException::class);
        Brand::query()->create(['name' => 'nandu', 'slug' => 'otro']);
    }

    public function test_tax_classes_are_seeded_with_chilean_iva(): void
    {
        $this->seed(TaxClassSeeder::class);
        $this->seed(TaxClassSeeder::class);

        $iva = TaxClass::query()->where('is_default', true)->sole();
        $this->assertSame(1900, $iva->effectiveRateBp());
        $this->assertSame(0, TaxClass::query()->where('code', 'exento')->sole()->effectiveRateBp());
    }

    public function test_demo_command_refuses_production_and_cleans_up(): void
    {
        Storage::fake('public');
        $this->seed(TaxClassSeeder::class);

        $this->app['env'] = 'production';
        $this->artisan('flux:demo')->assertFailed();
        $this->assertSame(0, Product::query()->count());

        $this->app['env'] = 'local';
        $this->artisan('flux:demo')->assertSuccessful();
        $this->assertGreaterThan(10, Product::query()->count());
        Storage::disk('public')->assertExists('demo/demo-polera-esencial-negro-a.jpg');
        $real = $this->product('Producto real', [['price' => 1000]]);

        $this->artisan('flux:demo --remove')->assertSuccessful();
        $this->assertSame([$real->id], Product::query()->pluck('id')->all());
        $this->assertSame(0, Category::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles('demo'));
    }
}
