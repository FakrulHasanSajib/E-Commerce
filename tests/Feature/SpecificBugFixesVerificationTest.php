<?php

use App\Models\Cart;
use App\Models\FlashSale;
use App\Models\NotFoundLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\FlashSale\FlashSaleService;
use App\Services\OrderService;
use App\Services\Return\ReturnService;
use App\Services\Seo\RedirectService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customer = User::factory()->create(['email' => 'customer@larashop.test', 'wallet_balance' => 0.00]);

    $this->zone = ShippingZone::create(['name' => 'Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Standard',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('bug 1 fix: variant price is respected by resolveEffectivePrice', function () {
    $product = Product::create([
        'name' => 'Configurable Shirt',
        'slug' => 'config-shirt',
        'type' => 'variable',
        'sku' => 'SHIRT-01',
        'price' => 1000.00,
        'sale_price' => 900.00,
        'stock_quantity' => 20,
        'is_active' => true,
        'status' => 'published',
    ]);

    $variant = $product->variants()->create([
        'sku' => 'SHIRT-XL-RED',
        'price' => 1500.00,
        'sale_price' => 1200.00,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    $flashSaleService = app(FlashSaleService::class);

    // Variant sale price (1200.00) must be resolved, not parent product sale price (900.00) or regular price (1000.00)
    $effectivePrice = $flashSaleService->resolveEffectivePrice($product, $variant);
    expect($effectivePrice)->toBe(1200.00);
});

test('bug 2 fix: flash sale quantity limits are enforced and sold_quantity is incremented under lock', function () {
    $product = Product::create([
        'name' => 'Limited Flash Item',
        'slug' => 'limited-flash',
        'type' => 'simple',
        'sku' => 'LIM-01',
        'price' => 1000.00,
        'stock_quantity' => 20,
        'is_active' => true,
        'status' => 'published',
    ]);

    $flashSale = FlashSale::create([
        'title' => 'Flash Sale Limit Test',
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $flashItem = $flashSale->items()->create([
        'product_id' => $product->id,
        'discount_price' => 300.00,
        'quantity_limit' => 2,
        'sold_quantity' => 0,
    ]);

    $orderService = app(OrderService::class);

    // Attempt to checkout 3 items (exceeds limit of 2)
    $cartExceed = Cart::create(['user_id' => $this->customer->id, 'token' => 'tok_exceed']);
    $cartExceed->items()->create(['product_id' => $product->id, 'quantity' => 3]);

    expect(function () use ($orderService, $cartExceed) {
        $orderService->checkout(
            $cartExceed,
            ['name' => 'Customer', 'email' => 'customer@test.com', 'phone' => '01700000000'],
            ['address' => 'Dhaka', 'country_code' => 'BD'],
            $this->method->id,
            'cod'
        );
    })->toThrow(ValidationException::class);

    // Valid checkout of 2 items
    $cartValid = Cart::create(['user_id' => $this->customer->id, 'token' => 'tok_valid']);
    $cartValid->items()->create(['product_id' => $product->id, 'quantity' => 2]);

    $orderService->checkout(
        $cartValid,
        ['name' => 'Customer', 'email' => 'customer@test.com', 'phone' => '01700000000'],
        ['address' => 'Dhaka', 'country_code' => 'BD'],
        $this->method->id,
        'cod'
    );

    // sold_quantity must be incremented to 2!
    expect($flashItem->fresh()->sold_quantity)->toBe(2);

    // Subsequent price resolution falls back to regular price (1000.00) because flash limit is reached!
    $flashSaleService = app(FlashSaleService::class);
    expect($flashSaleService->resolveEffectivePrice($product))->toBe(1000.00);
});

test('bug 3 fix: partial return refunds exact returned amount instead of grand_total', function () {
    $product1 = Product::create([
        'name' => 'Item 1',
        'slug' => 'item-1',
        'type' => 'simple',
        'sku' => 'ITEM-1',
        'price' => 1000.00,
        'stock_quantity' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);

    $product2 = Product::create([
        'name' => 'Item 2',
        'slug' => 'item-2',
        'type' => 'simple',
        'sku' => 'ITEM-2',
        'price' => 2000.00,
        'stock_quantity' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-PARTIAL-001',
        'status' => 'shipped',
        'subtotal' => 3000.00,
        'discount' => 0.00,
        'grand_total' => 3050.00,
        'customer_name' => 'Customer',
        'customer_email' => 'customer@test.com',
        'customer_phone' => '01700000000',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'payment_status' => 'paid',
    ]);

    $item1 = $order->items()->create(['product_id' => $product1->id, 'name' => 'Item 1', 'price' => 1000.00, 'quantity' => 1]);
    $item2 = $order->items()->create(['product_id' => $product2->id, 'name' => 'Item 2', 'price' => 2000.00, 'quantity' => 1]);

    $orderService = app(OrderService::class);
    $orderService->updateStatus($order, 'delivered');

    // Customer requests return ONLY for item1 (1000.00)
    $returnService = app(ReturnService::class);
    $returnRequest = $returnService->createReturnRequest(
        $this->customer,
        $order->id,
        'Defective',
        'wallet',
        [['order_item_id' => $item1->id, 'quantity' => 1]]
    );

    expect((float) $returnRequest->refund_amount)->toBe(1000.00);

    // Admin passes QC
    $returnService->processQcStatus($returnRequest->id, 'qc_passed');

    // User wallet balance must receive EXACTLY 1000.00 (NOT the grand total of 3050.00!)
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(1000.00);

    // Payment status must be 'partial' because only part of the order was refunded
    expect($order->fresh()->payment_status)->toBe('partial');
});

test('bug 4 fix: refund respects customer refund_method choice (bank vs wallet)', function () {
    $product = Product::create([
        'name' => 'Bank Refund Item',
        'slug' => 'bank-refund-item',
        'type' => 'simple',
        'sku' => 'BANK-01',
        'price' => 500.00,
        'stock_quantity' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-BANK-001',
        'status' => 'shipped',
        'subtotal' => 500.00,
        'grand_total' => 550.00,
        'customer_name' => 'Customer',
        'customer_email' => 'customer@test.com',
        'customer_phone' => '01700000000',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'payment_status' => 'paid',
    ]);

    $item = $order->items()->create(['product_id' => $product->id, 'name' => 'Bank Refund Item', 'price' => 500.00, 'quantity' => 1]);

    $orderService = app(OrderService::class);
    $orderService->updateStatus($order, 'delivered');

    // Customer explicitly requests 'bank' refund method
    $returnService = app(ReturnService::class);
    $returnRequest = $returnService->createReturnRequest(
        $this->customer,
        $order->id,
        'Wrong size',
        'bank',
        [['order_item_id' => $item->id, 'quantity' => 1]]
    );

    $returnService->processQcStatus($returnRequest->id, 'qc_passed');

    // Customer wallet balance must remain 0.00 because they requested bank refund!
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(0.00);
});

test('bug 5 fix: 404 logger parameter order places IP in ip_address and UserAgent in user_agent column', function () {
    $redirectService = app(RedirectService::class);

    $testUrl = 'https://larashop.test/test-404-param-order';
    $testIp = '192.168.1.100';
    $testUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TestBrowser/1.0';

    $redirectService->log404NotFound($testUrl, $testIp, $testUserAgent);

    $log = NotFoundLog::where('url', $testUrl)->first();

    expect($log)->not->toBeNull();
    expect($log->ip_address)->toBe($testIp);
    expect($log->user_agent)->toBe($testUserAgent);
});
