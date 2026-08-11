<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LandingPage\LandingPageService;

class LandingPagePublicController extends Controller
{
    public function __construct(protected LandingPageService $landingPageService) {}

    /**
     * Get published landing page structure by slug.
     */
    public function show(string $slug)
    {
        $pageData = $this->landingPageService->getPublicPage($slug);

        if (! $pageData) {
            return response()->json([
                'message' => 'Landing page not found or unpublished.',
            ], 404);
        }

        return response()->json([
            'data' => $pageData,
        ], 200);
    }
}
