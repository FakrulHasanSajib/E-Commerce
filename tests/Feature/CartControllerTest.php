<?php

use App\Jobs\CleanStaleCartsJob;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->user = User::factory()->create(['email' => 'customer@larashop.test']);
    $this->staffAdmin = User::factory()->create(['email' => 'staff-admin@larashop.test']);
    $this->staffAdmin->givePermissionTo('manage-products');

    // Create simple products
    $this->simpleProduct = Product::create([
        'name' => 'Adidas Gazelle',
        'type' => 'simple',
        'status' => 'published',
        'price' => 100.00,
        'manage_stock' => true,
        'stock_quantity' => 5,
        'is_active' => true,
    ]);

    $this->adidasShoes = Product::create([
        'name' => 'Adidas Samba',
        'type' => 'simple',
        'status' => 'published',
        'price' => 80.00,
        'manage_stock' => false,
        'is_active' => true,
    ]);
});

test('guests can manage cart items using token header', function () {
    $token = Str::random(40);

    // 1. Add item to guest cart
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/items', [
            'product_id' => $this->simpleProduct->id,
            'quantity' => 2,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.subtotal', '200.00')
        ->assertJsonCount(1, 'data.items');

    $itemId = $response->json('data.items.0.id');

    // 2. Update item quantity
    $response = $this->withHeader('X-Cart-Token', $token)
        ->putJson("/api/cart/items/{$itemId}", [
            'quantity' => 3,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.subtotal', '300.00');

    // 3. Remove item from cart
    $this->withHeader('X-Cart-Token', $token)
        ->deleteJson("/api/cart/items/{$itemId}")
        ->assertStatus(200)
        ->assertJsonPath('data.subtotal', '0.00')
        ->assertJsonCount(0, 'data.items');
});

test('advisory stock check caps quantity to available stock level', function () {
    $token = Str::random(40);

    // Adidas Gazelle has stock_quantity = 5. Try adding 10.
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/items', [
            'product_id' => $this->simpleProduct->id,
            'quantity' => 10,
        ]);

    // Quantity should be automatically reduced to 5 and return warning
    $response->assertStatus(200)
        ->assertJsonPath('data.items.0.quantity', 5)
        ->assertJsonPath('data.subtotal', '500.00')
        ->assertJsonFragment([
            'warnings' => [
                'Quantity for "Adidas Gazelle" was reduced to 5 due to stock limits.',
            ],
        ]);
});

test('guest cart merges automatically into user cart upon login', function () {
    $guestToken = Str::random(40);

    // 1. Add Adidas Samba to guest cart
    $this->withHeader('X-Cart-Token', $guestToken)
        ->postJson('/api/cart/items', [
            'product_id' => $this->adidasShoes->id,
            'quantity' => 2,
        ])
        ->assertStatus(200);

    // 2. Add Adidas Gazelle to user's existing cart
    $userCart = Cart::create([
        'user_id' => $this->user->id,
        'token' => Str::random(40),
    ]);
    $userCart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 1,
    ]);

    // 3. Request show cart as authenticated user passing the guest token header
    $response = $this->actingAs($this->user)
        ->withHeader('X-Cart-Token', $guestToken)
        ->getJson('/api/cart')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.subtotal', '260.00'); // 1 * 100 + 2 * 80 = 260

    // 4. Verify that guest cart got deleted
    expect(Cart::where('token', $guestToken)->exists())->toBeFalse();
});

test('coupons apply percentage discount and minimum spend constraints', function () {
    $token = Str::random(40);

    // Create 10% coupon with min purchase $150
    $coupon = Coupon::create([
        'code' => 'SAVE10',
        'type' => 'percentage',
        'value' => 10.00,
        'min_purchase_amount' => 150.00,
        'is_active' => true,
    ]);

    // 1. Add 1 Adidas Gazelle ($100.00)
    $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/items', [
            'product_id' => $this->simpleProduct->id,
            'quantity' => 1,
        ])
        ->assertStatus(200);

    // 2. Attempt applying SAVE10 (should fail due to min purchase limit)
    $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/coupon', [
            'code' => 'SAVE10',
        ])
        ->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'A minimum purchase of $150.00 is required to use this coupon.',
        ]);

    // 3. Add one more Gazelle ($200 subtotal)
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/items', [
            'product_id' => $this->simpleProduct->id,
            'quantity' => 1,
        ])
        ->assertStatus(200);

    $itemId = $response->json('data.items.0.id');

    // Update quantity of Gazelle to 2 ($200 subtotal)
    $this->withHeader('X-Cart-Token', $token)
        ->putJson("/api/cart/items/{$itemId}", [
            'quantity' => 2,
        ])
        ->assertStatus(200);

    // 4. Apply SAVE10 (should succeed now)
    $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/coupon', [
            'code' => 'SAVE10',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.discount', '20.00') // 10% of 200
        ->assertJsonPath('data.total', '180.00');

    // 5. Remove coupon
    $this->withHeader('X-Cart-Token', $token)
        ->deleteJson('/api/cart/coupon')
        ->assertStatus(200)
        ->assertJsonPath('data.discount', '0.00')
        ->assertJsonPath('data.total', '200.00');
});

test('coupon is invalidated if subtotal drops below minimum purchase limit', function () {
    $token = Str::random(40);

    $coupon = Coupon::create([
        'code' => 'SAVE10',
        'type' => 'percentage',
        'value' => 10.00,
        'min_purchase_amount' => 150.00,
        'is_active' => true,
    ]);

    // Add 2 Adidas Gazelle ($200 subtotal)
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/items', [
            'product_id' => $this->simpleProduct->id,
            'quantity' => 2,
        ])
        ->assertStatus(200);

    $itemId = $response->json('data.items.0.id');

    // Apply coupon
    $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/cart/coupon', [
            'code' => 'save10', // test case-insensitivity
        ])
        ->assertStatus(200);

    // Reduce quantity of Gazelle to 1 ($100 subtotal)
    // Dynamic recalculation should automatically detach the coupon
    $this->withHeader('X-Cart-Token', $token)
        ->putJson("/api/cart/items/{$itemId}", [
            'quantity' => 1,
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.discount', '0.00')
        ->assertJsonPath('data.total', '100.00')
        ->assertJsonFragment([
            'warnings' => [
                'Applied coupon "SAVE10" was removed: A minimum purchase of $150.00 is required to use this coupon.',
            ],
        ]);
});

test('scheduled purge job deletes stale guest carts', function () {
    // 1. Create fresh guest cart
    $cartFresh = Cart::create([
        'token' => Str::random(40),
    ]);

    // 2. Create stale guest cart
    $cartStale = Cart::create([
        'token' => Str::random(40),
    ]);
    // Manually force updated_at using query builder to bypass Eloquent events
    DB::table('carts')->where('id', $cartStale->id)->update([
        'user_id' => null,
        'updated_at' => now()->subDays(31),
    ]);

    // 3. Create stale user cart (must NOT be deleted)
    $cartUserStale = Cart::create([
        'user_id' => $this->user->id,
        'token' => Str::random(40),
    ]);
    DB::table('carts')->where('id', $cartUserStale->id)->update([
        'updated_at' => now()->subDays(31),
    ]);

    // 4. Run Job
    dispatch(new CleanStaleCartsJob);

    // Verify
    expect(Cart::where('id', $cartFresh->id)->exists())->toBeTrue();
    expect(Cart::where('id', $cartUserStale->id)->exists())->toBeTrue();
    expect(Cart::where('id', $cartStale->id)->exists())->toBeFalse();
});
