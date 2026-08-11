<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $thumbnailUrl = null;
        if ($this->relationLoaded('media')) {
            $thumb = $this->media->where('pivot.collection_name', 'thumbnail')->first();
            if ($thumb) {
                $thumbnailUrl = Storage::disk($thumb->disk)->url(
                    $thumb->path.'/'.$thumb->filename
                );
            }
        }

        $galleryUrls = [];
        if ($this->relationLoaded('media')) {
            $galleryItems = $this->media->where('pivot.collection_name', 'gallery');
            foreach ($galleryItems as $item) {
                $galleryUrls[] = [
                    'id' => $item->id,
                    'url' => Storage::disk($item->disk)->url(
                        $item->path.'/'.$item->filename
                    ),
                    'order' => $item->pivot->order,
                    'alt_text' => $item->pivot->alt_text ?? $item->alt_text,
                ];
            }
        }

        $attributes = [];
        if ($this->relationLoaded('attributeValues')) {
            $grouped = $this->attributeValues->groupBy('attribute_id');
            foreach ($grouped as $attributeId => $values) {
                $firstVal = $values->first();
                $attributes[] = [
                    'id' => $attributeId,
                    'name' => $firstVal->attribute ? $firstVal->attribute->name : 'Option',
                    'slug' => $firstVal->attribute ? $firstVal->attribute->slug : 'option',
                    'values' => $values->map(fn ($v) => [
                        'id' => $v->id,
                        'value' => $v->value,
                        'slug' => $v->slug,
                    ])->toArray(),
                ];
            }
        }

        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'summary' => $this->summary,
            'type' => $this->type,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'sku' => $this->sku,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'manage_stock' => $this->manage_stock,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'is_active' => $this->is_active,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'thumbnail_url' => $thumbnailUrl,
            'gallery' => $galleryUrls,

            // Relations
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'attributes' => $attributes,

            'downloads' => $this->when(
                $this->relationLoaded('downloads') && $request->is('api/admin/*'),
                fn () => $this->downloads->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'media_id' => $d->media_id,
                    'download_limit' => $d->download_limit,
                    'expiry_days' => $d->expiry_days,
                ])
            ),
        ];
    }
}
