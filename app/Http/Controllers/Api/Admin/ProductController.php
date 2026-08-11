<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDownload;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with(['brand', 'categories', 'media'])
            ->orderBy('id', 'desc');

        // Search name/SKU
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($v) use ($search) {
                        $v->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->input('category_id'));
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Include soft deleted products if requested
        if ($request->boolean('withTrashed')) {
            $query->withTrashed();
        }

        return ProductResource::collection($query->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Product::class);

        $validated = $this->validateProduct($request);

        return DB::transaction(function () use ($request, $validated) {
            $product = new Product;
            $this->saveProductData($product, $request, $validated);

            return new ProductResource($product->load(['brand', 'categories', 'media', 'variants.attributeValues', 'downloads']));
        });
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::withTrashed()
            ->with(['brand', 'categories', 'media', 'variants.attributeValues.attribute', 'downloads'])
            ->findOrFail($id);

        Gate::authorize('view', $product);

        return new ProductResource($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        Gate::authorize('update', $product);

        $validated = $this->validateProduct($request, $product);

        return DB::transaction(function () use ($product, $request, $validated) {
            $this->saveProductData($product, $request, $validated);

            return new ProductResource($product->load(['brand', 'categories', 'media', 'variants.attributeValues', 'downloads']));
        });
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        Gate::authorize('delete', $product);

        $product->delete();

        return response()->json(null, 204);
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore($id)
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $product);

        $product->restore();

        return new ProductResource($product->load(['brand', 'categories', 'media', 'variants.attributeValues', 'downloads']));
    }

    /**
     * Validate product input details.
     */
    protected function validateProduct(Request $request, ?Product $product = null): array
    {
        $productId = $product?->id ?? 'NULL';

        $rules = [
            'brand_id' => 'nullable|exists:brands,id',
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:products,slug,'.$productId,
            'description' => 'nullable|string',
            'summary' => 'nullable|string',
            'type' => 'required|string|in:simple,variable,digital',
            'status' => 'required|string|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'is_active' => 'boolean',
            'meta_title' => 'nullable|string|max:150',
            'meta_description' => 'nullable|string|max:255',

            // Simple & Digital fields
            'sku' => 'required_if:type,simple,digital|nullable|string|max:100|unique:products,sku,'.$productId,
            'price' => 'required_if:type,simple,digital|nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lte:price',
            'cost_price' => 'nullable|numeric|min:0',
            'manage_stock' => 'boolean',
            'stock_quantity' => 'required_if:manage_stock,true|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',

            // Physical shipping dimensions
            'weight' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',

            // Categories
            'categories' => 'required|array|min:1',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.is_primary' => 'boolean',

            // Media
            'thumbnail_id' => 'nullable|exists:media,id',
            'gallery' => 'nullable|array',
            'gallery.*.id' => 'required|exists:media,id',
            'gallery.*.order' => 'integer|min:0',

            // Variants (for variable products)
            'variants' => 'required_if:type,variable|array',
            'variants.*.sku' => 'required_with:variants|string|max:100',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.sale_price' => 'nullable|numeric|min:0|lte:variants.*.price',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.manage_stock' => 'boolean',
            'variants.*.stock_quantity' => 'required_if:variants.*.manage_stock,true|integer|min:0',
            'variants.*.image_id' => 'nullable|exists:media,id',
            'variants.*.attribute_value_ids' => 'required_with:variants|array|min:1',
            'variants.*.attribute_value_ids.*' => 'required|exists:attribute_values,id',

            // Downloads (for digital products)
            'downloads' => 'required_if:type,digital|array',
            'downloads.*.name' => 'required|string|max:100',
            'downloads.*.media_id' => 'required|exists:media,id',
            'downloads.*.download_limit' => 'nullable|integer|min:1',
            'downloads.*.expiry_days' => 'nullable|integer|min:1',
        ];

        $validated = $request->validate($rules);

        // System-wide variant SKU unique check
        if ($request->has('variants')) {
            foreach ($request->input('variants') as $index => $varInput) {
                $existingVar = ProductVariant::where('sku', $varInput['sku'])
                    ->when($product, fn ($q) => $q->where('product_id', '!=', $product->id))
                    ->exists();
                if ($existingVar) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.sku" => "The variant SKU '{$varInput['sku']}' has already been taken.",
                    ]);
                }
            }
        }

        // Duplicate variant combinations check
        if ($request->input('type') === 'variable') {
            $combinations = [];
            foreach ($request->input('variants', []) as $index => $varInput) {
                $valIds = $varInput['attribute_value_ids'] ?? [];
                sort($valIds);
                $comboKey = implode(',', $valIds);
                if (in_array($comboKey, $combinations)) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_value_ids" => 'Duplicate variant attribute combination is not allowed.',
                    ]);
                }
                $combinations[] = $comboKey;
            }
        }

        return $validated;
    }

    /**
     * Save the product fields, variants, downloads, and media.
     */
    protected function saveProductData(Product $product, Request $request, array $validated)
    {
        $product->fill([
            'brand_id' => $validated['brand_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        if (in_array($validated['type'], ['simple', 'digital'])) {
            $product->sku = $validated['sku'];
            $product->price = $validated['price'];
            $product->sale_price = $validated['sale_price'] ?? null;
            $product->cost_price = $validated['cost_price'] ?? null;
            $product->manage_stock = $validated['manage_stock'] ?? false;
            $product->stock_quantity = $validated['stock_quantity'] ?? 0;
            $product->low_stock_threshold = $validated['low_stock_threshold'] ?? null;
        } else {
            $product->sku = null;
            $product->price = null;
            $product->sale_price = null;
            $product->cost_price = null;
            $product->manage_stock = false;
            $product->stock_quantity = 0;
            $product->low_stock_threshold = null;
        }

        if ($validated['type'] !== 'digital') {
            $product->weight = $validated['weight'] ?? null;
            $product->width = $validated['width'] ?? null;
            $product->height = $validated['height'] ?? null;
            $product->length = $validated['length'] ?? null;
        } else {
            $product->weight = null;
            $product->width = null;
            $product->height = null;
            $product->length = null;
        }

        if ($request->filled('slug')) {
            $product->slug = $validated['slug'];
        }

        // Get category IDs before syncing to update old category counts on removal
        $oldCategoryIds = $product->categories()->pluck('categories.id')->toArray();

        $product->save();

        // 1. Sync categories
        $categoriesInput = $request->input('categories', []);
        $syncCategories = [];
        foreach ($categoriesInput as $cat) {
            $syncCategories[$cat['id']] = ['is_primary' => $cat['is_primary'] ?? false];
        }
        $product->categories()->sync($syncCategories);

        // 2. Sync media
        $product->media()->detach();
        if ($request->filled('thumbnail_id')) {
            $product->media()->attach($request->input('thumbnail_id'), [
                'mediable_type' => Product::class,
                'collection_name' => 'thumbnail',
                'order' => 0,
            ]);
        }
        if ($request->has('gallery')) {
            foreach ($request->input('gallery') as $item) {
                $product->media()->attach($item['id'], [
                    'mediable_type' => Product::class,
                    'collection_name' => 'gallery',
                    'order' => $item['order'] ?? 0,
                ]);
            }
        }

        // 3. Sync variants & variant attribute values (for variable products)
        if ($validated['type'] === 'variable') {
            $keepVariantIds = [];
            $allAttributeValueIds = [];

            foreach ($request->input('variants', []) as $varInput) {
                $variant = $product->variants()->where('sku', $varInput['sku'])->first();
                if (! $variant) {
                    $variant = new ProductVariant;
                }
                $variant->fill([
                    'product_id' => $product->id,
                    'sku' => $varInput['sku'],
                    'price' => $varInput['price'],
                    'sale_price' => $varInput['sale_price'] ?? null,
                    'cost_price' => $varInput['cost_price'] ?? null,
                    'manage_stock' => $varInput['manage_stock'] ?? true,
                    'stock_quantity' => $varInput['stock_quantity'] ?? 0,
                    'image_id' => $varInput['image_id'] ?? null,
                ]);
                $variant->save();
                $variant->attributeValues()->sync($varInput['attribute_value_ids']);
                $keepVariantIds[] = $variant->id;

                $allAttributeValueIds = array_merge($allAttributeValueIds, $varInput['attribute_value_ids']);
            }

            // Sync the product's overall attribute value pool for filtering
            $product->attributeValues()->sync(array_unique($allAttributeValueIds));

            // Delete variants not in current request
            $product->variants()->whereNotIn('id', $keepVariantIds)->delete();
        } else {
            $product->variants()->delete();
            $product->attributeValues()->detach();
        }

        // 4. Sync downloads (for digital products)
        if ($validated['type'] === 'digital') {
            $keepDownloadIds = [];
            foreach ($request->input('downloads', []) as $dlInput) {
                $download = $product->downloads()->where('media_id', $dlInput['media_id'])->first();
                if (! $download) {
                    $download = new ProductDownload;
                }
                $download->fill([
                    'product_id' => $product->id,
                    'media_id' => $dlInput['media_id'],
                    'name' => $dlInput['name'],
                    'download_limit' => $dlInput['download_limit'] ?? null,
                    'expiry_days' => $dlInput['expiry_days'] ?? null,
                ]);
                $download->save();
                $keepDownloadIds[] = $download->id;
            }
            $product->downloads()->whereNotIn('id', $keepDownloadIds)->delete();
        } else {
            $product->downloads()->delete();
        }

        // 5. Force update of denormalized counts
        Product::recalculateCountsForBrand($product->brand_id);
        if ($product->wasChanged('brand_id') && $product->getOriginal('brand_id')) {
            Product::recalculateCountsForBrand($product->getOriginal('brand_id'));
        }

        // Recalculate counts for both old and new category associations
        $allCategoryIds = array_unique(array_merge($oldCategoryIds, array_column($categoriesInput, 'id')));
        foreach ($allCategoryIds as $categoryId) {
            Product::recalculateCountsForCategory($categoryId);
        }
    }
}
