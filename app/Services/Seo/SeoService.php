<?php

namespace App\Services\Seo;

use App\Models\Article;
use App\Models\Category;
use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * Resolve SEO tags with fallback logic.
     */
    public function getSeoTags(Model $entity): array
    {
        $seo = $entity->relationLoaded('seoMetadata') ? $entity->seoMetadata : $entity->seoMetadata()->first();

        $title = $entity->name ?? $entity->title ?? 'LaraShop E-Commerce';
        $description = Str::limit(strip_tags($entity->description ?? $entity->summary ?? $entity->excerpt ?? ''), 160);

        $metaTitle = $seo?->meta_title ?: "{$title} | LaraShop";
        $metaDescription = $seo?->meta_description ?: $description;
        $canonicalUrl = $seo?->canonical_url ?: url('/'.Str::plural(strtolower(class_basename($entity))).'/'.($entity->slug ?? $entity->id));

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'og_title' => $seo?->og_title ?: $metaTitle,
            'og_description' => $seo?->og_description ?: $metaDescription,
            'og_image_id' => $seo?->og_image_id,
            'noindex' => (bool) ($seo?->noindex ?? false),
            'nofollow' => (bool) ($seo?->nofollow ?? false),
        ];
    }

    /**
     * Generate Google JSON-LD schema payload (zero-query ratings from denormalized columns).
     */
    public function generateJsonLdSchema(Model $entity): array
    {
        if ($entity instanceof Product) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $entity->name,
                'description' => Str::limit(strip_tags($entity->description ?? ''), 300),
                'sku' => $entity->sku ?? "SKU-{$entity->id}",
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (float) ($entity->sale_price ?? $entity->price),
                    'priceCurrency' => 'BDT',
                    'availability' => $entity->stock_quantity > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ],
            ];

            if ((int) $entity->reviews_count > 0) {
                $schema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (float) $entity->average_rating,
                    'reviewCount' => (int) $entity->reviews_count,
                ];
            }

            return $schema;
        }

        if ($entity instanceof Article) {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $entity->title,
                'description' => Str::limit(strip_tags($entity->excerpt ?? $entity->body), 200),
                'datePublished' => $entity->published_at ? $entity->published_at->toIso8601String() : $entity->created_at->toIso8601String(),
                'dateModified' => $entity->updated_at->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $entity->author?->name ?? 'Admin',
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'LaraShop',
            'url' => url('/'),
        ];
    }

    /**
     * Generate Sitemap Index XML pointing to sub-sitemaps (Google 50k limit compliant).
     */
    public function generateSitemapIndexXml(): string
    {
        return Cache::remember('sitemap:index', 3600, function () {
            $baseUrl = url('/');
            $now = Carbon::now()->toAtomString();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $types = ['products', 'categories', 'articles', 'pages'];
            foreach ($types as $type) {
                $xml .= '<sitemap>';
                $xml .= "<loc>{$baseUrl}/sitemap-{$type}.xml</loc>";
                $xml .= "<lastmod>{$now}</lastmod>";
                $xml .= '</sitemap>';
            }

            $xml .= '</sitemapindex>';

            return $xml;
        });
    }

    /**
     * Generate sub-sitemap XML for a specific model type.
     */
    public function generateSubSitemapXml(string $type): string
    {
        return Cache::remember("sitemap:sub:{$type}", 3600, function () use ($type) {
            $baseUrl = url('/');
            $urls = [];

            if ($type === 'products') {
                $items = Product::where('status', 'published')->get();
                foreach ($items as $item) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/products/{$item->slug}",
                        'lastmod' => $item->updated_at->toAtomString(),
                    ];
                }
            } elseif ($type === 'categories') {
                $items = Category::all();
                foreach ($items as $item) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/categories/{$item->slug}",
                        'lastmod' => $item->updated_at->toAtomString(),
                    ];
                }
            } elseif ($type === 'articles') {
                $items = Article::where('status', 'published')->get();
                foreach ($items as $item) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/blog/{$item->slug}",
                        'lastmod' => $item->updated_at->toAtomString(),
                    ];
                }
            } elseif ($type === 'pages') {
                $items = LandingPage::where('status', 'published')->get();
                foreach ($items as $item) {
                    $urls[] = [
                        'loc' => "{$baseUrl}/landing-pages/{$item->slug}",
                        'lastmod' => $item->updated_at->toAtomString(),
                    ];
                }
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($urls as $u) {
                $xml .= '<url>';
                $xml .= "<loc>{$u['loc']}</loc>";
                $xml .= "<lastmod>{$u['lastmod']}</lastmod>";
                $xml .= '</url>';
            }

            $xml .= '</urlset>';

            return $xml;
        });
    }
}
