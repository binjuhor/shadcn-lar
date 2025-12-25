<?php

namespace Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when(
                $request->routeIs('dashboard.posts.show', 'dashboard.posts.edit'),
                $this->content
            ),
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'views_count' => $this->views_count,
            'reading_time' => $this->reading_time,
            'featured_image_url' => $this->featured_image_url,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            'category' => $this->whenLoaded('category', fn () => CategoryResource::make($this->category)->resolve()),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve()),
            'user' => $this->whenLoaded('user', fn () => UserResource::make($this->user)->resolve()),
        ];
    }
}
