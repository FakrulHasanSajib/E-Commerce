<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\ProductFeedController;
use App\Http\Controllers\Api\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'LaraShop E-Commerce REST API',
        'version' => '1.0.0',
        'status' => 'online',
        'storefront_url' => 'http://localhost:3000',
        'admin_url' => 'http://localhost:5173',
    ], 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::get('/health', [HealthCheckController::class, 'index']);

Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{type}.xml', [SitemapController::class, 'subSitemap']);

Route::get('feeds/google-shopping.xml', [ProductFeedController::class, 'googleShopping']);
Route::get('feeds/facebook-catalog.csv', [ProductFeedController::class, 'facebookCatalog']);

require __DIR__.'/settings.php';
