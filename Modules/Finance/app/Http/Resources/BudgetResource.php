<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'period_type' => $this->period_type,
            'allocated_amount' => (float) $this->allocated_amount,
            'spent_amount' => (float) $this->spent_amount,
            'remaining_amount' => (float) ($this->allocated_amount - $this->spent_amount),
            'spent_percent' => $this->getSpentPercent(),
            'is_over_budget' => $this->isOverBudget(),
            'currency_code' => $this->currency_code,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
            'rollover' => $this->rollover,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'category_id' => $this->category_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
