<?php

use App\Models\Order;
use App\Models\ShippingZone;
use App\Services\Admin\FraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $zone = ShippingZone::create(['name' => 'Dhaka', 'is_active' => true]);
    $zone->locations()->create(['country_code' => 'BD']);
    $this->method = $zone->methods()->create([
        'name' => 'Standard',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('fix 4: adding to blacklist immediately invalidates cache and flags subsequent orders', function () {
    $fraudService = app(FraudDetectionService::class);

    // 1. Initial order before blacklisting
    $orderClean = Order::create([
        'order_number' => 'ORD-CLEAN-01',
        'status' => 'pending',
        'customer_name' => 'Clean User',
        'customer_phone' => '01999999999',
        'customer_email' => 'clean@test.com',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'subtotal' => 100.00,
        'grand_total' => 100.00,
    ]);

    // Warm cache: clean order must NOT be flagged
    $evalClean = $fraudService->evaluateOrderRisk($orderClean);
    expect($evalClean['is_flagged'])->toBeFalse();

    // 2. Add phone to blacklist
    $fraudService->addToBlacklist('phone', '01999999999', 'Fraudulent chargeback');

    // 3. Subsequent order with same phone must be flagged IMMEDIATELY without waiting 24h cache TTL!
    $orderFraud = Order::create([
        'order_number' => 'ORD-FRAUD-01',
        'status' => 'pending',
        'customer_name' => 'Fraud User',
        'customer_phone' => '01999999999',
        'customer_email' => 'clean2@test.com',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'subtotal' => 100.00,
        'grand_total' => 100.00,
    ]);

    $evalFraud = $fraudService->evaluateOrderRisk($orderFraud);
    expect($evalFraud['is_flagged'])->toBeTrue();
    expect($orderFraud->fresh()->is_flagged_fraud)->toBeTrue();
});
