<?php

namespace App\Services\Marketing;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductFeedService
{
    /**
     * Generate Google Shopping RSS/XML product feed (1-hour Redis cache).
     */
    public function generateGoogleShoppingXml(): string
    {
        return Cache::remember('feed:google_shopping', 3600, function () {
            $baseUrl = url('/');
            $products = Product::where('status', 'published')->get();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
            $xml .= '<channel>';
            $xml .= '<title>LaraShop E-Commerce Products</title>';
            $xml .= "<link>{$baseUrl}</link>";

            foreach ($products as $p) {
                $price = number_format((float) ($p->sale_price ?? $p->price), 2, '.', '').' BDT';
                $availability = $p->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
                $title = htmlspecialchars($p->name);

                $xml .= '<item>';
                $xml .= "<g:id>{$p->id}</g:id>";
                $xml .= "<title>{$title}</title>";
                $xml .= "<link>{$baseUrl}/products/{$p->slug}</link>";
                $xml .= "<g:price>{$price}</g:price>";
                $xml .= "<g:availability>{$availability}</g:availability>";
                $xml .= '<g:condition>new</g:condition>';
                $xml .= '</item>';
            }

            $xml .= '</channel>';
            $xml .= '</rss>';

            return $xml;
        });
    }

    /**
     * Generate Meta / Facebook Catalog CSV product feed (1-hour Redis cache).
     */
    public function generateFacebookCatalogCsv(): string
    {
        return Cache::remember('feed:facebook_catalog', 3600, function () {
            $baseUrl = url('/');
            $products = Product::where('status', 'published')->get();

            $lines = [];
            $lines[] = 'id,title,description,availability,condition,price,link,brand';

            foreach ($products as $p) {
                $price = number_format((float) ($p->sale_price ?? $p->price), 2, '.', '').' BDT';
                $availability = $p->stock_quantity > 0 ? 'in stock' : 'out of stock';
                $title = '"'.str_replace('"', '""', $p->name).'"';
                $desc = '"'.str_replace('"', '""', substr(strip_tags($p->description ?? ''), 0, 200)).'"';
                $link = "{$baseUrl}/products/{$p->slug}";

                $lines[] = "{$p->id},{$title},{$desc},{$availability},new,{$price},{$link},LaraShop";
            }

            return implode("\n", $lines);
        });
    }
}
