<?php

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'parent' => $this->whenLoaded('parent', fn () => CategoryResource::make($this->parent)->resolve()),
            'children' => $this->whenLoaded('children', fn () => CategoryResource::collection($this->children)->resolve()),
            'postsCount' => $this->when(
                $this->relationLoaded('posts'),
                fn () => $this->posts->count()
            ),
        ];
    }
}
