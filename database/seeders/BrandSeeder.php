<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Media;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mediaLogo = Media::where('mime_type', 'like', 'image/%')->first();
        $logoId = $mediaLogo ? $mediaLogo->id : null;

        $brands = [
            [
                'name' => 'Apple',
                'description' => 'Apple Inc. is an American multinational technology company.',
                'logo_id' => $logoId,
                'is_active' => true,
                'meta_title' => 'Apple Brand Products',
                'meta_description' => 'Browse and buy high quality Apple products.',
            ],
            [
                'name' => 'Samsung',
                'description' => 'Samsung is a South Korean multinational manufacturing conglomerate.',
                'logo_id' => $logoId,
                'is_active' => true,
                'meta_title' => 'Samsung Electronics',
                'meta_description' => 'Explore the Samsung electronics range.',
            ],
            [
                'name' => 'Sony',
                'description' => 'Sony Corporation is a Japanese multinational conglomerate corporation.',
                'logo_id' => null,
                'is_active' => true,
                'meta_title' => 'Sony Brand Store',
                'meta_description' => 'Premium cameras, gaming consoles, and audio equipment by Sony.',
            ],
            [
                'name' => 'Nike',
                'description' => 'Nike, Inc. is an American multinational corporation.',
                'logo_id' => null,
                'is_active' => true,
                'meta_title' => 'Nike Active Wear',
                'meta_description' => 'Just do it with Nike active footwear and apparel.',
            ],
            [
                'name' => 'Adidas',
                'description' => 'Adidas AG is a German multinational corporation.',
                'logo_id' => null,
                'is_active' => false, // Seed one inactive brand to test storefront filter
                'meta_title' => 'Adidas Sports',
                'meta_description' => 'Adidas shoes, sportswear, and accessories.',
            ],
        ];

        foreach ($brands as $brandData) {
            Brand::firstOrCreate(['name' => $brandData['name']], $brandData);
        }
    }
}
