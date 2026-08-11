<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
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
            'name' => $this->name,
            'is_active' => $this->is_active,
            'locations' => $this->relationLoaded('locations') ? $this->locations->map(fn ($loc) => [
                'id' => $loc->id,
                'country_code' => $loc->country_code,
                'division' => $loc->division,
                'district' => $loc->district,
                'upazila' => $loc->upazila,
            ]) : [],
            'methods' => $this->relationLoaded('methods') ? $this->methods->map(fn ($method) => [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type,
                'cost' => number_format($method->cost, 2, '.', ''),
                'min_order_amount' => number_format($method->min_order_amount, 2, '.', ''),
                'is_active' => $method->is_active,
                'weight_rates' => $method->relationLoaded('weightRates') ? $method->weightRates->map(fn ($rate) => [
                    'id' => $rate->id,
                    'max_weight' => number_format($rate->max_weight, 2, '.', ''),
                    'cost' => number_format($rate->cost, 2, '.', ''),
                ]) : [],
            ]) : [],
        ];
    }
}
