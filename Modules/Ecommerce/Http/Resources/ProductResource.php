<?php

namespace Modules\Ecommerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->when(
                $request->routeIs('dashboard.ecommerce.products.show', 'dashboard.ecommerce.products.edit'),
                $this->content
            ),
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'cost' => $this->cost ? (float) $this->cost : null,
            'effective_price' => (float) $this->effective_price,
            'is_on_sale' => $this->is_on_sale,
            'discount_percentage' => $this->discount_percentage,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_low_stock' => $this->is_low_stock,
            'is_out_of_stock' => $this->is_out_of_stock,
            'track_inventory' => $this->track_inventory,
            'status' => $this->status,
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->featured_image ? asset('storage/' . $this->featured_image) : null,
            'images' => $this->images,
            'is_featured' => $this->is_featured,
            'views_count' => $this->views_count,
            'sales_count' => $this->sales_count,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'category' => ProductCategoryResource::make($this->whenLoaded('category')),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
            'user' => new \Modules\Blog\Http\Resources\UserResource($this->whenLoaded('user')),
        ];
    }
}