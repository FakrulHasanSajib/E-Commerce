<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first() ?? User::create([
            'name' => 'Admin User',
            'email' => 'admin@larashop.test',
            'password' => bcrypt('password'),
        ]);

        // Create folders
        $productsFolder = MediaFolder::create(['name' => 'Products']);
        $electronicsFolder = MediaFolder::create(['name' => 'Electronics', 'parent_id' => $productsFolder->id]);
        $apparelFolder = MediaFolder::create(['name' => 'Apparel', 'parent_id' => $productsFolder->id]);
        $bannersFolder = MediaFolder::create(['name' => 'Banners']);
        $uncategorizedFolder = MediaFolder::create(['name' => 'Uncategorized']);

        // Create tags
        $tags = collect(['featured', 'promo', 'carousel', 'electronics', 'clothing'])
            ->map(fn ($tagName) => Tag::create([
                'name' => $tagName,
                'slug' => Str::slug($tagName),
            ]));

        // Create mock media items
        $media1 = Media::create([
            'folder_id' => $electronicsFolder->id,
            'uploaded_by' => $admin->id,
            'name' => 'iphone-15.jpg',
            'filename' => 'iphone-15.webp',
            'path' => 'media/products',
            'disk' => 'public',
            'mime_type' => 'image/webp',
            'size' => 125430,
            'width' => 1200,
            'height' => 1200,
            'hash' => hash('sha256', 'iphone-15-mock-file-content'),
            'alt_text' => 'iPhone 15 Pro Max Titanium',
            'seo_title' => 'iPhone 15 Pro Max',
            'seo_description' => 'Buy the latest iPhone 15 Pro Max with titanium finish.',
            'conversions' => [
                'thumbnail' => [
                    'path' => 'media/products/iphone-15_thumb.webp',
                    'width' => 150,
                    'height' => 150,
                    'size' => 5420,
                ],
                'medium' => [
                    'path' => 'media/products/iphone-15_medium.webp',
                    'width' => 600,
                    'height' => 600,
                    'size' => 38920,
                ],
            ],
        ]);
        $media1->tags()->attach($tags->where('name', 'electronics')->pluck('id'));
        $media1->tags()->attach($tags->where('name', 'featured')->pluck('id'));

        $media2 = Media::create([
            'folder_id' => $apparelFolder->id,
            'uploaded_by' => $admin->id,
            'name' => 'summer-tshirt.jpg',
            'filename' => 'summer-tshirt.webp',
            'path' => 'media/products',
            'disk' => 'public',
            'mime_type' => 'image/webp',
            'size' => 85230,
            'width' => 800,
            'height' => 1000,
            'hash' => hash('sha256', 'summer-tshirt-mock-file-content'),
            'alt_text' => 'Unisex Summer Cotton T-Shirt',
            'seo_title' => 'Summer Cotton T-Shirt',
            'seo_description' => 'Lightweight cotton summer t-shirt for daily wear.',
            'conversions' => [
                'thumbnail' => [
                    'path' => 'media/products/summer-tshirt_thumb.webp',
                    'width' => 150,
                    'height' => 150,
                    'size' => 3210,
                ],
            ],
        ]);
        $media2->tags()->attach($tags->where('name', 'clothing')->pluck('id'));

        $media3 = Media::create([
            'folder_id' => $bannersFolder->id,
            'uploaded_by' => $admin->id,
            'name' => 'summer-campaign-banner.jpg',
            'filename' => 'summer-campaign-banner.webp',
            'path' => 'media/banners',
            'disk' => 'public',
            'mime_type' => 'image/webp',
            'size' => 452100,
            'width' => 1920,
            'height' => 600,
            'hash' => hash('sha256', 'summer-campaign-banner-mock-file-content'),
            'alt_text' => 'Summer Campaign Banner 2026',
            'seo_title' => 'Summer Campaign Sale',
            'seo_description' => 'Get up to 50% off on our summer collections.',
            'conversions' => [],
        ]);
        $media3->tags()->attach($tags->where('name', 'promo')->pluck('id'));
        $media3->tags()->attach($tags->where('name', 'carousel')->pluck('id'));

        // Seed a video
        Media::create([
            'folder_id' => $uncategorizedFolder->id,
            'uploaded_by' => $admin->id,
            'name' => 'product-review.mp4',
            'filename' => 'product-review.mp4',
            'path' => 'media/videos',
            'disk' => 'public',
            'mime_type' => 'video/mp4',
            'size' => 5242880, // 5MB
            'hash' => hash('sha256', 'video-review-mock-file-content'),
            'conversions' => [],
        ]);
    }
}
