<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if ($this->image) {
            $imageUrl = Storage::disk($this->image->disk)->url(
                $this->image->path.'/'.$this->image->filename
            );
        }

        $options = [];
        if ($this->relationLoaded('attributeValues')) {
            foreach ($this->attributeValues as $val) {
                $options[] = [
                    'attribute_id' => $val->attribute_id,
                    'attribute_name' => $val->attribute ? $val->attribute->name : 'Option',
                    'value_id' => $val->id,
                    'value' => $val->value,
                ];
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'manage_stock' => $this->manage_stock,
            'stock_quantity' => $this->stock_quantity,
            'image_id' => $this->image_id,
            'image_url' => $imageUrl,
            'options' => $options,
        ];
    }
}
