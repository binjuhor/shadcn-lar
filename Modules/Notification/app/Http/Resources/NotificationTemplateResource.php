<?php

namespace Modules\Notification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subject' => $this->subject,
            'body' => $this->when(
                $request->routeIs('dashboard.notifications.templates.show', 'dashboard.notifications.templates.edit'),
                $this->body
            ),
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'channels' => $this->channels,
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
