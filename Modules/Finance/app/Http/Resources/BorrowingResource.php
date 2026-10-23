<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'lender_name' => $this->lender_name,
            'amount' => (float) $this->amount,
            'amount_repaid' => (float) $this->amount_repaid,
            'remaining_amount' => (float) $this->remaining_amount,
            'progress_percent' => (float) $this->progress_percent,
            'currency_code' => $this->currency_code,
            'borrowed_date' => $this->borrowed_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'message' => $this->message,
            'status' => $this->status,
            'repayments' => BorrowingRepaymentResource::collection($this->whenLoaded('repayments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
