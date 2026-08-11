<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup basic dummy images
    $this->dummyImage = Media::create([
        'name' => 'test.png',
        'filename' => 'test.png',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/png',
        'size' => 1024,
        'hash' => 'dummy-search-image-hash',
    ]);

    // Setup brands
    $this->brandAdidas = Brand::create(['name' => 'Adidas']);
    $this->brandNike = Brand::create(['name' => 'Nike']);

    // Setup categories
    $this->categoryApparel = Category::create(['name' => 'Apparel']);
    $this->categoryShoes = Category::create(['name' => 'Shoes']);

    // Setup attributes
    $this->colorAttr = Attribute::create(['name' => 'Color', 'slug' => 'color']);
    $this->redValue = AttributeValue::create([
        'attribute_id' => $this->colorAttr->id,
        'value' => 'Red',
        'slug' => 'red',
    ]);
    $this->blueValue = AttributeValue::create([
        'attribute_id' => $this->colorAttr->id,
        'value' => 'Blue',
        'slug' => 'blue',
    ]);

    // Setup products
    // 1. Adidas Red Shoes
    $this->product1 = Product::create([
        'brand_id' => $this->brandAdidas->id,
        'name' => 'Adidas Red Shoes',
        'type' => 'simple',
        'status' => 'published',
        'price' => 120.00,
        'is_active' => true,
    ]);
    $this->product1->categories()->sync([$this->categoryShoes->id => ['is_primary' => true]]);
    $this->product1->attributeValues()->sync([$this->redValue->id]);
    $this->product1->media()->attach($this->dummyImage->id, [
        'mediable_type' => Product::class,
        'collection_name' => 'thumbnail',
        'order' => 0,
    ]);

    // 2. Nike Blue Tracksuit
    $this->product2 = Product::create([
        'brand_id' => $this->brandNike->id,
        'name' => 'Nike Blue Tracksuit',
        'type' => 'simple',
        'status' => 'published',
        'price' => 80.00,
        'is_active' => true,
    ]);
    $this->product2->categories()->sync([$this->categoryApparel->id => ['is_primary' => true]]);
    $this->product2->attributeValues()->sync([$this->blueValue->id]);
    $this->product2->media()->attach($this->dummyImage->id, [
        'mediable_type' => Product::class,
        'collection_name' => 'thumbnail',
        'order' => 0,
    ]);
});

test('public users can search catalog without authentication', function () {
    $this->getJson('/api/products')
        ->assertStatus(200);
});

test('searching text matches product name', function () {
    $response = $this->getJson('/api/products?search=Adidas')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.name'))->toEqual('Adidas Red Shoes');
});

test('filtering catalog by brand and category', function () {
    // Brand Nike
    $response = $this->getJson('/api/products?brand_id='.$this->brandNike->id)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toEqual('Nike Blue Tracksuit');

    // Category Shoes
    $response = $this->getJson('/api/products?category_id='.$this->categoryShoes->id)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toEqual('Adidas Red Shoes');
});

test('filtering by price range', function () {
    // Under $100
    $response = $this->getJson('/api/products?price_max=100')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toEqual('Nike Blue Tracksuit');

    // Over $100
    $response = $this->getJson('/api/products?price_min=100')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toEqual('Adidas Red Shoes');
});

test('storefront index returns correct facets count distribution', function () {
    $response = $this->getJson('/api/products')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data',
            'meta' => [
                'facets' => [
                    'brands',
                    'categories',
                    'attributes',
                ],
            ],
        ]);

    $facets = $response->json('meta.facets');

    // Verify brands facet counts
    expect($facets['brands']['Adidas'])->toEqual(1);
    expect($facets['brands']['Nike'])->toEqual(1);

    // Verify categories facet counts
    expect($facets['categories']['Shoes'])->toEqual(1);
    expect($facets['categories']['Apparel'])->toEqual(1);

    // Verify attributes structured facet counts
    expect($facets['attributes']['color']['red'])->toEqual(1);
    expect($facets['attributes']['color']['blue'])->toEqual(1);
});

test('saving product pushes make searchable job to background queue', function () {
    Queue::fake();

    // Trigger save to fire model saved event which triggers Scout sync index
    $product = new Product;
    $product->brand_id = $this->brandAdidas->id;
    $product->name = 'Adidas Gazelle';
    $product->type = 'simple';
    $product->status = 'published';
    $product->price = 90.00;
    $product->save();

    Queue::assertPushed(MakeSearchable::class);
});
