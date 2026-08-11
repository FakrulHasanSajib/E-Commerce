<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductDownload;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Global Attributes
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color']);
        $size = Attribute::create(['name' => 'Size', 'slug' => 'size']);

        // Color Values
        $blackVal = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Black', 'slug' => 'black']);
        $redVal = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Red', 'slug' => 'red']);
        $whiteVal = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'White', 'slug' => 'white']);

        // Size Values
        $sVal = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'S', 'slug' => 's']);
        $mVal = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M', 'slug' => 'm']);
        $lVal = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'L', 'slug' => 'l']);
        $xlVal = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'XL', 'slug' => 'xl']);

        // Fetch Seeded Brands & Categories
        $apple = Brand::where('name', 'Apple')->first() ?? Brand::create(['name' => 'Apple']);
        $nike = Brand::where('name', 'Nike')->first() ?? Brand::create(['name' => 'Nike']);

        $electronics = Category::where('name', 'Electronics')->first() ?? Category::create(['name' => 'Electronics']);
        $apparel = Category::where('name', 'Apparel')->first() ?? Category::create(['name' => 'Apparel']);

        // Create Seed Dummy Image
        $dummyImage = Media::create([
            'name' => 'iphone.png',
            'filename' => 'iphone.png',
            'path' => 'media',
            'disk' => 'public',
            'mime_type' => 'image/png',
            'size' => 5000,
            'hash' => 'iphone-dummy-hash',
        ]);

        $bookFile = Media::create([
            'name' => 'guide.pdf',
            'filename' => 'guide.pdf',
            'path' => 'media',
            'disk' => 'public',
            'mime_type' => 'application/pdf',
            'size' => 10000,
            'hash' => 'pdf-dummy-hash',
        ]);

        // 2. Seed Simple Product (iPhone 15 Pro)
        $iphone = Product::create([
            'brand_id' => $apple->id,
            'name' => 'iPhone 15 Pro',
            'description' => 'Apple flagship phone.',
            'summary' => 'iPhone 15 Pro.',
            'type' => 'simple',
            'status' => 'published',
            'sku' => 'IPHONE15PRO',
            'price' => 999.00,
            'sale_price' => 949.00,
            'cost_price' => 600.00,
            'manage_stock' => true,
            'stock_quantity' => 45,
            'is_active' => true,
        ]);
        $iphone->categories()->sync([$electronics->id => ['is_primary' => true]]);
        $iphone->media()->attach($dummyImage->id, [
            'mediable_type' => Product::class,
            'collection_name' => 'thumbnail',
            'order' => 0,
        ]);

        // 3. Seed Variable Product (Nike Air Max)
        $shoes = Product::create([
            'brand_id' => $nike->id,
            'name' => 'Nike Air Max',
            'description' => 'Nike running shoes.',
            'summary' => 'Nike Air Max.',
            'type' => 'variable',
            'status' => 'published',
            'is_active' => true,
        ]);
        $shoes->categories()->sync([$apparel->id => ['is_primary' => true]]);
        $shoes->media()->attach($dummyImage->id, [
            'mediable_type' => Product::class,
            'collection_name' => 'thumbnail',
            'order' => 0,
        ]);

        // Attach overall attribute value pool to variable product
        $shoes->attributeValues()->sync([$blackVal->id, $redVal->id, $mVal->id, $lVal->id]);

        // Create variants
        $v1 = ProductVariant::create([
            'product_id' => $shoes->id,
            'sku' => 'NIKE-AM-BLK-M',
            'price' => 120.00,
            'stock_quantity' => 12,
            'manage_stock' => true,
        ]);
        $v1->attributeValues()->sync([$blackVal->id, $mVal->id]);

        $v2 = ProductVariant::create([
            'product_id' => $shoes->id,
            'sku' => 'NIKE-AM-BLK-L',
            'price' => 120.00,
            'stock_quantity' => 18,
            'manage_stock' => true,
        ]);
        $v2->attributeValues()->sync([$blackVal->id, $lVal->id]);

        $v3 = ProductVariant::create([
            'product_id' => $shoes->id,
            'sku' => 'NIKE-AM-RED-M',
            'price' => 125.00,
            'stock_quantity' => 8,
            'manage_stock' => true,
        ]);
        $v3->attributeValues()->sync([$redVal->id, $mVal->id]);

        // 4. Seed Digital Product (Laravel Advanced Guide E-Book)
        $book = Product::create([
            'name' => 'Laravel Advanced Guide E-Book',
            'description' => 'The ultimate Laravel manual.',
            'summary' => 'Laravel book.',
            'type' => 'digital',
            'status' => 'published',
            'sku' => 'LARAVEL-ADV-GUIDE',
            'price' => 29.00,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        $book->categories()->sync([$electronics->id => ['is_primary' => true]]);

        ProductDownload::create([
            'product_id' => $book->id,
            'media_id' => $bookFile->id,
            'name' => 'Laravel Guide E-Book PDF',
            'download_limit' => 5,
            'expiry_days' => 30,
        ]);

        // Force counts recalculation
        Product::recalculateCountsForBrand($apple->id);
        Product::recalculateCountsForBrand($nike->id);
        Product::recalculateCountsForCategory($electronics->id);
        Product::recalculateCountsForCategory($apparel->id);
    }
}
