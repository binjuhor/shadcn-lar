<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'target_amount' => (float) $this->target_amount,
            'current_amount' => (float) $this->current_amount,
            'remaining_amount' => (float) $this->remaining_amount,
            'progress_percent' => $this->progress_percent,
            'currency_code' => $this->currency_code,
            'target_date' => $this->target_date?->toDateString(),
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_completed' => $this->isCompleted(),
            'has_reached_target' => $this->hasReachedTarget(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'target_account' => $this->whenLoaded('targetAccount', fn () => new AccountResource($this->targetAccount)),
            'target_account_id' => $this->target_account_id,
            'contributions' => SavingsContributionResource::collection($this->whenLoaded('contributions')),
            'contributions_count' => $this->whenCounted('contributions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
