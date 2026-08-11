<?php

use App\Http\Middleware\RedirectMiddleware;
use App\Models\Article;
use App\Models\NotFoundLog;
use App\Models\Product;
use App\Models\UrlRedirect;
use App\Models\User;
use App\Services\Seo\RedirectService;
use App\Services\Seo\SeoService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('administrator');

    $this->product = Product::create([
        'name' => 'SEO Product',
        'slug' => 'seo-product',
        'type' => 'simple',
        'status' => 'published',
        'price' => 1200.00,
        'average_rating' => 4.50,
        'reviews_count' => 10,
        'stock_quantity' => 15,
    ]);
});

test('polymorphic seo metadata attachment and fallback resolution', function () {
    $seoService = app(SeoService::class);

    // 1. Fallback Resolution without custom metadata
    $tags1 = $seoService->getSeoTags($this->product);
    expect($tags1['meta_title'])->toBe('SEO Product | LaraShop');
    expect($tags1['canonical_url'])->toContain('/products/seo-product');

    // 2. Custom SEO Metadata Attachment
    $this->product->seoMetadata()->create([
        'meta_title' => 'Custom Meta Title',
        'meta_description' => 'Custom Meta Description',
        'canonical_url' => 'https://larashop.test/custom-product',
    ]);

    $tags2 = $seoService->getSeoTags($this->product->fresh());
    expect($tags2['meta_title'])->toBe('Custom Meta Title');
    expect($tags2['meta_description'])->toBe('Custom Meta Description');
    expect($tags2['canonical_url'])->toBe('https://larashop.test/custom-product');
});

test('zero query json-ld schema generation consumes denormalized ratings', function () {
    $seoService = app(SeoService::class);

    $schema = $seoService->generateJsonLdSchema($this->product);

    expect($schema['@type'])->toBe('Product');
    expect($schema['name'])->toBe('SEO Product');
    expect((float) $schema['offers']['price'])->toBe(1200.0);
    expect($schema['aggregateRating']['ratingValue'])->toBe(4.5);
    expect($schema['aggregateRating']['reviewCount'])->toBe(10);
});

test('google sitemap index and modular sub-sitemaps rendering', function () {
    Cache::flush();

    // 1. Sitemap Index
    $responseIndex = $this->get('/sitemap.xml')->assertStatus(200);
    expect($responseIndex->getContent())->toContain('<sitemapindex');
    expect($responseIndex->getContent())->toContain('/sitemap-products.xml');

    // 2. Sub-sitemap Products
    $responseSub = $this->get('/sitemap-products.xml')->assertStatus(200);
    expect($responseSub->getContent())->toContain('<urlset');
    expect($responseSub->getContent())->toContain('/products/seo-product');
});

test('redis cached 301 redirect middleware and loop prevention validation', function () {
    $redirectService = app(RedirectService::class);

    // 1. Admin creates redirect from /old-link to /new-link
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/redirects', [
            'source_url' => '/old-link',
            'target_url' => '/new-link',
            'status_code' => 301,
        ])
        ->assertStatus(201);

    expect(UrlRedirect::count())->toBe(1);

    // 2. Loop Prevention: Admin tries to create /new-link -> /old-link (should fail 422)
    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/redirects', [
            'source_url' => '/new-link',
            'target_url' => '/old-link',
        ])
        ->assertStatus(422);

    // 3. Test RedirectMiddleware resolving via Redis memory cache
    $middleware = new RedirectMiddleware($redirectService);
    $request = Request::create('/old-link', 'GET');

    $response = $middleware->handle($request, function () {
        return response('Next middleware', 200);
    });

    expect($response->getStatusCode())->toBe(301);
    expect($response->headers->get('Location'))->toContain('/new-link');
});

test('atomic 404 hit logging and retention purge command', function () {
    $redirectService = app(RedirectService::class);

    // 1. Log 404 hits
    $redirectService->log404NotFound('/broken-url', 'Mozilla/5.0', '127.0.0.1');
    $redirectService->log404NotFound('/broken-url', 'Mozilla/5.0', '127.0.0.1');

    expect(NotFoundLog::count())->toBe(1);
    expect(NotFoundLog::first()->hits_count)->toBe(2);

    // Set last_occurred_at to 40 days ago
    NotFoundLog::first()->update(['last_occurred_at' => Carbon::now()->subDays(40)]);

    // 2. Run Purge Command
    Artisan::call('404:purge-old-logs', ['--days' => 30]);

    expect(NotFoundLog::count())->toBe(0);
});

test('blog post public viewing and views count increment', function () {
    $article = Article::create([
        'title' => 'Laravel SEO Best Practices',
        'slug' => 'laravel-seo-best-practices',
        'body' => 'Comprehensive guide to building fast Laravel e-commerce apps.',
        'status' => 'published',
        'published_at' => Carbon::now(),
        'views_count' => 0,
    ]);

    $response = $this->getJson('/api/blog/posts/laravel-seo-best-practices')
        ->assertStatus(200);

    expect($response->json('data.title'))->toBe('Laravel SEO Best Practices');
    expect($response->json('seo.meta_title'))->toContain('Laravel SEO Best Practices');
    expect($article->fresh()->views_count)->toBe(1);
});
