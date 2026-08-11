<?php

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\TaxClass;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->user = User::factory()->create(['email' => 'customer@larashop.test']);

    // 1. Create Tax Classes & Rates (National flat tax rates)
    $this->standardTax = TaxClass::create(['name' => 'Standard Rate', 'slug' => 'standard-rate']);
    $this->standardTax->rate()->create(['name' => 'VAT 15%', 'rate' => 15.00, 'is_active' => true]);

    $this->reducedTax = TaxClass::create(['name' => 'Reduced Rate', 'slug' => 'reduced-rate']);
    $this->reducedTax->rate()->create(['name' => 'VAT 5%', 'rate' => 5.00, 'is_active' => true]);

    // 2. Create products with tax classes and weights
    $this->adidasGazelle = Product::create([
        'name' => 'Adidas Gazelle',
        'type' => 'simple',
        'status' => 'published',
        'price' => 100.00,
        'weight' => 1.50, // 1.5 kg
        'tax_class_id' => $this->standardTax->id,
        'is_active' => true,
    ]);

    $this->adidasSamba = Product::create([
        'name' => 'Adidas Samba',
        'type' => 'simple',
        'status' => 'published',
        'price' => 80.00,
        'weight' => 2.00, // 2.0 kg
        'tax_class_id' => $this->reducedTax->id,
        'is_active' => true,
    ]);

    // 3. Create Shipping Zones & Methods (BD geography context)
    // Zone A: Inside Dhaka (specific district level)
    $this->dhakaZone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->dhakaZone->locations()->create([
        'country_code' => 'BD',
        'division' => 'Dhaka',
        'district' => 'Dhaka',
    ]);
    $this->dhakaFlatMethod = $this->dhakaZone->methods()->create([
        'name' => 'Dhaka Flat Rate',
        'type' => 'flat_rate',
        'cost' => 60.00,
        'is_active' => true,
    ]);

    // Zone B: Outside Dhaka (country fallback level)
    $this->outsideDhakaZone = ShippingZone::create(['name' => 'Outside Dhaka', 'is_active' => true]);
    $this->outsideDhakaZone->locations()->create([
        'country_code' => 'BD',
    ]);

    // Weight based method for outside Dhaka
    $this->weightMethod = $this->outsideDhakaZone->methods()->create([
        'name' => 'Weight Courier',
        'type' => 'weight_based',
        'cost' => 100.00, // base cost
        'is_active' => true,
    ]);
    // Brackets: 0-3kg: $120, 3-6kg: $180
    $this->weightMethod->weightRates()->create(['max_weight' => 3.00, 'cost' => 120.00]);
    $this->weightMethod->weightRates()->create(['max_weight' => 6.00, 'cost' => 180.00]);

    // Clear caches to start clean
    ShippingZone::clearCache();
    Cache::forget('active_tax_rates_by_class');
});

test('tax is calculated correctly with proportional coupon discount', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->adidasGazelle->id, 'quantity' => 1]); // $100
    $cart->items()->create(['product_id' => $this->adidasSamba->id, 'quantity' => 1]);   // $80
    // Subtotal = $180

    // Create a 10% coupon ($18 discount)
    $coupon = Coupon::create([
        'code' => 'SAVE10',
        'type' => 'percentage',
        'value' => 10.00,
        'is_active' => true,
    ]);
    $cart->coupon_id = $coupon->id;
    $cart->save();

    // 1. Calculate
    // Subtotal: 180, Discount: 18
    // Proportional discount:
    // Gazelle: 100 - (18 / 180 * 100) = 100 - 10 = 90 taxable. Tax (15%): 90 * 0.15 = 13.50
    // Samba: 80 - (18 / 180 * 80) = 80 - 8 = 72 taxable. Tax (5%): 72 * 0.05 = 3.60
    // Total Tax = 13.50 + 3.60 = 17.10

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'shipping_method_id' => $this->dhakaFlatMethod->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.subtotal', '180.00')
        ->assertJsonPath('data.discount', '18.00')
        ->assertJsonPath('data.tax_cost', '17.10')
        ->assertJsonPath('data.shipping_cost', '60.00')
        ->assertJsonPath('data.grand_total', '239.10'); // 180 - 18 + 60 + 17.10 = 239.10
});

test('shipping zone matches district priority first then country fallback', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->adidasGazelle->id, 'quantity' => 1]);

    // 1. Target is Dhaka district -> matches Inside Dhaka zone
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.shipping_zone', 'Inside Dhaka')
        ->assertJsonCount(1, 'data.available_shipping_methods')
        ->assertJsonPath('data.available_shipping_methods.0.name', 'Dhaka Flat Rate');

    // 2. Target is Sylhet district -> matches Outside Dhaka country fallback
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Sylhet',
            'district' => 'Sylhet',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.shipping_zone', 'Outside Dhaka')
        ->assertJsonPath('data.available_shipping_methods.0.name', 'Weight Courier');
});

test('weight-based shipping resolves cost and triggers unavailability on overweight limit', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);

    // Add 1 Adidas Gazelle (1.5 kg). Matches 0-3kg bracket ($120.00)
    $cart->items()->create(['product_id' => $this->adidasGazelle->id, 'quantity' => 1]);

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Sylhet',
            'district' => 'Sylhet',
            'shipping_method_id' => $this->weightMethod->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.total_weight', '1.50')
        ->assertJsonPath('data.shipping_cost', '120.00');

    // Add 2 Samba (4.0 kg). Total = 5.5 kg. Matches 3-6kg bracket ($180.00)
    $cart->items()->create(['product_id' => $this->adidasSamba->id, 'quantity' => 2]);

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Sylhet',
            'district' => 'Sylhet',
            'shipping_method_id' => $this->weightMethod->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.total_weight', '5.50')
        ->assertJsonPath('data.shipping_cost', '180.00');

    // Add 1 more Samba (2.0 kg). Total = 7.5 kg. Overweight!
    $cart->items()->create(['product_id' => $this->adidasSamba->id, 'quantity' => 1]);

    // Attempting to calculate with the overweight method selected should throw ValidationException
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Sylhet',
            'district' => 'Sylhet',
            'shipping_method_id' => $this->weightMethod->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_method_id']);
});

test('free shipping coupon overrides shipping costs to zero', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->adidasGazelle->id, 'quantity' => 1]);

    // Apply free shipping coupon
    $coupon = Coupon::create([
        'code' => 'FREESHIP',
        'type' => 'free_shipping',
        'value' => 0.00,
        'is_active' => true,
    ]);
    $cart->coupon_id = $coupon->id;
    $cart->save();

    // Dhaka flat rate cost is $60.00, but should be overridden to $0.00
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'shipping_method_id' => $this->dhakaFlatMethod->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.shipping_cost', '0.00')
        ->assertJsonPath('data.grand_total', '115.00'); // 100 subtotal + 15 tax + 0 shipping = 115
});

test('tax rates and shipping zones query evaluations are cached in Redis', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->adidasGazelle->id, 'quantity' => 1]);

    // 1. Initial calculation runs queries and caches
    $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
        ])
        ->assertStatus(200);

    expect(Cache::has('active_shipping_zones'))->toBeTrue();
    expect(Cache::has('active_tax_rates_by_class'))->toBeTrue();

    // 2. Modify database directly bypass models (so models events don't fire to flush cache)
    DB::table('shipping_zones')->where('id', $this->dhakaZone->id)->update(['name' => 'Modified Name']);
    DB::table('tax_rates')->where('id', $this->standardTax->rate->id)->update(['rate' => 50.00]);

    // 3. Repeat calculation: should still return the cached values (old standard tax 15% and old zone name)
    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout/calculate', [
            'country_code' => 'BD',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.shipping_zone', 'Inside Dhaka')
        ->assertJsonPath('data.tax_cost', '15.00'); // Standard VAT is still computed as 15%, not 50%
});
