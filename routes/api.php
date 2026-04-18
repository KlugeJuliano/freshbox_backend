<?php

use App\Http\Controllers\Api\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\BannerController as ClientBannerController;
use App\Http\Controllers\Api\Client\CatalogController;
use App\Http\Controllers\Api\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Api\Client\StoreController as ClientStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('resolve.company')->prefix('client')->group(function () {
    Route::get('store', [ClientStoreController::class, 'show']);
    Route::get('banners', [ClientBannerController::class, 'index']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('categories/{slug}/products', [CatalogController::class, 'byCategory']);
    Route::get('products/featured', [CatalogController::class, 'featured']);
    Route::get('products/on-promo', [CatalogController::class, 'onPromo']);
    Route::get('products/search', [CatalogController::class, 'search']);
    Route::get('products/{slug}', [CatalogController::class, 'show']);
    Route::post('orders', [ClientOrderController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'resolve.company'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::patch('categories/reorder', [AdminCategoryController::class, 'reorder']);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::patch('categories/{category}/toggle', [AdminCategoryController::class, 'toggle']);

    Route::apiResource('products', AdminProductController::class);
    Route::patch('products/{product}/toggle', [AdminProductController::class, 'toggle']);
    Route::post('products/{product}/image', [AdminProductController::class, 'uploadImage']);

    Route::apiResource('banners', AdminBannerController::class);
    Route::patch('banners/{banner}/toggle', [AdminBannerController::class, 'toggle']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order}', [AdminOrderController::class, 'show']);
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

    Route::get('store', [AdminStoreController::class, 'show']);
    Route::put('store', [AdminStoreController::class, 'update']);
    Route::post('store/logo', [AdminStoreController::class, 'uploadLogo']);
});
