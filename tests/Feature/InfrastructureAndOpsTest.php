<?php

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->zone = ShippingZone::create(['name' => 'Dhaka', 'is_active' => true]);
    $this->method = $this->zone->methods()->create([
        'name' => 'Standard',
        'type' => 'flat_rate',
        'cost' => 60.00,
        'is_active' => true,
    ]);
});

test('system health check uptime endpoint checks db redis and storage', function () {
    $response = $this->getJson('/health');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'services' => ['database', 'disk', 'redis'],
            'is_healthy',
        ]);
});

test('encrypted database backup command and automated restore drill', function () {
    // 1. Run backup command
    $exitCode = Artisan::call('db:backup');
    expect($exitCode)->toBe(0);

    $backupDir = storage_path('app/backups');
    $files = File::files($backupDir);
    expect($files)->not()->toBeEmpty();

    $latestBackup = basename($files[0]->getRealPath());
    expect($latestBackup)->toContain('.enc');

    // 2. Run automated restore drill
    $restoreCode = Artisan::call('db:restore-test', ['file' => $latestBackup]);
    expect($restoreCode)->toBe(0);
});

test('high volume load testing asserting zero n plus 1 queries on list endpoints', function () {
    // Seed high volume rows
    $category = Category::create(['name' => 'Load Category', 'slug' => 'load-cat', 'is_active' => true]);
    $brand = Brand::create(['name' => 'Load Brand', 'slug' => 'load-brand', 'is_active' => true]);

    for ($i = 1; $i <= 50; $i++) {
        Product::create([
            'name' => "Load Product {$i}",
            'slug' => "load-prod-{$i}",
            'type' => 'simple',
            'sku' => "SKU-LOAD-{$i}",
            'price' => 100 + $i,
            'stock_quantity' => 100,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);
    }

    $customer = User::factory()->create(['email' => 'loadcustomer@test.com']);
    for ($i = 1; $i <= 30; $i++) {
        Order::create([
            'user_id' => $customer->id,
            'order_number' => "ORD-LOAD-{$i}",
            'status' => 'delivered',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'customer_name' => 'Load Customer',
            'customer_email' => 'loadcustomer@test.com',
            'customer_phone' => '01700000000',
            'shipping_address' => 'Dhaka',
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
        ]);
    }

    $articleCat = ArticleCategory::create(['name' => 'News', 'slug' => 'news', 'is_active' => true]);
    for ($i = 1; $i <= 20; $i++) {
        Article::create([
            'title' => "Article {$i}",
            'slug' => "article-{$i}",
            'body' => 'Sample article content body for load testing',
            'category_id' => $articleCat->id,
            'author_id' => $this->admin->id,
            'is_published' => true,
        ]);
    }

    // Assert /api/products query count (Must be <= 5 queries!)
    DB::flushQueryLog();
    DB::enableQueryLog();
    $res1 = $this->getJson('/api/products?per_page=15');
    $res1->assertStatus(200);
    $queryCountProducts = count(DB::getQueryLog());
    expect($queryCountProducts)->toBeLessThanOrEqual(5);

    // Assert /api/blog/posts query count (Must be <= 5 queries!)
    DB::flushQueryLog();
    DB::enableQueryLog();
    $res2 = $this->getJson('/api/blog/posts?per_page=15');
    $res2->assertStatus(200);
    $queryCountArticles = count(DB::getQueryLog());
    expect($queryCountArticles)->toBeLessThanOrEqual(5);

    // Assert /api/admin/dashboard/metrics query count (Must be <= 10 queries for entire metrics aggregate!)
    DB::flushQueryLog();
    DB::enableQueryLog();
    $res3 = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/dashboard/metrics');
    $res3->assertStatus(200);
    $queryCountMetrics = count(DB::getQueryLog());
    expect($queryCountMetrics)->toBeLessThanOrEqual(10);
});
