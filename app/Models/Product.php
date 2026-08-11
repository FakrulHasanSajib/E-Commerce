<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

#[Fillable([
    'brand_id',
    'name',
    'slug',
    'description',
    'summary',
    'type',
    'status',
    'published_at',
    'sku',
    'price',
    'sale_price',
    'cost_price',
    'manage_stock',
    'stock_quantity',
    'low_stock_threshold',
    'weight',
    'width',
    'height',
    'length',
    'is_active',
    'average_rating',
    'review_count',
    'reviews_count',
    'has_active_offer',
    'meta_title',
    'meta_description',
    'tax_class_id',
])]
class Product extends Model
{
    use Searchable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'manage_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'weight' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'length' => 'decimal:2',
            'is_active' => 'boolean',
            'average_rating' => 'decimal:2',
            'review_count' => 'integer',
            'reviews_count' => 'integer',
            'has_active_offer' => 'boolean',
            'tax_class_id' => 'integer',
        ];
    }

    /**
     * Get the brand of the product.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get product reviews.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Get polymorphic SEO metadata.
     */
    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    /**
     * Get the tax class of the product.
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * Get the categories linked to the product.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product')
            ->withPivot('is_primary');
    }

    /**
     * Get the variants of the product (for variable products).
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the global attribute values associated with the product.
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_value');
    }

    /**
     * Get the digital downloads.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(ProductDownload::class);
    }

    /**
     * Polymorphic many-to-many relationship using the mediables table.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'mediables', 'mediable_id', 'media_id')
            ->wherePivot('mediable_type', static::class)
            ->withPivot('collection_name', 'order', 'alt_text', 'seo_title', 'seo_description')
            ->orderByPivot('order', 'asc');
    }

    /**
     * Get only the product thumbnail image.
     */
    public function thumbnail(): BelongsToMany
    {
        return $this->media()->wherePivot('collection_name', 'thumbnail');
    }

    /**
     * Get the product image gallery.
     */
    public function gallery(): BelongsToMany
    {
        return $this->media()->wherePivot('collection_name', 'gallery');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            if ($product->isDirty('name') && ! $product->isDirty('slug')) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            } elseif (empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name, $product->id);
            }
        });

        static::saved(function ($product) {
            static::recalculateCountsForBrand($product->brand_id);
            if ($product->wasChanged('brand_id') && $product->getOriginal('brand_id')) {
                static::recalculateCountsForBrand($product->getOriginal('brand_id'));
            }
            foreach ($product->categories as $category) {
                static::recalculateCountsForCategory($category->id);
            }
        });

        static::deleted(function ($product) {
            static::recalculateCountsForBrand($product->brand_id);
            foreach ($product->categories as $category) {
                static::recalculateCountsForCategory($category->id);
            }
        });

        static::restored(function ($product) {
            static::recalculateCountsForBrand($product->brand_id);
            foreach ($product->categories as $category) {
                static::recalculateCountsForCategory($category->id);
            }
        });
    }

    /**
     * Generate a unique slug.
     */
    protected static function generateUniqueSlug(string $name, ?int $id = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Recalculate storefront active/published product count for a brand.
     */
    public static function recalculateCountsForBrand(?int $brandId): void
    {
        if (! $brandId) {
            return;
        }

        $count = static::where('brand_id', $brandId)
            ->where('is_active', true)
            ->where('status', 'published')
            ->count();

        Brand::where('id', $brandId)->update(['products_count' => $count]);
    }

    /**
     * Recalculate storefront active/published product count for a category.
     */
    public static function recalculateCountsForCategory(?int $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        $count = DB::table('category_product')
            ->join('products', 'category_product.product_id', '=', 'products.id')
            ->where('category_product.category_id', $categoryId)
            ->where('products.is_active', true)
            ->where('products.status', 'published')
            ->whereNull('products.deleted_at')
            ->count();

        Category::where('id', $categoryId)->update(['products_count' => $count]);
    }

    public function toSearchableArray(): array
    {
        // Safe load to prevent lazy loading exceptions during indexing
        $this->relationLoaded('brand') ?: $this->load('brand');
        $this->relationLoaded('categories') ?: $this->load('categories');
        $this->relationLoaded('attributeValues') ?: $this->load('attributeValues.attribute');
        $this->relationLoaded('variants') ?: $this->load('variants');

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'summary' => $this->summary,
            'type' => $this->type,
            'status' => $this->status,
            'price' => (float) $this->price,
            'is_active' => (bool) $this->is_active,
            'average_rating' => (float) $this->average_rating,
            'review_count' => (int) $this->review_count,
            'has_active_offer' => (bool) $this->has_active_offer,
            'brand_name' => $this->brand?->name,
            'category_names' => $this->categories->pluck('name')->toArray(),
            'category_slugs' => $this->categories->pluck('slug')->toArray(),
            'attribute_values' => $this->attributeValues->map(function ($value) {
                return $value->attribute ? "{$value->attribute->slug}:{$value->slug}" : $value->slug;
            })->toArray(),
            'variant_skus' => $this->variants->pluck('sku')->toArray(),
        ];
    }
}
