<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    // Create Admin user
    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('administrator');

    // Create Customer user
    $this->customer = User::factory()->create(['email' => 'customer@larashop.test']);
    $this->customer->assignRole('staff'); // Staff has no manage-orders by default

    // Create Shipping Method
    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Flat Rate',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('admin authorization security gates', function () {
    // 1. Non-admin is blocked
    $this->actingAs($this->customer, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics')
        ->assertStatus(403);

    // 2. Admin is allowed
    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics')
        ->assertStatus(200);
});

test('metrics logic and cancelled/returned orders exclusion with growth rates', function () {
    // Current period orders
    $o1 = Order::create([
        'order_number' => 'ORD-001',
        'subtotal' => 1000.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 1000.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o1->id)->update(['created_at' => Carbon::now()->subDays(5)]);

    $o2 = Order::create([
        'order_number' => 'ORD-002',
        'subtotal' => 500.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 500.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'processing',
    ]);
    DB::table('orders')->where('id', $o2->id)->update(['created_at' => Carbon::now()->subDays(10)]);

    // Excluded orders
    $o3 = Order::create([
        'order_number' => 'ORD-003',
        'subtotal' => 800.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 800.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'cancelled',
    ]);
    DB::table('orders')->where('id', $o3->id)->update(['created_at' => Carbon::now()->subDays(2)]);

    $o4 = Order::create([
        'order_number' => 'ORD-004',
        'subtotal' => 700.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 700.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'returned',
    ]);
    DB::table('orders')->where('id', $o4->id)->update(['created_at' => Carbon::now()->subDays(4)]);

    // Previous period orders
    $o5 = Order::create([
        'order_number' => 'ORD-005',
        'subtotal' => 1000.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 1000.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o5->id)->update(['created_at' => Carbon::now()->subDays(45)]);

    // Clear cache to verify calculations
    Cache::flush();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics?period=month')
        ->assertStatus(200);

    $data = $response->json('data');

    // Current active revenue: 1000 + 500 = 1500.00 (ORD-003 & ORD-004 are excluded)
    expect((float) $data['revenue']['current'])->toBe(1500.0);
    expect((float) $data['revenue']['previous'])->toBe(1000.0);
    // Growth: ((1500 - 1000) / 1000) * 100 = 50%
    expect((float) $data['revenue']['growth_rate'])->toBe(50.0);

    // Total orders: current: 2, previous: 1
    expect((int) $data['orders']['current'])->toBe(2);
    expect((int) $data['orders']['previous'])->toBe(1);
    expect((float) $data['orders']['growth_rate'])->toBe(100.0);
});

test('caching mechanism and cache invalidation via clearCache', function () {
    // 1. Create order
    $o1 = Order::create([
        'order_number' => 'ORD-CACHE-1',
        'subtotal' => 100.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 100.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o1->id)->update(['created_at' => Carbon::now()]);

    Cache::flush();

    // 2. Fetch metrics first time (populates cache)
    $res1 = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics?period=month')
        ->json('data.revenue.current');

    expect((float) $res1)->toBe(100.0);

    // 3. Create another order (directly in DB)
    $o2 = Order::create([
        'order_number' => 'ORD-CACHE-2',
        'subtotal' => 400.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 400.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o2->id)->update(['created_at' => Carbon::now()]);

    // 4. Fetch metrics second time (should return cached BDT 100 instead of 500)
    $res2 = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics?period=month')
        ->json('data.revenue.current');

    expect((float) $res2)->toBe(100.0); // Stale (cached) check matches

    // 5. Clear settings cache to load fresh values
    Cache::flush();

    // 6. Fetch third time (cache was flushed, should show BDT 500)
    $res3 = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/metrics?period=month')
        ->json('data.revenue.current');

    expect((float) $res3)->toBe(500.0); // Fresh value returned
});

test('sales trend chart date range grouping', function () {
    $o1 = Order::create([
        'order_number' => 'ORD-CHART-1',
        'subtotal' => 200.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 200.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o1->id)->update(['created_at' => Carbon::parse('2026-08-05 10:00:00')]);

    $o2 = Order::create([
        'order_number' => 'ORD-CHART-2',
        'subtotal' => 300.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 300.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o2->id)->update(['created_at' => Carbon::parse('2026-08-05 15:00:00')]);

    $o3 = Order::create([
        'order_number' => 'ORD-CHART-3',
        'subtotal' => 400.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 400.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
    ]);
    DB::table('orders')->where('id', $o3->id)->update(['created_at' => Carbon::parse('2026-08-06 12:00:00')]);

    // Mock Carbon::now() inside test scope to match static dates
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));

    Cache::flush();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/sales-chart?period=month')
        ->assertStatus(200);

    $chart = $response->json('data');

    // Expecting 2 chart data groups: '2026-08-05' (sum 500) and '2026-08-06' (sum 400)
    expect(count($chart))->toBe(2);
    expect($chart[0]['label'])->toBe('2026-08-05');
    expect((float) $chart[0]['revenue'])->toBe(500.0);
    expect($chart[1]['label'])->toBe('2026-08-06');
    expect((float) $chart[1]['revenue'])->toBe(400.0);

    Carbon::setTestNow(); // Reset Mocking
});

test('top selling products query sorts by qty and eager loads product details', function () {
    $productA = Product::create([
        'name' => 'Gold Ring',
        'slug' => 'gold-ring',
        'type' => 'simple',
        'status' => 'published',
        'price' => 100.00,
    ]);

    $productB = Product::create([
        'name' => 'Silver Necklace',
        'slug' => 'silver-necklace',
        'type' => 'simple',
        'status' => 'published',
        'price' => 200.00,
    ]);

    $order = Order::create([
        'order_number' => 'ORD-TOP-1',
        'subtotal' => 1000.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 1000.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John',
        'customer_email' => 'john@gmail.com',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
        'created_at' => Carbon::now(),
    ]);

    // Order items (10 of A, 2 of B)
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $productA->id,
        'name' => 'Gold Ring',
        'quantity' => 10,
        'price' => 100.00,
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $productB->id,
        'name' => 'Silver Necklace',
        'quantity' => 2,
        'price' => 200.00,
    ]);

    Cache::flush();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/top-products?limit=2')
        ->assertStatus(200);

    $topList = $response->json('data');

    expect(count($topList))->toBe(2);
    // Product A should be first (sorted desc by total quantity)
    expect($topList[0]['product_name'])->toBe('Gold Ring');
    expect((int) $topList[0]['total_quantity'])->toBe(10);
    expect($topList[1]['product_name'])->toBe('Silver Necklace');
    expect((int) $topList[1]['total_quantity'])->toBe(2);
});

test('low stock warning listings for simple and variant items', function () {
    // 1. Simple product low on stock
    Product::create([
        'name' => 'Low Stock Cup',
        'slug' => 'low-stock-cup',
        'type' => 'simple',
        'status' => 'published',
        'price' => 20.00,
        'manage_stock' => true,
        'stock_quantity' => 3,
    ]);

    // 2. Simple product fully in stock (should not show)
    Product::create([
        'name' => 'High Stock Cup',
        'slug' => 'high-stock-cup',
        'type' => 'simple',
        'status' => 'published',
        'price' => 20.00,
        'manage_stock' => true,
        'stock_quantity' => 50,
    ]);

    // 3. Variant product low on stock
    $variableProd = Product::create([
        'name' => 'Shirt',
        'slug' => 'shirt',
        'type' => 'variable',
        'status' => 'published',
        'price' => 50.00,
    ]);

    ProductVariant::create([
        'product_id' => $variableProd->id,
        'sku' => 'SKU-SHIRT-BLUE-S',
        'price' => 50.00,
        'manage_stock' => true,
        'stock_quantity' => 2,
    ]);

    Cache::flush();

    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/admin/dashboard/low-stock?threshold=5&limit=5')
        ->assertStatus(200);

    $lowStockList = $response->json('data');

    expect(count($lowStockList))->toBe(2);
    // Sorted ascending by stock: Shirt (SKU-SHIRT-BLUE-S) has 2, Low Stock Cup has 3
    expect($lowStockList[0]['name'])->toBe('Shirt (SKU-SHIRT-BLUE-S)');
    expect((int) $lowStockList[0]['stock_quantity'])->toBe(2);
    expect($lowStockList[1]['name'])->toBe('Low Stock Cup');
    expect((int) $lowStockList[1]['stock_quantity'])->toBe(3);
});
