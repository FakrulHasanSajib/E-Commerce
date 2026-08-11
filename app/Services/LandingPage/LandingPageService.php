<?php

namespace App\Services\LandingPage;

use App\Models\LandingPage;
use App\Models\LandingPageSection;
use App\Models\LandingPageVersion;
use App\Services\SettingsManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LandingPageService
{
    /**
     * Create a new draft landing page.
     */
    public function createPage(array $data, ?int $userId = null): LandingPage
    {
        return DB::transaction(function () use ($data, $userId) {
            $page = LandingPage::create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'status' => $data['status'] ?? 'draft',
                'template' => $data['template'] ?? 'default',
                'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : null,
                'header_settings' => $data['header_settings'] ?? null,
                'footer_settings' => $data['footer_settings'] ?? null,
                'layout_settings' => $data['layout_settings'] ?? null,
            ]);

            if (! empty($data['sections'])) {
                $this->saveSections($page, $data['sections']);
            }

            $this->createVersionSnapshot($page, $userId);
            $this->clearCache($page->slug);

            return $page;
        });
    }

    /**
     * Update landing page settings and sections.
     */
    public function updatePage(LandingPage $page, array $data, ?int $userId = null): LandingPage
    {
        return DB::transaction(function () use ($page, $data, $userId) {
            $page->update([
                'title' => $data['title'] ?? $page->title,
                'slug' => $data['slug'] ?? $page->slug,
                'status' => $data['status'] ?? $page->status,
                'template' => $data['template'] ?? $page->template,
                'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : $page->published_at,
                'header_settings' => array_key_exists('header_settings', $data) ? $data['header_settings'] : $page->header_settings,
                'footer_settings' => array_key_exists('footer_settings', $data) ? $data['footer_settings'] : $page->footer_settings,
                'layout_settings' => array_key_exists('layout_settings', $data) ? $data['layout_settings'] : $page->layout_settings,
            ]);

            if (array_key_exists('sections', $data)) {
                $this->saveSections($page, $data['sections']);
            }

            $this->createVersionSnapshot($page, $userId);
            $this->clearCache($page->slug);

            return $page;
        });
    }

    /**
     * Reorder sections position.
     */
    public function reorderSections(LandingPage $page, array $positions): void
    {
        DB::transaction(function () use ($page, $positions) {
            foreach ($positions as $item) {
                $page->sections()->where('id', $item['id'])->update([
                    'position' => $item['position'],
                ]);
            }
            $this->clearCache($page->slug);
        });
    }

    /**
     * Create a snapshot of current page configuration (prunes history beyond 10 versions).
     */
    public function createVersionSnapshot(LandingPage $page, ?int $userId = null): LandingPageVersion
    {
        $maxVersion = (int) ($page->versions()->max('version_number') ?? 0);

        $snapshot = [
            'title' => $page->title,
            'slug' => $page->slug,
            'header_settings' => $page->header_settings,
            'footer_settings' => $page->footer_settings,
            'layout_settings' => $page->layout_settings,
            'sections' => $page->sections()->with('media')->get()->toArray(),
        ];

        $version = LandingPageVersion::create([
            'landing_page_id' => $page->id,
            'version_number' => $maxVersion + 1,
            'snapshot_data' => $snapshot,
            'created_by' => $userId,
        ]);

        // Auto-prune versions keeping only the 10 most recent
        $allVersions = $page->versions()->orderBy('version_number', 'desc')->get();
        if ($allVersions->count() > 10) {
            $allVersions->slice(10)->each->delete();
        }

        return $version;
    }

    /**
     * Rollback page to a previous version snapshot.
     */
    public function rollbackToVersion(LandingPage $page, int $versionId): LandingPage
    {
        $version = $page->versions()->findOrFail($versionId);
        $data = $version->snapshot_data;

        return DB::transaction(function () use ($page, $data) {
            $page->update([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'header_settings' => $data['header_settings'],
                'footer_settings' => $data['footer_settings'],
                'layout_settings' => $data['layout_settings'],
            ]);

            // Recreate sections
            $page->sections()->delete();
            if (! empty($data['sections'])) {
                foreach ($data['sections'] as $secData) {
                    $sec = LandingPageSection::create([
                        'landing_page_id' => $page->id,
                        'type' => $secData['type'],
                        'title' => $secData['title'] ?? null,
                        'content' => $secData['content'] ?? null,
                        'is_visible' => $secData['is_visible'] ?? true,
                        'position' => $secData['position'] ?? 0,
                        'device_visibility' => $secData['device_visibility'] ?? 'all',
                    ]);

                    if (! empty($secData['media'])) {
                        $mediaIds = array_column($secData['media'], 'id');
                        $this->syncSectionMedia($sec, $mediaIds);
                    }
                }
            }

            $this->clearCache($page->slug);

            return $page;
        });
    }

    /**
     * Get published landing page layout structure with global settings fallback.
     */
    public function getPublicPage(string $slug): ?array
    {
        return Cache::remember("landing_page:{$slug}", 60, function () use ($slug) {
            $now = Carbon::now();

            $page = LandingPage::where('slug', $slug)
                ->where(function ($q) use ($now) {
                    $q->where('status', 'published')
                        ->orWhere(function ($sub) use ($now) {
                            $sub->where('status', 'scheduled')
                                ->whereNotNull('published_at')
                                ->where('published_at', '<=', $now);
                        });
                })
                ->with(['sections' => function ($q) {
                    $q->where('is_visible', true)->orderBy('position', 'asc')->with('media');
                }])
                ->first();

            if (! $page) {
                return null;
            }

            // Resolve Header Settings with Global Fallbacks
            $headerSettings = $this->resolveHeaderSettings($page->header_settings);
            // Resolve Footer Settings with Global Fallbacks
            $footerSettings = $this->resolveFooterSettings($page->footer_settings);

            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'template' => $page->template,
                'header_settings' => $headerSettings,
                'footer_settings' => $footerSettings,
                'layout_settings' => $page->layout_settings,
                'sections' => $page->sections->toArray(),
            ];
        });
    }

    /**
     * Save/Update sections for a landing page.
     */
    protected function saveSections(LandingPage $page, array $sections): void
    {
        $existingIds = [];

        foreach ($sections as $index => $secData) {
            $content = $secData['content'] ?? [];

            // Sanitize custom HTML blocks to prevent XSS
            if (($secData['type'] ?? '') === 'custom_html' && isset($content['html_content'])) {
                $content['html_content'] = $this->sanitizeHtml($content['html_content']);
            }

            $section = LandingPageSection::updateOrCreate(
                [
                    'id' => $secData['id'] ?? null,
                    'landing_page_id' => $page->id,
                ],
                [
                    'type' => $secData['type'],
                    'title' => $secData['title'] ?? null,
                    'content' => $content,
                    'is_visible' => $secData['is_visible'] ?? true,
                    'position' => $secData['position'] ?? $index,
                    'device_visibility' => $secData['device_visibility'] ?? 'all',
                ]
            );

            $existingIds[] = $section->id;

            // Sync media attachments to mediables table
            if (! empty($secData['media_ids'])) {
                $this->syncSectionMedia($section, $secData['media_ids']);
            }
        }

        // Delete removed sections
        $page->sections()->whereNotIn('id', $existingIds)->delete();
    }

    /**
     * Sync polymorphic media attachments to mediables table.
     */
    protected function syncSectionMedia(LandingPageSection $section, array $mediaIds): void
    {
        $syncData = [];
        foreach ($mediaIds as $idx => $mediaId) {
            $syncData[$mediaId] = [
                'mediable_type' => LandingPageSection::class,
                'collection_name' => 'section_media',
                'order' => $idx + 1,
            ];
        }

        $section->media()->sync($syncData);
    }

    /**
     * Sanitize HTML content to prevent XSS scripts.
     */
    protected function sanitizeHtml(string $html): string
    {
        return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
    }

    /**
     * Resolve header settings with fallback to global settings.
     */
    protected function resolveHeaderSettings(?array $pageHeader): array
    {
        $settingsManager = app(SettingsManager::class);
        $defaults = [
            'logo' => true,
            'search' => true,
            'wishlist_icon' => true,
            'cart_icon' => true,
            'login_menu' => true,
            'top_bar' => true,
            'language_switcher' => true,
        ];

        $resolved = [];
        foreach ($defaults as $key => $defaultVal) {
            if (is_array($pageHeader) && array_key_exists($key, $pageHeader) && $pageHeader[$key] !== null) {
                $resolved[$key] = (bool) $pageHeader[$key];
            } else {
                $resolved[$key] = (bool) $settingsManager->get("header.{$key}", $defaultVal);
            }
        }

        return $resolved;
    }

    /**
     * Resolve footer settings with fallback to global settings.
     */
    protected function resolveFooterSettings(?array $pageFooter): array
    {
        $settingsManager = app(SettingsManager::class);
        $defaults = [
            'about' => true,
            'links' => true,
            'social_icons' => true,
            'newsletter_box' => true,
            'payment_icons' => true,
            'copyright_bar' => true,
            'contact_block' => true,
        ];

        $resolved = [];
        foreach ($defaults as $key => $defaultVal) {
            if (is_array($pageFooter) && array_key_exists($key, $pageFooter) && $pageFooter[$key] !== null) {
                $resolved[$key] = (bool) $pageFooter[$key];
            } else {
                $resolved[$key] = (bool) $settingsManager->get("footer.{$key}", $defaultVal);
            }
        }

        return $resolved;
    }

    /**
     * Invalidate Redis cache.
     */
    public function clearCache(string $slug): void
    {
        Cache::forget("landing_page:{$slug}");
    }
}
