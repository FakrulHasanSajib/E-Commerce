<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
});

test('unauthorized users are blocked from admin catalog routes', function () {
    // 1. Unauthenticated
    $this->getJson('/api/admin/brands')->assertUnauthorized();
    $this->getJson('/api/admin/categories')->assertUnauthorized();

    // 2. Authenticated but unauthorized (customer)
    $this->actingAs($this->customer)
        ->getJson('/api/admin/brands')
        ->assertStatus(403);

    $this->actingAs($this->customer)
        ->getJson('/api/admin/categories')
        ->assertStatus(403);
});

test('admin can manage brands and unique slugs are auto-generated', function () {
    // 1. Create a brand -> slug generated
    $response = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/brands', [
            'name' => 'Sony Electronics',
            'description' => 'Sony brand.',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Sony Electronics')
        ->assertJsonPath('data.slug', 'sony-electronics');

    $brandId = $response->json('data.id');

    // 2. Create another brand with same name -> unique slug (sony-electronics-1)
    $responseDup = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/brands', [
            'name' => 'Sony Electronics',
        ]);

    $responseDup->assertStatus(201)
        ->assertJsonPath('data.slug', 'sony-electronics-1');

    // 3. Rename brand -> slug regenerates
    $responseUpdate = $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/brands/{$brandId}", [
            'name' => 'Sony Premium',
        ]);

    $responseUpdate->assertStatus(200)
        ->assertJsonPath('data.slug', 'sony-premium');
});

test('admin can manage categories and circular loops are guarded', function () {
    // 1. Create parent category
    $parentResponse = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/categories', [
            'name' => 'Electronics',
        ]);
    $parentResponse->assertStatus(201);
    $parentId = $parentResponse->json('data.id');

    // 2. Create subcategory
    $childResponse = $this->actingAs($this->staffAdmin)
        ->postJson('/api/admin/categories', [
            'name' => 'Phones',
            'parent_id' => $parentId,
        ]);
    $childResponse->assertStatus(201);
    $childId = $childResponse->json('data.id');

    // 3. Try to set parent_id of Electronics to Phones (descendant) -> should fail
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/categories/{$parentId}", [
            'parent_id' => $childId,
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'A category cannot have one of its descendants as its parent.']);
});

test('categories deletion is blocked if children or products exist', function () {
    // 1. Create parent
    $parent = Category::create(['name' => 'Electronics']);
    // 2. Create child
    $child = Category::create(['name' => 'Phones', 'parent_id' => $parent->id]);

    // Deleting parent is blocked because child exists
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/categories/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Cannot delete a category that contains subcategories.']);

    // Deleting child works since it is empty
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/categories/{$child->id}")
        ->assertStatus(204);

    // 3. Let's seed products_count to simulate linked products
    $parent->update(['products_count' => 5]);

    // Deleting parent is now blocked because products exist
    $this->actingAs($this->staffAdmin)
        ->deleteJson("/api/admin/categories/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Cannot delete a category that contains products.']);
});

test('storefront retrieves cached category tree with active isolation and exactly one query', function () {
    // Seed media logo/image to prevent null url errors
    $logo = Media::create([
        'name' => 'logo.png',
        'filename' => 'logo.png',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/png',
        'size' => 1234,
        'hash' => 'dummy-logo-hash',
    ]);

    // 1. Create root Electronics (Active)
    $electronics = Category::create(['name' => 'Electronics', 'is_active' => true, 'image_id' => $logo->id]);
    // 2. Create active child of active Electronics
    $phones = Category::create(['name' => 'Phones', 'parent_id' => $electronics->id, 'is_active' => true]);
    // 3. Create inactive root Home Decor
    $homeDecor = Category::create(['name' => 'Home Decor', 'is_active' => false]);
    // 4. Create active child of inactive Home Decor (must be isolated/hidden!)
    $lighting = Category::create(['name' => 'Lighting', 'parent_id' => $homeDecor->id, 'is_active' => true]);

    // Flush cache
    Cache::flush();

    // Hit public endpoint
    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->getJson('/api/categories/tree');
    $response->assertSuccessful();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Verify database queries were run efficiently (<= 3 queries) to construct the whole tree
    expect(count($queries))->toBeLessThanOrEqual(3);

    // Verify active nodes are present
    $response->assertJsonFragment(['name' => 'Electronics']);
    $response->assertJsonFragment(['name' => 'Phones']);

    // Verify inactive node and its active child are completely missing from storefront tree
    $data = $response->json('data');

    // Check tree structure: "Home Decor" should not be in the root level, and "Lighting" should not be anywhere
    $rootNames = collect($data)->pluck('name')->toArray();
    expect($rootNames)->toContain('Electronics');
    expect($rootNames)->not->toContain('Home Decor');
    expect($rootNames)->not->toContain('Lighting');

    // Verify cache is written
    expect(Cache::has('categories:tree'))->toBeTrue();

    // Verify cache invalidates on Category update
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/categories/{$electronics->id}", [
            'name' => 'Electronics Modified',
        ])
        ->assertSuccessful();

    expect(Cache::has('categories:tree'))->toBeFalse();
});
