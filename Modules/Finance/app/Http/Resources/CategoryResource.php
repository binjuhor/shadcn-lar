<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_key' => $this->name_key,
            'type' => $this->type,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'is_passive' => $this->is_passive,
            'is_system' => $this->user_id === null,
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => new CategoryResource($this->parent)),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
