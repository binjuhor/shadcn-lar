<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_year' => $this->start_year,
            'end_year' => $this->end_year,
            'year_span' => $this->year_span,
            'currency_code' => $this->currency_code,
            'status' => $this->status,
            'total_planned_income' => $this->total_planned_income,
            'total_planned_expense' => $this->total_planned_expense,
            'net_planned' => $this->net_planned,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'periods' => $this->whenLoaded('periods', fn () => PlanPeriodResource::collection($this->periods)),
        ];
    }
}
