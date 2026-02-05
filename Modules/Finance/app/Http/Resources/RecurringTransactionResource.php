<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'transaction_type' => $this->transaction_type,
            'amount' => (int) $this->amount,
            'currency_code' => $this->currency_code,
            'frequency' => $this->frequency,
            'day_of_week' => $this->day_of_week,
            'day_of_month' => $this->day_of_month,
            'month_of_year' => $this->month_of_year,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_run_date' => $this->next_run_date?->toDateString(),
            'last_run_date' => $this->last_run_date?->toDateString(),
            'is_active' => $this->is_active,
            'auto_create' => $this->auto_create,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'account' => $this->whenLoaded('account', fn () => new AccountResource($this->account)),
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
        ];
    }
}
