<?php

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->staffAdmin = User::factory()->create(['email' => 'staff-admin@larashop.test']);
    $this->staffAdmin->givePermissionTo('manage-products');

    $this->customer = User::factory()->create(['email' => 'customer@example.com']);
});

test('unauthorized users cannot manage coupons', function () {
    // Guest
    $this->getJson('/api/admin/coupons')
        ->assertStatus(401);

    // Customer
    $this->actingAs($this->customer)
        ->getJson('/api/admin/coupons')
        ->assertStatus(403);
});

test('admin can manage coupon CRUD lifecycle', function () {
    // 1. Create Coupon
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/coupons', [
            'code' => 'DISCOUNT50',
            'type' => 'percentage',
            'value' => 50.00,
            'min_purchase_amount' => 100.00,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addDays(7)->toIso8601String(),
            'max_uses' => 100,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.code', 'DISCOUNT50')
        ->assertJsonPath('data.type', 'percentage')
        ->assertJsonPath('data.value', '50.00');

    $couponId = $response->json('data.id');

    // 2. Read Coupon
    $this->actingAs($this->staffAdmin)
        ->getJson("/api/admin/coupons/{$couponId}")
        ->assertStatus(200)
        ->assertJsonPath('data.code', 'DISCOUNT50');

    // 3. Update Coupon
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/coupons/{$couponId}", [
            'code' => 'DISCOUNT60',
            'type' => 'percentage',
            'value' => 60.00,
            'min_purchase_amount' => 120.00,
            'is_active' => false,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.code', 'DISCOUNT60')
        ->assertJsonPath('data.value', '60.00')
        ->assertJsonPath('data.is_active', false);

    // 4. Delete Coupon
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/coupons/{$couponId}")
        ->assertStatus(204);

    expect(Coupon::where('id', $couponId)->exists())->toBeFalse();
});
