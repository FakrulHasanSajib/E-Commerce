<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPublicController extends Controller
{
    /**
     * Display a listing of storefront active products.
     */
    public function index(Request $request)
    {
        $brandId = $request->input('brand_id');
        $categoryId = $request->input('category_id');
        $priceMin = $request->input('price_min');
        $priceMax = $request->input('price_max');
        $sort = $request->input('sort', 'newest');
        $search = $request->input('search');

        // 1. Perform search if a query is present
        if ($request->filled('search')) {
            $scoutQuery = Product::search($search);

            $scoutQuery->query(function ($dbQuery) use ($brandId, $categoryId, $priceMin, $priceMax) {
                $dbQuery->with([
                    'brand.logo',
                    'categories.image',
                    'media' => function ($q) {
                        $q->wherePivot('collection_name', 'thumbnail');
                    },
                ])
                    ->where('is_active', true)
                    ->where('status', 'published')
                    ->where(function ($q) {
                        $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                    });

                if ($brandId) {
                    $dbQuery->where('brand_id', $brandId);
                }

                if ($categoryId) {
                    $dbQuery->whereHas('categories', function ($q) use ($categoryId) {
                        $q->where('categories.id', $categoryId);
                    });
                }

                if ($priceMin || $priceMax) {
                    $min = $priceMin ?? 0;
                    $max = $priceMax ?? 99999999;
                    $dbQuery->where(function ($q) use ($min, $max) {
                        $q->whereBetween('price', [$min, $max])
                            ->orWhereHas('variants', function ($v) use ($min, $max) {
                                $v->whereBetween('price', [$min, $max]);
                            });
                    });
                }
            });

            if ($sort === 'price_asc') {
                $scoutQuery->orderBy('price', 'asc');
            } elseif ($sort === 'price_desc') {
                $scoutQuery->orderBy('price', 'desc');
            } else {
                $scoutQuery->orderBy('id', 'desc');
            }

            $products = $scoutQuery->paginate(20);
        } else {
            // 2. Otherwise browse with standard query
            $dbQuery = Product::with([
                'brand.logo',
                'categories.image',
                'media' => function ($q) {
                    $q->wherePivot('collection_name', 'thumbnail');
                },
            ])
                ->where('is_active', true)
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                });

            if ($brandId) {
                $dbQuery->where('brand_id', $brandId);
            }

            if ($categoryId) {
                $dbQuery->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }

            if ($priceMin || $priceMax) {
                $min = $priceMin ?? 0;
                $max = $priceMax ?? 99999999;
                $dbQuery->where(function ($q) use ($min, $max) {
                    $q->whereBetween('price', [$min, $max])
                        ->orWhereHas('variants', function ($v) use ($min, $max) {
                            $v->whereBetween('price', [$min, $max]);
                        });
                });
            }

            if ($sort === 'price_asc') {
                $dbQuery->orderBy('price', 'asc');
            } elseif ($sort === 'price_desc') {
                $dbQuery->orderBy('price', 'desc');
            } else {
                $dbQuery->orderBy('id', 'desc');
            }

            $products = $dbQuery->paginate(20);
        }

        $products->getCollection()->load([
            'brand.logo',
            'categories.image',
            'media' => function ($q) {
                $q->wherePivot('collection_name', 'thumbnail');
            },
        ]);

        $facets = $this->getFacets($request);

        return ProductResource::collection($products)->additional([
            'meta' => [
                'facets' => $facets,
            ],
        ]);
    }

    /**
     * Display the storefront detail page of an active product.
     */
    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->with([
                'brand.logo',
                'categories.image',
                'media',
                'variants.attributeValues.attribute',
                'attributeValues.attribute',
            ])
            ->firstOrFail();

        return new ProductResource($product);
    }

    /**
     * Calculate facets for the current filter parameters.
     */
    private function getFacets(Request $request): array
    {
        $driver = config('scout.driver');
        $search = $request->input('search', '*');

        if ($driver === 'meilisearch' && class_exists('MeiliSearch\Client')) {
            try {
                $options = [
                    'facets' => ['brand_name', 'category_names', 'attribute_values'],
                ];

                $filters = ['is_active = true', "status = 'published'"];

                if ($request->filled('brand_id')) {
                    if ($brand = Brand::find($request->input('brand_id'))) {
                        $filters[] = "brand_name = '".addslashes($brand->name)."'";
                    }
                }

                if ($request->filled('category_id')) {
                    if ($category = Category::find($request->input('category_id'))) {
                        $filters[] = "category_names = '".addslashes($category->name)."'";
                    }
                }

                if ($request->filled('price_min') || $request->filled('price_max')) {
                    $min = $request->input('price_min', 0);
                    $max = $request->input('price_max', 99999999);
                    $filters[] = "price >= {$min} AND price <= {$max}";
                }

                if (! empty($filters)) {
                    $options['filter'] = implode(' AND ', $filters);
                }

                $raw = Product::search($search)->options($options)->raw();
                $facetDistribution = $raw['facetDistribution'] ?? [];

                $attributes = [];
                $rawAttributes = $facetDistribution['attribute_values'] ?? [];
                foreach ($rawAttributes as $key => $count) {
                    if (str_contains($key, ':')) {
                        [$attrSlug, $valSlug] = explode(':', $key, 2);
                        $attributes[$attrSlug][$valSlug] = $count;
                    }
                }

                return [
                    'brands' => $facetDistribution['brand_name'] ?? [],
                    'categories' => $facetDistribution['category_names'] ?? [],
                    'attributes' => $attributes,
                ];
            } catch (\Throwable $e) {
                // Fallback to database if Meilisearch fails
            }
        }

        // Database aggregation fallback
        $baseQuery = Product::where('products.is_active', true)
            ->where('products.status', 'published')
            ->where(function ($q) {
                $q->whereNull('products.published_at')->orWhere('products.published_at', '<=', now());
            });

        if ($request->filled('search')) {
            $searchQuery = $request->input('search');
            $baseQuery->where('products.name', 'like', "%{$searchQuery}%");
        }

        if ($request->filled('brand_id')) {
            $baseQuery->where('products.brand_id', $request->input('brand_id'));
        }

        if ($request->filled('category_id')) {
            $baseQuery->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->input('category_id'));
            });
        }

        if ($request->filled('price_min') || $request->filled('price_max')) {
            $min = $request->input('price_min', 0);
            $max = $request->input('price_max', 99999999);
            $baseQuery->where(function ($q) use ($min, $max) {
                $q->whereBetween('products.price', [$min, $max])
                    ->orWhereHas('variants', fn ($v) => $v->whereBetween('price', [$min, $max]));
            });
        }

        $brandCounts = (clone $baseQuery)
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->groupBy('brands.name')
            ->select('brands.name', DB::raw('count(*) as count'))
            ->pluck('count', 'brands.name')
            ->toArray();

        $categoryCounts = (clone $baseQuery)
            ->join('category_product', 'products.id', '=', 'category_product.product_id')
            ->join('categories', 'category_product.category_id', '=', 'categories.id')
            ->groupBy('categories.name')
            ->select('categories.name', DB::raw('count(*) as count'))
            ->pluck('count', 'categories.name')
            ->toArray();

        $attributeCountsRaw = (clone $baseQuery)
            ->join('product_attribute_value', 'products.id', '=', 'product_attribute_value.product_id')
            ->join('attribute_values', 'product_attribute_value.attribute_value_id', '=', 'attribute_values.id')
            ->join('attributes', 'attribute_values.attribute_id', '=', 'attributes.id')
            ->groupBy('attributes.slug', 'attribute_values.slug')
            ->select('attributes.slug as attr_slug', 'attribute_values.slug as val_slug', DB::raw('count(*) as count'))
            ->get();

        $attributes = [];
        foreach ($attributeCountsRaw as $row) {
            $attributes[$row->attr_slug][$row->val_slug] = $row->count;
        }

        return [
            'brands' => $brandCounts,
            'categories' => $categoryCounts,
            'attributes' => $attributes,
        ];
    }
}
