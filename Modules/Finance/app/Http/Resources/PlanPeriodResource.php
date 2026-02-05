<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'planned_income' => $this->planned_income,
            'planned_expense' => $this->planned_expense,
            'net_planned' => $this->net_planned,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => PlanItemResource::collection($this->items)),
        ];
    }
}
