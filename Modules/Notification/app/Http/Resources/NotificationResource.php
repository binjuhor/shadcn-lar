<?php

namespace Modules\Notification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'icon' => $this->icon,
            'action_url' => $this->action_url,
            'action_label' => $this->action_label,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'time_ago' => $this->created_at->diffForHumans(),
        ];
    }
}
