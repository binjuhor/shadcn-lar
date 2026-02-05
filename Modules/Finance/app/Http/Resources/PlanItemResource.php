<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'planned_amount' => $this->planned_amount,
            'recurrence' => $this->recurrence,
            'category_id' => $this->category_id,
            'notes' => $this->notes,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
        ];
    }
}
