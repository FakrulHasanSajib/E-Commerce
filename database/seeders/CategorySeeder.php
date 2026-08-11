<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Media;
use App\Services\CatalogService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mediaImage = Media::where('mime_type', 'like', 'image/%')->first();
        $imageId = $mediaImage ? $mediaImage->id : null;

        // 1. Electronics (Root)
        $electronics = Category::firstOrCreate([
            'name' => 'Electronics',
        ], [
            'description' => 'Gadgets, appliances, and other electronics.',
            'parent_id' => null,
            'image_id' => $imageId,
            'is_active' => true,
            'order' => 1,
            'meta_title' => 'Electronics Store',
            'meta_description' => 'Shop the latest electronic devices online.',
        ]);

        // 1.1. Smartphones (Child of Electronics)
        $smartphones = Category::firstOrCreate([
            'name' => 'Smartphones',
            'parent_id' => $electronics->id,
        ], [
            'description' => 'Mobile phones and smart handheld devices.',
            'image_id' => $imageId,
            'is_active' => true,
            'order' => 1,
            'meta_title' => 'Smartphones & Mobiles',
            'meta_description' => 'Buy Android smartphones and iPhones at discount rates.',
        ]);

        // 1.1.1. iPhone Accessories (Grandchild of Electronics)
        Category::firstOrCreate([
            'name' => 'iPhone Accessories',
            'parent_id' => $smartphones->id,
        ], [
            'description' => 'Cases, chargers, and screen protectors for Apple iPhones.',
            'image_id' => null,
            'is_active' => true,
            'order' => 1,
            'meta_title' => 'iPhone Accessories Online',
            'meta_description' => 'Premium covers and chargers for iPhones.',
        ]);

        // 1.1.2. Android Accessories (Grandchild of Electronics)
        Category::firstOrCreate([
            'name' => 'Android Accessories',
            'parent_id' => $smartphones->id,
        ], [
            'description' => 'Accessories tailored for Samsung, Xiaomi, and OnePlus phones.',
            'image_id' => null,
            'is_active' => true,
            'order' => 2,
            'meta_title' => 'Android Phone Accessories',
            'meta_description' => 'Find the best cables and cases for your Android phone.',
        ]);

        // 1.2. Laptops (Child of Electronics)
        Category::firstOrCreate([
            'name' => 'Laptops',
            'parent_id' => $electronics->id,
        ], [
            'description' => 'Notebooks, ultrabooks, and gaming laptops.',
            'image_id' => null,
            'is_active' => true,
            'order' => 2,
            'meta_title' => 'Laptops & MacBooks',
            'meta_description' => 'High performance business and gaming laptops.',
        ]);

        // 2. Apparel (Root)
        $apparel = Category::firstOrCreate([
            'name' => 'Apparel',
        ], [
            'description' => 'Clothing, activewear, and fashion accessories.',
            'parent_id' => null,
            'image_id' => $imageId,
            'is_active' => true,
            'order' => 2,
            'meta_title' => 'Apparel & Fashion Store',
            'meta_description' => 'Update your wardrobe with the latest fashion trends.',
        ]);

        // 2.1. Men\'s Clothing (Child of Apparel)
        Category::firstOrCreate([
            'name' => "Men's Clothing",
            'parent_id' => $apparel->id,
        ], [
            'description' => 'Shirts, jeans, jackets, and footwear for men.',
            'image_id' => null,
            'is_active' => true,
            'order' => 1,
            'meta_title' => "Men's Clothing Online",
            'meta_description' => 'Classic and casual outfits for men.',
        ]);

        // 2.2. Women\'s Clothing (Child of Apparel)
        Category::firstOrCreate([
            'name' => "Women's Clothing",
            'parent_id' => $apparel->id,
        ], [
            'description' => 'Tops, dresses, skirts, and shoes for women.',
            'image_id' => null,
            'is_active' => true,
            'order' => 2,
            'meta_title' => "Women's Fashion Clothing",
            'meta_description' => 'Elegant and daily wear apparel for women.',
        ]);

        // 3. Home Decor (Root, Inactive to test isolation)
        $homeDecor = Category::firstOrCreate([
            'name' => 'Home Decor',
        ], [
            'description' => 'Furniture, lights, and kitchenware.',
            'parent_id' => null,
            'image_id' => null,
            'is_active' => false,
            'order' => 3,
            'meta_title' => 'Home Decor & Furniture',
            'meta_description' => 'Find furniture and decor items.',
        ]);

        // 3.1. Lighting (Active child of inactive parent - must be hidden)
        Category::firstOrCreate([
            'name' => 'Lighting',
            'parent_id' => $homeDecor->id,
        ], [
            'description' => 'Lamps, ceiling lights, and LEDs.',
            'parent_id' => $homeDecor->id,
            'image_id' => null,
            'is_active' => true, // Active, but parent is inactive
            'order' => 1,
            'meta_title' => 'Home Lighting & Lamps',
            'meta_description' => 'Modern lighting fixtures for home decor.',
        ]);

        // Clear category cache tree
        app(CatalogService::class)->clearCategoryTreeCache();
    }
}
