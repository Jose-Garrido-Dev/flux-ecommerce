<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Storefront\V1\AuthController;
use App\Http\Controllers\Api\Storefront\V1\CartController;
use App\Http\Controllers\Api\Storefront\V1\CatalogController;
use App\Http\Controllers\Api\Storefront\V1\CheckoutController;
use App\Http\Controllers\Api\Storefront\V1\GeoController;
use App\Http\Controllers\Api\Storefront\V1\MeController;
use App\Http\Controllers\Api\Storefront\V1\OrderTrackingController;
use App\Http\Controllers\Api\Storefront\V1\ShipmentTrackingController;
use App\Http\Controllers\Api\Storefront\V1\StoreController;
use App\Http\Controllers\Webhooks\PaymentReturnController;
use App\Http\Middleware\AuthenticateCustomer;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);

// Regreso del cliente desde la pasarela de pago (la pasarela lo envía por GET o POST, sin CSRF ni sesión)
Route::match(['get', 'post'], '/pagos/{gateway}/retorno', PaymentReturnController::class)
    ->where('gateway', '[a-z_]+')->middleware('throttle:60,1')->name('payments.return');

// API pública para el storefront Next.js (contrato: packages/api-contract/openapi.yaml)
Route::prefix('storefront/v1')->middleware('throttle:storefront')->group(function () {
    Route::get('/store', [StoreController::class, 'show']);
    Route::get('/home', [CatalogController::class, 'home']);

    Route::prefix('catalog')->group(function () {
        Route::get('/products', [CatalogController::class, 'products']);
        Route::get('/products/{slug}', [CatalogController::class, 'product']);
        Route::get('/categories', [CatalogController::class, 'categories']);
        Route::get('/categories/{slug}', [CatalogController::class, 'category']);
        Route::get('/brands', [CatalogController::class, 'brands']);
        Route::get('/brands/{slug}', [CatalogController::class, 'brand']);
    });

    Route::get('/geo/regions', [GeoController::class, 'regions']);

    // Carrito: el navegador solo envía SKU y cantidad (el token va en X-Cart-Token)
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'add']);
    Route::patch('/cart/items/{sku}', [CartController::class, 'update']);
    Route::delete('/cart/items/{sku}', [CartController::class, 'remove']);

    Route::get('/checkout/options', [CheckoutController::class, 'options']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/orders/{token}', [OrderTrackingController::class, 'show']);
    Route::post('/orders/{token}/pay', [OrderTrackingController::class, 'pay']);
    Route::get('/shipments/{token}', [ShipmentTrackingController::class, 'show']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/forgot-password', [AuthController::class, 'forgot']);
        Route::post('/reset-password', [AuthController::class, 'reset']);
        Route::post('/verify-email', [AuthController::class, 'verify']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware(AuthenticateCustomer::class);
    });

    Route::prefix('me')->middleware(AuthenticateCustomer::class)->group(function () {
        Route::get('/', [MeController::class, 'show']);
        Route::patch('/', [MeController::class, 'update']);
        Route::put('/password', [MeController::class, 'password']);
        Route::get('/orders', [MeController::class, 'orders']);
        Route::get('/orders/{uuid}', [MeController::class, 'order']);
        Route::get('/addresses', [MeController::class, 'addresses']);
        Route::post('/addresses', [MeController::class, 'storeAddress']);
        Route::patch('/addresses/{id}', [MeController::class, 'updateAddress'])->whereNumber('id');
        Route::delete('/addresses/{id}', [MeController::class, 'destroyAddress'])->whereNumber('id');
    });
});
