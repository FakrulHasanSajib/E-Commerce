<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class CategoryPublicController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {}

    /**
     * Display the hierarchical category tree.
     */
    public function index(): JsonResponse
    {
        $tree = $this->catalogService->getCachedCategoryTree();

        return response()->json([
            'data' => $tree,
        ]);
    }
}
