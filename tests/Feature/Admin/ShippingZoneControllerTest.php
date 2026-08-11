<?php

use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->staffAdmin = User::factory()->create(['email' => 'staff-admin@larashop.test']);
    $this->staffAdmin->givePermissionTo('manage-products');

    $this->customer = User::factory()->create(['email' => 'customer@example.com']);
});

test('unauthorized users cannot manage shipping configurations', function () {
    $this->getJson('/api/admin/shipping-zones')
        ->assertStatus(401);

    $this->actingAs($this->customer)
        ->getJson('/api/admin/shipping-zones')
        ->assertStatus(403);
});

test('admin can manage shipping zone CRUD lifecycle and trigger cache evictions', function () {
    // 1. Pre-warm Cache
    Cache::put('active_shipping_zones', collect([]));
    expect(Cache::has('active_shipping_zones'))->toBeTrue();

    // 2. Create Shipping Zone
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/shipping-zones', [
            'name' => 'Inside Dhaka Division',
            'is_active' => true,
            'locations' => [
                [
                    'country_code' => 'BD',
                    'division' => 'Dhaka',
                ],
            ],
            'methods' => [
                [
                    'name' => 'Division Delivery',
                    'type' => 'flat_rate',
                    'cost' => 80.00,
                ],
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Inside Dhaka Division')
        ->assertJsonCount(1, 'data.locations')
        ->assertJsonCount(1, 'data.methods');

    $zoneId = $response->json('data.id');

    // Verify cache was evicted
    expect(Cache::has('active_shipping_zones'))->toBeFalse();

    // Pre-warm cache again
    Cache::put('active_shipping_zones', collect([]));

    // 3. Update Shipping Zone
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/shipping-zones/{$zoneId}", [
            'name' => 'Updated Dhaka Division',
            'is_active' => true,
            'locations' => [
                [
                    'country_code' => 'BD',
                    'division' => 'Dhaka',
                    'district' => 'Gazipur',
                ],
            ],
            'methods' => [
                [
                    'name' => 'Weight Rates Method',
                    'type' => 'weight_based',
                    'cost' => 100.00,
                    'weight_rates' => [
                        [
                            'max_weight' => 5.00,
                            'cost' => 150.00,
                        ],
                    ],
                ],
            ],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Dhaka Division')
        ->assertJsonPath('data.locations.0.district', 'Gazipur')
        ->assertJsonPath('data.methods.0.weight_rates.0.cost', '150.00');

    expect(Cache::has('active_shipping_zones'))->toBeFalse();

    // Pre-warm cache again
    Cache::put('active_shipping_zones', collect([]));

    // 4. Delete Shipping Zone
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/shipping-zones/{$zoneId}")
        ->assertStatus(204);

    expect(ShippingZone::where('id', $zoneId)->exists())->toBeFalse();
    expect(Cache::has('active_shipping_zones'))->toBeFalse();
});
