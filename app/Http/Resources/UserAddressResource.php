<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'address' => $this->address,
            'division' => $this->division,
            'district' => $this->district,
            'upazila' => $this->upazila,
            'country_code' => $this->country_code,
            'is_default' => $this->is_default,
        ];
    }
}
