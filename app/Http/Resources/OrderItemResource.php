<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'name' => $this->name,
            'price' => number_format($this->price, 2, '.', ''),
            'quantity' => $this->quantity,
            'total_price' => number_format($this->price * $this->quantity, 2, '.', ''),
            'variant_attributes' => $this->variant_attributes,
        ];
    }
}
