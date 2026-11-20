<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\System\Models\AuditLog;
use App\Domain\System\Models\ModuleStatus;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use App\Models\User;
use App\Policies\AttributePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CatalogImportPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ModuleStatusPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RegionPolicy;
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

        // El superadministrador técnico tiene todos los permisos
        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
    }
}
