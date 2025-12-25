<?php

namespace Modules\Permission\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn () => RoleResource::collection($this->roles)->resolve()),
            'role_names' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->roles->pluck('name')->toArray()
            ),
            'permissions' => $this->when(
                $this->relationLoaded('roles') && $this->roles->first()?->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->pluck('name')->unique()->values()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
