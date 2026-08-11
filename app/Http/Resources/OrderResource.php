<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => number_format($this->subtotal, 2, '.', ''),
            'discount' => number_format($this->discount, 2, '.', ''),
            'shipping_cost' => number_format($this->shipping_cost, 2, '.', ''),
            'tax_cost' => number_format($this->tax_cost, 2, '.', ''),
            'grand_total' => number_format($this->grand_total, 2, '.', ''),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'shipping_address' => $this->shipping_address,
            'division' => $this->division,
            'district' => $this->district,
            'upazila' => $this->upazila,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'histories' => $this->relationLoaded('histories') ? $this->histories->map(fn ($hist) => [
                'status' => $hist->status,
                'note' => $hist->note,
                'created_at' => $hist->created_at ? $hist->created_at->toIso8601String() : null,
            ]) : [],
        ];
    }
}
