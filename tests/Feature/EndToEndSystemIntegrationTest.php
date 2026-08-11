<?php

use App\Models\Affiliate;
use App\Models\Cart;
use App\Models\FlashSale;
use App\Models\NotFoundLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\CheckoutCalculationService;
use App\Services\Marketing\AffiliateService;
use App\Services\Marketing\GiftCardService;
use App\Services\OrderService;
use App\Services\Return\ReturnService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customer = User::factory()->create(['email' => 'customer@larashop.test']);

    $this->zone = ShippingZone::create(['name' => 'Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Standard',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('flash sale price precedence in cart and checkout', function () {
    $product = Product::create([
        'name' => 'Flash Sale Phone',
        'slug' => 'flash-phone',
        'type' => 'simple',
        'sku' => 'FLASH-01',
        'price' => 1000.00,
        'sale_price' => 800.00,
        'stock_quantity' => 20,
        'is_active' => true,
        'status' => 'published',
    ]);

    $flashSale = FlashSale::create([
        'title' => 'Mega Flash Sale',
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'is_active' => true,
    ]);

    $flashSale->items()->create([
        'product_id' => $product->id,
        'discount_price' => 500.00,
        'quantity_limit' => 10,
    ]);

    $cart = Cart::create(['user_id' => $this->customer->id, 'token' => 'cart_tok_123']);
    $cartItem = $cart->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    // Flash sale price (500.00) must override regular and sale price!
    expect($cartItem->getItemPrice())->toBe(500.00);

    $calculationService = app(CheckoutCalculationService::class);
    $breakdown = $calculationService->calculate($cart, 'BD', 'Dhaka', 'Dhaka', null, $this->method->id);

    expect((float) $breakdown['subtotal'])->toBe(1000.00);
});

test('end to end gift card redemption during checkout', function () {
    $giftCardService = app(GiftCardService::class);
    $giftCard = $giftCardService->createGiftCard(1000.00, $this->admin->id);

    $product = Product::create([
        'name' => 'Sample Item',
        'slug' => 'sample-item',
        'type' => 'simple',
        'sku' => 'SAMPLE-01',
        'price' => 1200.00,
        'stock_quantity' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);

    $cart = Cart::create(['user_id' => $this->customer->id, 'token' => 'cart_tok_456']);
    $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

    $orderService = app(OrderService::class);
    $order = $orderService->checkout(
        $cart,
        ['name' => 'Customer', 'email' => 'customer@test.com', 'phone' => '01700000000'],
        ['address' => 'Dhaka', 'country_code' => 'BD'],
        $this->method->id,
        'cod',
        null,
        null,
        $giftCard->code
    );

    // 1200 + 50 shipping = 1250. Gift card (1000) deducted -> Grand total = 250
    expect((float) $order->grand_total)->toBe(250.00);
    expect((float) $giftCard->fresh()->current_balance)->toBe(0.00);
});

test('unified return pipeline reversing affiliate commission and refunding gift cards', function () {
    $affiliateUser = User::factory()->create(['email' => 'affiliate@test.com']);
    $affiliateService = app(AffiliateService::class);
    $affiliate = $affiliateService->registerAffiliate($affiliateUser, 10.0);

    $product = Product::create([
        'name' => 'Affiliate Product',
        'slug' => 'aff-prod',
        'type' => 'simple',
        'sku' => 'AFF-01',
        'price' => 1000.00,
        'stock_quantity' => 10,
        'is_active' => true,
        'status' => 'published',
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-AFF-001',
        'status' => 'confirmed',
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'grand_total' => 1050.00,
        'customer_name' => 'Customer',
        'customer_email' => 'customer@test.com',
        'customer_phone' => '01700000000',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'wallet',
        'payment_status' => 'paid',
    ]);

    $orderItem = $order->items()->create([
        'product_id' => $product->id,
        'name' => $product->name,
        'price' => 1000.00,
        'quantity' => 1,
    ]);

    $affiliateService->recordOrderReferral($order, $affiliate->affiliate_code);

    // 1. Transition order: confirmed -> shipped -> delivered -> Credits affiliate commission
    $orderService = app(OrderService::class);
    $orderService->updateStatus($order, 'shipped');
    $orderService->updateStatus($order, 'delivered');

    expect((float) $affiliate->fresh()->earnings_balance)->toBe(100.00);

    // 2. Process Return Request QC Approval -> Centralized Payment Refund Reverses Affiliate Commission!
    $returnService = app(ReturnService::class);
    $returnRequest = $returnService->createReturnRequest(
        $this->customer,
        $order->id,
        'Defective',
        'wallet',
        [['order_item_id' => $orderItem->id, 'quantity' => 1]]
    );

    $returnService->processQcStatus($returnRequest->id, 'qc_passed');

    // Commission must be reversed from affiliate earnings balance!
    expect((float) $affiliate->fresh()->earnings_balance)->toBe(0.00);
    expect($order->fresh()->status)->toBe('returned');
});

test('404 hit logging middleware', function () {
    $this->getJson('/api/non-existent-random-page-12345');

    expect(NotFoundLog::where('url', 'like', '%non-existent-random-page-12345%')->count())->toBe(1);
});
