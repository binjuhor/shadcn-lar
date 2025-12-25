<?php

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'isActive' => $this->is_active,
            'usageCount' => $this->usage_count,
            'postsCount' => $this->when(
                $this->relationLoaded('posts'),
                fn () => $this->posts->count()
            ),
        ];
    }
}
