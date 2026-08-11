<?php

use App\Models\LandingPage;
use App\Models\LandingPageSection;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('administrator');
});

test('landing page creation, section addition, and reordering', function () {
    // 1. Create draft page with sections
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/landing-pages', [
            'title' => 'Black Friday Sale',
            'slug' => 'black-friday',
            'status' => 'draft',
            'sections' => [
                [
                    'type' => 'hero_slider',
                    'title' => 'Main Hero',
                    'position' => 0,
                ],
                [
                    'type' => 'featured_products',
                    'title' => 'Top Deals',
                    'position' => 1,
                ],
            ],
        ])
        ->assertStatus(201);

    $pageId = $response->json('data.id');
    expect(LandingPage::count())->toBe(1);
    expect(LandingPageSection::count())->toBe(2);

    $page = LandingPage::find($pageId);
    $sec1 = $page->sections[0];
    $sec2 = $page->sections[1];

    // 2. Reorder sections
    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/landing-pages/{$pageId}/sections/reorder", [
            'positions' => [
                ['id' => $sec1->id, 'position' => 1],
                ['id' => $sec2->id, 'position' => 0],
            ],
        ])
        ->assertStatus(200);

    expect($sec1->fresh()->position)->toBe(1);
    expect($sec2->fresh()->position)->toBe(0);
});

test('global settings fallback vs page level overrides on public rendering', function () {
    // Page overrides search to false, leaves wishlist_icon as null (which falls back to global default true)
    $page = LandingPage::create([
        'title' => 'Promo Campaign',
        'slug' => 'promo-campaign',
        'status' => 'published',
        'header_settings' => [
            'search' => false,
            'wishlist_icon' => null,
        ],
    ]);

    Cache::flush();

    $response = $this->getJson('/api/landing-pages/promo-campaign')
        ->assertStatus(200);

    $header = $response->json('data.header_settings');
    expect($header['search'])->toBeFalse(); // Explicit Page Override
    expect($header['wishlist_icon'])->toBeTrue(); // Global Setting Fallback
});

test('polymorphic media attachments sync for section orphan protection', function () {
    $media = Media::create([
        'name' => 'banner',
        'filename' => 'banner.jpg',
        'path' => 'sliders',
        'mime_type' => 'image/jpeg',
        'size' => 2048,
        'disk' => 'public',
        'hash' => md5('banner'),
        'uploaded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/landing-pages', [
            'title' => 'Media Landing',
            'slug' => 'media-landing',
            'sections' => [
                [
                    'type' => 'hero_slider',
                    'title' => 'Slider Section',
                    'media_ids' => [$media->id],
                ],
            ],
        ])
        ->assertStatus(201);

    $section = LandingPageSection::first();
    expect($section->media()->count())->toBe(1);
    expect($section->media->first()->pivot->collection_name)->toBe('section_media');
});

test('scheduled publishing command transitions status and flushes cache', function () {
    // Page scheduled 10 minutes in the future (not available yet)
    $page = LandingPage::create([
        'title' => 'Scheduled Summer Launch',
        'slug' => 'summer-launch',
        'status' => 'scheduled',
        'published_at' => Carbon::now()->addMinutes(10),
    ]);

    Cache::flush();

    // Storefront returns 404 while in future
    $this->getJson('/api/landing-pages/summer-launch')->assertStatus(404);

    // Advance time past published_at
    $page->update(['published_at' => Carbon::now()->subMinutes(5)]);

    // Execute scheduler command
    Artisan::call('landing-pages:publish-scheduled');

    expect($page->fresh()->status)->toBe('published');

    // Storefront now returns 200
    $this->getJson('/api/landing-pages/summer-launch')->assertStatus(200);
});

test('version history auto pruning and rollback functionality', function () {
    $page = LandingPage::create([
        'title' => 'Dynamic Page',
        'slug' => 'dynamic-page',
        'status' => 'draft',
    ]);

    // Create 12 updates (should prune oldest and keep max 10 versions)
    for ($i = 1; $i <= 12; $i++) {
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/landing-pages/{$page->id}", [
                'title' => "Dynamic Page Revision {$i}",
            ]);
    }

    expect($page->versions()->count())->toBe(10); // Pruned to 10 max!

    // Rollback to version snapshot
    $oldestVersion = $page->versions()->first(); // Revision 12 (latest version)
    $firstAvailable = $page->versions()->get()->last(); // Oldest retained version

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/admin/landing-pages/{$page->id}/versions/{$firstAvailable->id}/rollback")
        ->assertStatus(200);

    expect($page->fresh()->title)->toBe($firstAvailable->snapshot_data['title']);
});

test('xss html sanitization on custom html sections', function () {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/admin/landing-pages', [
            'title' => 'Custom HTML Page',
            'slug' => 'custom-html-page',
            'sections' => [
                [
                    'type' => 'custom_html',
                    'title' => 'HTML Block',
                    'content' => [
                        'html_content' => '<script>alert("XSS Attack");</script><div>Safe Content</div>',
                    ],
                ],
            ],
        ])
        ->assertStatus(201);

    $section = LandingPageSection::first();
    // <script> should be stripped out!
    expect($section->content['html_content'])->not()->toContain('<script>');
    expect($section->content['html_content'])->toContain('<div>Safe Content</div>');
});
