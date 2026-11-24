<?php

namespace Tests\Feature\Marketing;

use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\Redirect;
use App\Domain\Marketing\Models\Automation;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Models\Promotion;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Pages\WhatsAppSettingsPage;
use App\Filament\Resources\Automations\Pages\ManageAutomations;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Filament\Resources\Promotions\Pages\ManagePromotions;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

/** Panel de Marketing y Contenido (Fase 10): lo que se configura aquí es lo que aplica la tienda. */
class MarketingAdminTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_coupon_is_created_with_targets_and_a_normalized_code(): void
    {
        $this->as('marketing');
        $polera = $this->product('Polera', [['price' => 10000]]);
        $taza = $this->product('Taza', [['price' => 5000]]);

        Livewire::test(ManageCoupons::class)
            ->callAction('create', data: [
                'code' => 'verano 10', 'name' => 'Verano', 'type' => 'percent', 'value' => 10, 'status' => 'active', 'stackable' => true,
            ])
            ->assertHasActionErrors(['code']);

        Livewire::test(ManageCoupons::class)
            ->callAction('create', data: [
                'code' => 'verano10', 'name' => 'Verano', 'type' => 'percent', 'value' => 150, 'status' => 'active',
            ])
            ->assertHasActionErrors(['value']);

        Livewire::test(ManageCoupons::class)
            ->callAction('create', data: [
                'code' => 'verano10', 'name' => 'Verano', 'type' => 'percent', 'value' => 10, 'max_discount' => 5000, 'status' => 'active',
                'stackable' => true, 'include_products' => [(string) $polera->id], 'exclude_products' => [(string) $taza->id],
            ])
            ->assertHasNoActionErrors();

        $coupon = Coupon::query()->where('code', 'VERANO10')->firstOrFail();
        Livewire::test(ManageCoupons::class)
            ->callAction('create', data: ['code' => 'Verano10', 'name' => 'Otro', 'type' => 'fixed', 'value' => 1000, 'status' => 'active'])
            ->assertHasActionErrors(['code']);
        $this->assertSame(10, $coupon->value);
        $this->assertSame([[$polera->id, 'include'], [$taza->id, 'exclude']],
            $coupon->targets()->orderBy('id')->get()->map(fn ($t) => [$t->target_id, $t->mode])->all());

        // Pasar a envío gratis limpia el monto y el tope; los productos se reemplazan
        Livewire::test(ManageCoupons::class)
            ->callTableAction(EditAction::class, $coupon, data: ['type' => 'free_shipping', 'include_products' => [], 'exclude_products' => []])
            ->assertHasNoTableActionErrors();
        $coupon->refresh();
        $this->assertSame(['free_shipping', 0, null, 0], [$coupon->type, $coupon->value, $coupon->max_discount, $coupon->targets()->count()]);
    }

    public function test_three_for_two_promotion_is_stored_as_rules(): void
    {
        $this->as('marketing');
        $category = $this->category('Poleras');

        Livewire::test(ManagePromotions::class)
            ->callAction('create', data: [
                'name' => '3x2 en poleras', 'type' => 'buy_x_get_y', 'buy_quantity' => 2, 'get_quantity' => 1, 'value' => 100,
                'category_ids' => [(string) $category->id], 'priority' => 5, 'stackable' => true, 'status' => 'active',
            ])
            ->assertHasNoActionErrors();

        $promotion = Promotion::query()->firstOrFail();
        $this->assertSame(['category_ids' => [$category->id], 'buy_quantity' => 2], $promotion->conditions);
        $this->assertSame(['discount_type' => 'percent', 'value' => 100, 'get_quantity' => 1], $promotion->actions);

        Livewire::test(ManagePromotions::class)
            ->callAction('create', data: ['name' => 'Sin mínimo', 'type' => 'cart_amount', 'discount_type' => 'percent', 'value' => 10, 'status' => 'active'])
            ->assertHasActionErrors(['min_subtotal']);
    }

    public function test_subscriber_added_from_the_panel_must_confirm_and_can_be_unsubscribed(): void
    {
        $this->as('marketing');
        Livewire::test(ManageNewsletterSubscribers::class)
            ->callAction('add', data: ['email' => 'Ana@Correo.cl', 'name' => 'Ana'])
            ->assertHasNoActionErrors();
        $subscriber = NewsletterSubscriber::query()->firstOrFail();
        $this->assertSame(['ana@correo.cl', 'pending', 'admin'], [$subscriber->email, $subscriber->status, $subscriber->source]);

        Livewire::test(ManageNewsletterSubscribers::class)->callTableAction('unsubscribe', $subscriber);
        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
        $this->assertDatabaseHas('consents', ['email' => 'ana@correo.cl', 'granted' => false, 'source' => 'newsletter_baja_panel']);
    }

    public function test_campaign_needs_confirmed_subscribers_and_only_marketing_can_send(): void
    {
        $this->as('marketing');
        Livewire::test(CreateCampaign::class)
            ->fillForm(['name' => 'Cyber', 'audience' => 'all', 'subject' => 'Ofertas', 'heading' => 'Cyber', 'body' => 'Hola', 'button_url' => 'javascript:alert(1)', 'button_label' => 'Ver'])
            ->call('create')
            ->assertHasFormErrors(['button_url']);
        Livewire::test(CreateCampaign::class)
            ->fillForm(['name' => 'Cyber', 'audience' => 'all', 'subject' => 'Ofertas', 'heading' => 'Cyber', 'body' => 'Hola', 'button_label' => 'Ver'])
            ->call('create')
            ->assertHasFormErrors(['button_url']);
        Livewire::test(CreateCampaign::class)
            ->fillForm(['name' => 'Cyber', 'audience' => 'all', 'subject' => 'Ofertas', 'heading' => 'Cyber', 'body' => 'Hola', 'button_label' => 'Ver', 'button_url' => '/catalogo'])
            ->call('create')
            ->assertHasNoFormErrors();
        $campaign = Campaign::query()->firstOrFail();
        $this->assertSame('draft', $campaign->status);

        // Sin suscritos confirmados no se programa
        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('schedule', data: ['now' => true])
            ->assertNotified('No se pudo completar');
        $this->assertSame('draft', $campaign->fresh()->status);

        NewsletterSubscriber::query()->create(['email' => 'ana@correo.cl', 'source' => 'footer', 'status' => 'subscribed', 'confirmed_at' => now()]);
        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])
            ->callAction('schedule', data: ['now' => false, 'at' => now()->addDay()->format('Y-m-d H:i')])
            ->assertHasNoActionErrors();
        $this->assertSame('scheduled', $campaign->fresh()->status);

        // Un rol sin "marketing.send" no ve el botón de envío
        $editor = User::factory()->create(['status' => 'active']);
        $editor->givePermissionTo(['marketing.view', 'marketing.update']);
        $this->actingAs($editor);
        Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()])->assertActionHidden('schedule');
    }

    public function test_activating_an_automation_starts_counting_from_now(): void
    {
        $this->as('marketing');
        $this->travelTo(now()->setTime(10, 0));
        Livewire::test(ManageAutomations::class)->assertCanSeeTableRecords(Automation::query()->get());
        $welcome = Automation::query()->where('trigger', 'welcome')->firstOrFail();
        $this->assertFalse($welcome->is_active);

        Livewire::test(ManageAutomations::class)->callTableAction('toggle', $welcome);
        $welcome->refresh();
        $this->assertTrue($welcome->is_active);
        $this->assertSame(now()->format('Y-m-d H:i'), $welcome->active_since->format('Y-m-d H:i'));
    }

    public function test_whatsapp_phone_is_validated_and_normalized(): void
    {
        $this->as('marketing');
        Livewire::test(WhatsAppSettingsPage::class)
            ->fillForm(['enabled' => true, 'phone' => '1234'])
            ->call('save')->assertHasFormErrors(['phone']);
        Livewire::test(WhatsAppSettingsPage::class)
            ->fillForm(['enabled' => true, 'phone' => '9 1234 5678', 'position' => 'left', 'pages' => 'all'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('+56912345678', app(SettingsRepository::class)->group('whatsapp')['phone']);
        $this->getJson('/api/storefront/v1/store')->assertJsonPath('data.whatsapp.phone', '56912345678')
            ->assertJsonPath('data.whatsapp.position', 'left');
    }

    public function test_redirect_errors_show_on_the_field_and_blog_post_publishes_now(): void
    {
        $this->as('marketing');
        Livewire::test(ManageRedirects::class)
            ->callAction('create', data: ['from_path' => '/admin/x', 'to_path' => '/catalogo', 'code' => 301])
            ->assertHasActionErrors(['from_path']);
        Livewire::test(ManageRedirects::class)
            ->callAction('create', data: ['from_path' => 'https://mitienda.cl/tienda/polera/', 'to_path' => '/producto/polera', 'code' => 301])
            ->assertHasNoActionErrors();
        $this->assertSame('/tienda/polera', Redirect::query()->value('from_path'));

        Livewire::test(CreateBlogPost::class)
            ->fillForm(['title' => 'Cómo elegir tu talla', 'slug' => 'como-elegir-tu-talla', 'body' => 'Texto', 'status' => 'published'])
            ->call('create')->assertHasNoFormErrors();
        $post = BlogPost::query()->firstOrFail();
        $this->assertTrue($post->isVisible());
        $this->getJson('/api/storefront/v1/blog/posts/como-elegir-tu-talla')->assertOk();
    }
}
