<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed settings and roles/permissions
    $this->seed(SettingSeeder::class);
    $this->seed(RbacSeeder::class);

    // Create Admin User (Super Admin)
    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    // Create Staff User (has manage-products permission)
    $this->staffAdmin = User::factory()->create(['email' => 'staff-admin@larashop.test']);
    $this->staffAdmin->givePermissionTo('manage-products');

    // Create Customer (no permissions)
    $this->customer = User::factory()->create(['email' => 'customer@example.com']);

    // Seed dummy media
    $this->dummyImage = Media::create([
        'name' => 'test.png',
        'filename' => 'test.png',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/png',
        'size' => 100,
        'hash' => 'test-hash-image',
    ]);
});

test('storefront catalog listings and detail endpoints are public', function () {
    // 1. Create a published, active product
    $product = Product::create([
        'name' => 'Storefront Product',
        'type' => 'simple',
        'status' => 'published',
        'price' => 50.00,
        'sku' => 'STORE-PROD',
        'is_active' => true,
    ]);

    $this->getJson('/api/products')->assertStatus(200);
    $this->getJson("/api/products/{$product->slug}")->assertStatus(200);
});

test('admin product catalog CRUD routes are protected', function () {
    $this->getJson('/api/admin/products')->assertUnauthorized();

    $this->actingAs($this->customer)
        ->getJson('/api/admin/products')
        ->assertStatus(403);
});

test('admin can manage simple products and auto-updates denormalized counts', function () {
    $brand = Brand::create(['name' => 'Adidas']);
    $category = Category::create(['name' => 'Shoes']);

    // 1. Create a simple product
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/products', [
            'brand_id' => $brand->id,
            'name' => 'Adidas Super Star',
            'type' => 'simple',
            'status' => 'published',
            'sku' => 'ADI-SS-001',
            'price' => 80.00,
            'manage_stock' => true,
            'stock_quantity' => 10,
            'categories' => [
                ['id' => $category->id, 'is_primary' => true],
            ],
            'thumbnail_id' => $this->dummyImage->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Adidas Super Star')
        ->assertJsonPath('data.slug', 'adidas-super-star')
        ->assertJsonPath('data.sku', 'ADI-SS-001');

    $productId = $response->json('data.id');

    // Verify denormalized counts updated to 1
    expect($brand->fresh()->products_count)->toEqual(1);
    expect($category->fresh()->products_count)->toEqual(1);

    // 2. Soft Delete product
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/products/{$productId}")
        ->assertStatus(204);

    // Counts should go back to 0
    expect($brand->fresh()->products_count)->toEqual(0);
    expect($category->fresh()->products_count)->toEqual(0);

    // 3. Restore product
    $this->actingAs($this->staffAdmin)
        ->postJson("/api/admin/products/{$productId}/restore")
        ->assertStatus(200);

    // Counts should go back to 1
    expect($brand->fresh()->products_count)->toEqual(1);
    expect($category->fresh()->products_count)->toEqual(1);
});

test('admin is blocked from creating variable products with duplicate attribute combinations or SKUs', function () {
    $category = Category::create(['name' => 'Apparel']);

    // Seed global attributes
    $color = Attribute::create(['name' => 'Color', 'slug' => 'color']);
    $size = Attribute::create(['name' => 'Size', 'slug' => 'size']);
    $red = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'slug' => 'red']);
    $m = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M', 'slug' => 'm']);

    // 1. Send request with duplicate attribute combination (Red + M twice)
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/products', [
            'name' => 'Duplicate Combo Product',
            'type' => 'variable',
            'status' => 'published',
            'categories' => [
                ['id' => $category->id, 'is_primary' => true],
            ],
            'variants' => [
                [
                    'sku' => 'COMBO-VAR-1',
                    'price' => 30.00,
                    'attribute_value_ids' => [$red->id, $m->id],
                ],
                [
                    'sku' => 'COMBO-VAR-2',
                    'price' => 32.00,
                    'attribute_value_ids' => [$red->id, $m->id], // Duplicate!
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('variants.1.attribute_value_ids');

    // 2. Send request with duplicate system-wide SKU
    ProductVariant::create([
        'product_id' => Product::create(['name' => 'P1', 'type' => 'simple', 'status' => 'draft'])->id,
        'sku' => 'EXISTING-SKU-1',
        'price' => 10,
    ]);

    $responseSku = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/products', [
            'name' => 'Duplicate SKU Product',
            'type' => 'variable',
            'status' => 'published',
            'categories' => [
                ['id' => $category->id, 'is_primary' => true],
            ],
            'variants' => [
                [
                    'sku' => 'EXISTING-SKU-1', // Duplicate system SKU!
                    'price' => 30.00,
                    'attribute_value_ids' => [$red->id],
                ],
            ],
        ]);

    $responseSku->assertStatus(422)
        ->assertJsonValidationErrors('variants.0.sku');
});

test('admin can manage digital products and media ON DELETE RESTRICT safeguard works', function () {
    // Enable foreign keys explicitly in SQLite test connection
    DB::statement('PRAGMA foreign_keys = ON;');

    $category = Category::create(['name' => 'Tech Books']);
    $bookPdf = Media::create([
        'name' => 'manual.pdf',
        'filename' => 'manual.pdf',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'application/pdf',
        'size' => 2048,
        'hash' => 'book-pdf-hash',
    ]);

    // 1. Create digital product with download
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/products', [
            'name' => 'Laravel Advanced Book',
            'type' => 'digital',
            'status' => 'published',
            'sku' => 'LARAVEL-BOOK-001',
            'price' => 29.00,
            'categories' => [
                ['id' => $category->id, 'is_primary' => true],
            ],
            'downloads' => [
                [
                    'name' => 'PDF Manual',
                    'media_id' => $bookPdf->id,
                    'download_limit' => 5,
                    'expiry_days' => 10,
                ],
            ],
        ]);

    $response->assertStatus(201);

    // 2. Attempting to delete the referenced media record must throw a foreign key constraint exception
    expect(fn () => $bookPdf->delete())->toThrow(QueryException::class);
});

test('storefront product listings only eager load the thumbnail collection', function () {
    $category = Category::create(['name' => 'Tech']);

    // Create product
    $product = Product::create([
        'name' => 'Thumbnail Test Product',
        'type' => 'simple',
        'status' => 'published',
        'sku' => 'THUMB-TEST-1',
        'price' => 10.00,
        'is_active' => true,
    ]);
    $product->categories()->sync([$category->id => ['is_primary' => true]]);

    // Attach thumbnail
    $product->media()->attach($this->dummyImage->id, [
        'mediable_type' => Product::class,
        'collection_name' => 'thumbnail',
        'order' => 0,
    ]);

    // Attach gallery image
    $galleryImage = Media::create([
        'name' => 'gallery.png',
        'filename' => 'gallery.png',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/png',
        'size' => 100,
        'hash' => 'gallery-image-hash',
    ]);
    $product->media()->attach($galleryImage->id, [
        'mediable_type' => Product::class,
        'collection_name' => 'gallery',
        'order' => 1,
    ]);

    // Hit storefront listing
    $response = $this->getJson('/api/products');
    $response->assertSuccessful();

    // Verify thumbnail is loaded
    $data = $response->json('data.0');
    expect($data['thumbnail_url'])->not->toBeNull();

    // Verify gallery is empty or not present in listing payload
    expect($data['gallery'])->toBeEmpty();
});
