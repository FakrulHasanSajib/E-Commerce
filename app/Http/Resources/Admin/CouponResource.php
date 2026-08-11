<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'type' => $this->type,
            'value' => number_format($this->value, 2, '.', ''),
            'min_purchase_amount' => number_format($this->min_purchase_amount, 2, '.', ''),
            'starts_at' => $this->starts_at ? $this->starts_at->toIso8601String() : null,
            'ends_at' => $this->ends_at ? $this->ends_at->toIso8601String() : null,
            'max_uses' => $this->max_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'uses_count' => $this->uses_count,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
