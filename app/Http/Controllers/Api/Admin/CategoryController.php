<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::with(['image', 'parent'])->paginate(15);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'image_id' => 'nullable|integer|exists:media,id',
            'is_active' => 'boolean',
            'order' => 'integer',
            'meta_title' => 'nullable|string|max:150',
            'meta_description' => 'nullable|string|max:255',
        ]);

        $category = Category::create($validated);

        // Evict cached category tree
        $this->catalogService->clearCategoryTreeCache();

        return (new CategoryResource($category->load(['image', 'parent'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): CategoryResource
    {
        $category = Category::with(['image', 'parent', 'children'])->findOrFail($id);

        return new CategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse|CategoryResource
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'slug' => "sometimes|required|string|max:100|unique:categories,slug,{$id}",
            'description' => 'nullable|string',
            'parent_id' => "nullable|integer|exists:categories,id|different:{$id}",
            'image_id' => 'nullable|integer|exists:media,id',
            'is_active' => 'boolean',
            'order' => 'integer',
            'meta_title' => 'nullable|string|max:150',
            'meta_description' => 'nullable|string|max:255',
        ]);

        // Prevent circular dependency (e.g. parent_id is set to a child/descendant of this category)
        if (isset($validated['parent_id']) && $validated['parent_id'] !== $category->parent_id) {
            if ($this->isDescendant($category, $validated['parent_id'])) {
                return response()->json([
                    'message' => 'A category cannot have one of its descendants as its parent.',
                ], 422);
            }
        }

        $category->update($validated);

        // Evict cached category tree
        $this->catalogService->clearCategoryTreeCache();

        return new CategoryResource($category->load(['image', 'parent']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): Response|JsonResponse
    {
        $category = Category::findOrFail($id);

        // Check if category has subcategories
        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a category that contains subcategories.',
            ], 422);
        }

        // Check if category has products
        if ($category->products_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a category that contains products.',
            ], 422);
        }

        $category->delete();

        // Evict cached category tree
        $this->catalogService->clearCategoryTreeCache();

        return response()->noContent();
    }

    /**
     * Check if a parent candidate is a descendant of the category.
     */
    protected function isDescendant(Category $category, int $parentId): bool
    {
        $parent = Category::find($parentId);
        while ($parent) {
            if ($parent->parent_id === $category->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }
}
