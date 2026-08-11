<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $systemRoles = ['super-admin', 'administrator', 'manager', 'staff'];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'is_system' => in_array($this->name, $systemRoles),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
