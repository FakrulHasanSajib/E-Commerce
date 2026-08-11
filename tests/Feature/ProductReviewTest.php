<?php

use App\Models\Media;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('administrator');

    $this->customer = User::factory()->create(['email' => 'customer@larashop.test']);
    $this->customer2 = User::factory()->create(['email' => 'customer2@larashop.test']);

    $this->product = Product::create([
        'name' => 'Review Shoe',
        'slug' => 'review-shoe',
        'type' => 'simple',
        'status' => 'published',
        'price' => 500.00,
        'manage_stock' => true,
        'stock_quantity' => 10,
    ]);

    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Flat Rate',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('customer review submissions unique check and rejected resubmissions upsert', function () {
    // 1. Submit review (rating 4)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 4,
            'title' => 'Good Shoe',
            'comment' => 'Very comfortable.',
        ])
        ->assertStatus(201);

    expect(ProductReview::where('product_id', $this->product->id)->count())->toBe(1);

    // 2. Submit second review on same product fails (422)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'title' => 'Awesome Shoe',
            'comment' => 'Must buy.',
        ])
        ->assertStatus(422);

    // 3. Admin rejects the first review
    $review = ProductReview::first();
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/reviews/{$review->id}/status", [
            'status' => 'rejected',
        ])
        ->assertStatus(200);

    expect($review->fresh()->status)->toBe('rejected');

    // 4. Customer resubmits (should execute Upsert overwriting old rejected row and resetting status to pending)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'title' => 'Awesome Shoe Updated',
            'comment' => 'Correcting my comment.',
        ])
        ->assertStatus(200); // 200 instead of 201 for upserts

    $freshReview = $review->fresh();
    expect(ProductReview::where('product_id', $this->product->id)->count())->toBe(1);
    expect($freshReview->status)->toBe('pending');
    expect($freshReview->rating)->toBe(5);
    expect($freshReview->title)->toBe('Awesome Shoe Updated');
});

test('verified purchaser check based on delivered orders', function () {
    // 1. Submit review without purchase (is_purchased should be false)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 4,
            'title' => 'Nice',
        ])
        ->assertStatus(201);

    expect(ProductReview::first()->is_purchased)->toBeFalse();

    // Clear review
    ProductReview::truncate();

    // 2. Place order containing the product and deliver it
    $order = Order::create([
        'order_number' => 'ORD-REV-1',
        'subtotal' => 500.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 500.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'Customer',
        'customer_email' => 'customer@larashop.test',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
        'user_id' => $this->customer->id,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'name' => 'Review Shoe',
        'quantity' => 1,
        'price' => 500.00,
    ]);

    // 3. Submit review now (should automatically resolve is_purchased as true)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 5,
            'title' => 'Nice indeed',
        ])
        ->assertStatus(201);

    expect(ProductReview::first()->is_purchased)->toBeTrue();
});

test('polymorphic media attachments uploads inside product reviews', function () {
    // Create Media record
    $media = Media::create([
        'name' => 'shoe',
        'filename' => 'shoe.jpg',
        'path' => 'reviews',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'disk' => 'public',
        'hash' => md5('shoe'),
        'uploaded_by' => $this->customer->id,
    ]);

    // Submit review with media attachments
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 4,
            'title' => 'Great product',
            'media_ids' => [$media->id],
        ])
        ->assertStatus(201);

    $review = ProductReview::first();
    expect($review->media()->count())->toBe(1);
    expect($review->media->first()->pivot->collection_name)->toBe('review_media');
});

test('ratings aggregation caching and zero approved reviews null guard', function () {
    // Create two reviews (from customer 1 and customer 2)
    $r1 = ProductReview::create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'rating' => 4,
        'status' => 'pending',
    ]);

    $r2 = ProductReview::create([
        'user_id' => $this->customer2->id,
        'product_id' => $this->product->id,
        'rating' => 2,
        'status' => 'pending',
    ]);

    // Initial cache is 0.00
    expect($this->product->fresh()->average_rating)->toBe('0.00');

    // Approve r1 (Avg should be 4.00, count 1)
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/reviews/{$r1->id}/status", ['status' => 'approved'])
        ->assertStatus(200);

    expect((float) $this->product->fresh()->average_rating)->toBe(4.00);
    expect($this->product->fresh()->reviews_count)->toBe(1);

    // Approve r2 (Avg should be 3.00, count 2)
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/reviews/{$r2->id}/status", ['status' => 'approved'])
        ->assertStatus(200);

    expect((float) $this->product->fresh()->average_rating)->toBe(3.00);
    expect($this->product->fresh()->reviews_count)->toBe(2);

    // Delete r1 (Avg should return to 2.00, count 1)
    $this->actingAs($this->admin, 'sanctum')
        ->deleteJson("/api/admin/reviews/{$r1->id}")
        ->assertStatus(204);

    expect((float) $this->product->fresh()->average_rating)->toBe(2.00);
    expect($this->product->fresh()->reviews_count)->toBe(1);

    // Reject r2 (Avg should fallback to 0.00, count 0 due to null-guard checks)
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/reviews/{$r2->id}/status", ['status' => 'rejected'])
        ->assertStatus(200);

    expect((float) $this->product->fresh()->average_rating)->toBe(0.00);
    expect($this->product->fresh()->reviews_count)->toBe(0);
});

test('customer review update resetting status to pending and re-indexing ratings cache', function () {
    // 1. Submit review
    $review = ProductReview::create([
        'user_id' => $this->customer->id,
        'product_id' => $this->product->id,
        'rating' => 5,
        'status' => 'approved',
    ]);
    // Set initial cache
    $this->product->update(['average_rating' => 5.00, 'reviews_count' => 1]);

    // 2. Customer edits the review (rating 3)
    $this->actingAs($this->customer, 'sanctum')
        ->putJson("/api/products/{$this->product->id}/reviews/{$review->id}", [
            'rating' => 3,
            'title' => 'Updated comment',
        ])
        ->assertStatus(200);

    $freshReview = $review->fresh();
    expect($freshReview->status)->toBe('pending'); // Reset to pending!
    expect($freshReview->rating)->toBe(3);

    // Cache should automatically recalculate removing the edited review from approved rating counts
    expect((float) $this->product->fresh()->average_rating)->toBe(0.00);
    expect($this->product->fresh()->reviews_count)->toBe(0);
});
