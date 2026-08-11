<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'user_id' => $this->user_id,
            'token' => $this->token,
            'subtotal' => number_format($this->getSubtotal(), 2, '.', ''),
            'discount' => number_format($this->getDiscount(), 2, '.', ''),
            'total' => number_format($this->getTotal(), 2, '.', ''),
            'coupon' => $this->coupon_id && $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'type' => $this->coupon->type,
                'value' => number_format($this->coupon->value, 2, '.', ''),
            ] : null,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
