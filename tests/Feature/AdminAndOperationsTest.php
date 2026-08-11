<?php

use App\Jobs\RecalculateCustomerSegmentsJob;
use App\Models\ActivityLog;
use App\Models\Blacklist;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\CustomerSegmentationService;
use App\Services\Admin\FraudDetectionService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);
    Cache::flush();

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customer = User::factory()->create(['email' => 'customer@larashop.test']);

    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Flat Rate',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('live real time fraud evaluation and redis blacklist sets', function () {
    $fraudService = app(FraudDetectionService::class);

    // 1. Add phone number to Blacklist
    $fraudService->addToBlacklist('phone', '01700000000', 'Repeat COD Refusal');

    $order1 = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-FRAUD-001',
        'status' => 'pending',
        'subtotal' => 1000.00,
        'grand_total' => 1000.00,
        'customer_phone' => '01700000000',
        'customer_name' => 'Fraud User',
        'customer_email' => 'fraud@test.com',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
    ]);

    $risk1 = $fraudService->evaluateOrderRisk($order1);
    expect($risk1['is_flagged'])->toBeTrue();
    expect($order1->fresh()->is_flagged_fraud)->toBeTrue();

    // 2. Test live real-time return requests threshold (>= 3)
    $cleanUser = User::factory()->create(['email' => 'clean@test.com']);
    for ($i = 1; $i <= 3; $i++) {
        $ord = Order::create([
            'user_id' => $cleanUser->id,
            'order_number' => "ORD-RET-{$i}",
            'status' => 'delivered',
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'customer_phone' => '01711111111',
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
            'customer_name' => 'Clean User',
            'customer_email' => 'clean@test.com',
            'shipping_address' => 'Dhaka',
        ]);

        ReturnRequest::create([
            'order_id' => $ord->id,
            'user_id' => $cleanUser->id,
            'reason' => 'Damaged',
            'status' => 'completed',
        ]);
    }

    $order2 = Order::create([
        'user_id' => $cleanUser->id,
        'order_number' => 'ORD-FRAUD-002',
        'status' => 'pending',
        'subtotal' => 1200.00,
        'grand_total' => 1200.00,
        'customer_phone' => '01899999999',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Clean User',
        'customer_email' => 'clean@test.com',
        'shipping_address' => 'Dhaka',
    ]);

    $risk2 = $fraudService->evaluateOrderRisk($order2);
    expect($risk2['is_flagged'])->toBeTrue();
    expect($order2->fresh()->is_flagged_fraud)->toBeTrue();
});

test('audit log creation and model level immutability enforcement', function () {
    $auditService = app(AuditLogService::class);

    $log = $auditService->log('product.created', $this->admin, ['name' => 'Sample'], 'Created product');

    expect(ActivityLog::count())->toBe(1);

    // Test Immutability Exception on Update
    expect(function () use ($log) {
        $log->update(['description' => 'Tampered description']);
    })->toThrow(Exception::class);

    // Test Immutability Exception on Delete
    expect(function () use ($log) {
        $log->delete();
    })->toThrow(Exception::class);
});

test('queued customer segmentation background job execution and classification boundaries', function () {
    Queue::fake();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/customer-segments/recalculate')
        ->assertStatus(202);

    Queue::assertPushed(RecalculateCustomerSegmentsJob::class);

    // Direct classification logic verification
    $segmentationService = app(CustomerSegmentationService::class);

    // Create delivered orders worth 60,000 BDT -> Should classify as VIP
    Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-VIP-001',
        'status' => 'delivered',
        'subtotal' => 60000.00,
        'grand_total' => 60000.00,
        'customer_phone' => '01711111111',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Customer VIP',
        'customer_email' => 'customer@larashop.test',
        'shipping_address' => 'Dhaka',
    ]);

    $seg = $segmentationService->recalculateForUser($this->customer);
    expect($seg->segment)->toBe('vip');
    expect((float) $seg->total_spent)->toBe(60000.00);
});

test('super admin rbac role assignments and audit trail logging', function () {
    $targetUser = User::factory()->create(['email' => 'staff@larashop.test']);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/users/{$targetUser->id}/roles", [
            'role' => 'administrator',
        ])
        ->assertStatus(200);

    expect($targetUser->fresh()->hasRole('administrator'))->toBeTrue();
    expect(ActivityLog::where('action', 'user.role_assigned')->count())->toBe(1);
});
