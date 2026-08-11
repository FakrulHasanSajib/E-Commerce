<?php

use App\Models\TaxClass;
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

test('unauthorized users cannot manage tax configurations', function () {
    $this->getJson('/api/admin/tax-classes')
        ->assertStatus(401);

    $this->actingAs($this->customer)
        ->getJson('/api/admin/tax-classes')
        ->assertStatus(403);
});

test('admin can manage tax class CRUD lifecycle and trigger cache evictions', function () {
    // 1. Pre-warm Cache
    Cache::put('active_tax_rates_by_class', collect([]));
    expect(Cache::has('active_tax_rates_by_class'))->toBeTrue();

    // 2. Create Tax Class
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/tax-classes', [
            'name' => 'Standard VAT Rate',
            'slug' => 'standard-vat',
            'rate' => [
                'name' => 'Standard VAT 15%',
                'rate' => 15.00,
                'is_active' => true,
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Standard VAT Rate')
        ->assertJsonPath('data.rate.rate', '15.00');

    $classId = $response->json('data.id');

    // Verify cache was evicted
    expect(Cache::has('active_tax_rates_by_class'))->toBeFalse();

    // Pre-warm cache again
    Cache::put('active_tax_rates_by_class', collect([]));

    // 3. Update Tax Class
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/tax-classes/{$classId}", [
            'name' => 'Updated Standard VAT Rate',
            'slug' => 'standard-vat',
            'rate' => [
                'name' => 'Standard VAT 12%',
                'rate' => 12.00,
                'is_active' => true,
            ],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Standard VAT Rate')
        ->assertJsonPath('data.rate.rate', '12.00');

    expect(Cache::has('active_tax_rates_by_class'))->toBeFalse();

    // Pre-warm cache again
    Cache::put('active_tax_rates_by_class', collect([]));

    // 4. Delete Tax Class
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/tax-classes/{$classId}")
        ->assertStatus(204);

    expect(TaxClass::where('id', $classId)->exists())->toBeFalse();
    expect(Cache::has('active_tax_rates_by_class'))->toBeFalse();
});
