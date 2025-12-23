<?php

namespace Modules\Ecommerce\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'shipping' => (float) $this->shipping,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'customer_notes' => $this->customer_notes,
            'admin_notes' => $this->admin_notes,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'is_paid' => $this->is_paid,
            'is_completed' => $this->is_completed,
            'is_cancelled' => $this->is_cancelled,
            'is_pending' => $this->is_pending,
            'is_processing' => $this->is_processing,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'user' => $this->whenLoaded('user', fn() => (new \Modules\Blog\Http\Resources\UserResource($this->user))->resolve()),
            'items' => $this->whenLoaded('items', fn() => OrderItemResource::collection($this->items)->resolve()),
        ];
    }
}