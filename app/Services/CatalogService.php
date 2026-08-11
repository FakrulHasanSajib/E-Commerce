<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CatalogService
{
    /**
     * Retrieve the fully nested, cached category tree for the storefront.
     */
    public function getCachedCategoryTree(): array
    {
        return Cache::rememberForever('categories:tree', function () {
            // Retrieve all active categories with their cover image in exactly ONE query
            $categories = Category::with('image')
                ->where('is_active', true)
                ->orderBy('order', 'asc')
                ->get();

            return $this->buildTreeInMemory($categories);
        });
    }

    /**
     * Clear the cached category tree from Redis.
     */
    public function clearCategoryTreeCache(): void
    {
        Cache::forget('categories:tree');
    }

    /**
     * Build the hierarchical tree structure recursively in memory.
     */
    protected function buildTreeInMemory(Collection $categories, ?int $parentId = null): array
    {
        $branch = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $children = $this->buildTreeInMemory($categories, $category->id);

                $imageUrl = null;
                if ($category->image) {
                    $imageUrl = Storage::disk($category->image->disk)->url(
                        $category->image->path.'/'.$category->image->filename
                    );
                }

                $branch[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'image_id' => $category->image_id,
                    'image_url' => $imageUrl,
                    'is_active' => $category->is_active,
                    'order' => $category->order,
                    'products_count' => $category->products_count,
                    'meta_title' => $category->meta_title,
                    'meta_description' => $category->meta_description,
                    'children' => $children,
                ];
            }
        }

        return $branch;
    }
}
