<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Services\LandingPage\LandingPageService;
use Illuminate\Http\Request;

class LandingPageAdminController extends Controller
{
    public function __construct(protected LandingPageService $landingPageService) {}

    /**
     * List all landing pages.
     */
    public function index()
    {
        $pages = LandingPage::withCount(['sections', 'versions'])->latest()->paginate(15);

        return response()->json([
            'data' => $pages->items(),
            'meta' => [
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'total' => $pages->total(),
            ],
        ]);
    }

    /**
     * Create draft landing page.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:landing_pages,slug',
            'status' => 'nullable|string|in:draft,published,scheduled',
            'template' => 'nullable|string|max:100',
            'published_at' => 'nullable|date',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'layout_settings' => 'nullable|array',
            'sections' => 'nullable|array',
        ]);

        $user = $request->user('sanctum');

        $page = $this->landingPageService->createPage($validated, $user?->id);

        return response()->json([
            'message' => 'Landing page created successfully.',
            'data' => $page->load(['sections.media', 'versions']),
        ], 201);
    }

    /**
     * Show page details for builder preview.
     */
    public function show($id)
    {
        $page = LandingPage::with(['sections.media', 'versions.creator'])->findOrFail($id);

        return response()->json([
            'data' => $page,
        ]);
    }

    /**
     * Update page settings and sections.
     */
    public function update(Request $request, $id)
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'slug' => "nullable|string|max:255|unique:landing_pages,slug,{$id}",
            'status' => 'nullable|string|in:draft,published,scheduled',
            'template' => 'nullable|string|max:100',
            'published_at' => 'nullable|date',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'layout_settings' => 'nullable|array',
            'sections' => 'nullable|array',
        ]);

        $user = $request->user('sanctum');

        $updatedPage = $this->landingPageService->updatePage($page, $validated, $user?->id);

        return response()->json([
            'message' => 'Landing page updated successfully.',
            'data' => $updatedPage->load(['sections.media', 'versions']),
        ]);
    }

    /**
     * Reorder sections.
     */
    public function reorderSections(Request $request, $id)
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|integer|exists:landing_page_sections,id',
            'positions.*.position' => 'required|integer|min:0',
        ]);

        $this->landingPageService->reorderSections($page, $validated['positions']);

        return response()->json([
            'message' => 'Sections reordered successfully.',
        ]);
    }

    /**
     * Publish or schedule page.
     */
    public function publish(Request $request, $id)
    {
        $page = LandingPage::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:published,scheduled',
            'published_at' => 'nullable|date',
        ]);

        $user = $request->user('sanctum');

        $updatedPage = $this->landingPageService->updatePage($page, $validated, $user?->id);

        return response()->json([
            'message' => "Landing page status updated to {$updatedPage->status}.",
            'data' => $updatedPage,
        ]);
    }

    /**
     * Get version history.
     */
    public function versions($id)
    {
        $page = LandingPage::findOrFail($id);

        return response()->json([
            'data' => $page->versions()->with('creator')->get(),
        ]);
    }

    /**
     * Rollback page to a snapshot version.
     */
    public function rollback($id, $versionId)
    {
        $page = LandingPage::findOrFail($id);

        $restoredPage = $this->landingPageService->rollbackToVersion($page, (int) $versionId);

        return response()->json([
            'message' => 'Landing page layout restored from version snapshot.',
            'data' => $restoredPage->load('sections.media'),
        ]);
    }
}
