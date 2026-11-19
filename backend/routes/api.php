<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Storefront\V1\CatalogController;
use App\Http\Controllers\Api\Storefront\V1\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);

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
});
