<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\MediaFolder;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Redirect;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Marketing\Models\Automation;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Models\Promotion;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\System\Models\AuditLog;
use App\Domain\System\Models\ModuleStatus;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use App\Models\User;
use App\Policies\AttributePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\AutomationPolicy;
use App\Policies\BlogPostPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\CatalogImportPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CouponPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\EmailPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\MediaPolicy;
use App\Policies\MenuPolicy;
use App\Policies\ModuleStatusPolicy;
use App\Policies\NewsletterSubscriberPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PagePolicy;
use App\Policies\ProductPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\RedirectPolicy;
use App\Policies\RegionPolicy;
use App\Policies\ReturnPolicy;
use App\Policies\ShippingMethodPolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(SettingsRepository::class);
    }

    public function boot(): void
    {
        // Detecta en desarrollo consultas N+1 y atributos no rellenables
        Model::shouldBeStrict(! $this->app->isProduction());

        // El servidor Next.js hace las consultas en nombre de todos los visitantes desde una sola IP:
        // se identifica con el secreto compartido y no se limita. Cualquier otro cliente sí.
        RateLimiter::for('storefront', function (Request $request) {
            $secret = (string) config('flux.revalidate_secret');
            $sent = (string) $request->header('X-Flux-Storefront-Key');
            if ($secret !== '' && $sent !== '' && hash_equals($secret, $sent)) {
                return Limit::none();
            }

            return Limit::perMinute(config('flux.storefront_rate_limit'))->by($request->ip());
        });

        // Se guarda en UTC y se muestra en hora de Chile
        FilamentTimezone::set(config('flux.display_timezone'));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ModuleStatus::class, ModuleStatusPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Region::class, RegionPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(CatalogImport::class, CatalogImportPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(InventoryLevel::class, InventoryPolicy::class);
        Gate::policy(StockReservation::class, InventoryPolicy::class);
        Gate::policy(StockTransfer::class, InventoryPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(ShippingMethod::class, ShippingMethodPolicy::class);
        Gate::policy(ShippingZone::class, ShippingMethodPolicy::class);
        Gate::policy(Courier::class, ShippingMethodPolicy::class);
        Gate::policy(Shipment::class, ShippingMethodPolicy::class);
        Gate::policy(ShippingIncident::class, ShippingMethodPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnPolicy::class);
        Gate::policy(EmailTemplate::class, EmailPolicy::class);
        Gate::policy(EmailLog::class, EmailPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Menu::class, MenuPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(MediaFolder::class, MediaPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Promotion::class, PromotionPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(NewsletterSubscriber::class, NewsletterSubscriberPolicy::class);
        Gate::policy(Automation::class, AutomationPolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(BlogCategory::class, BlogPostPolicy::class);
        Gate::policy(Redirect::class, RedirectPolicy::class);

        // El superadministrador técnico tiene todos los permisos
        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
    }
}
