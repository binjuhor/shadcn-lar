<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'contribution_date' => $this->contribution_date?->toDateString(),
            'notes' => $this->notes,
            'type' => $this->type,
            'is_linked' => $this->isLinked(),
            'is_withdrawal' => $this->isWithdrawal(),
            'transaction' => $this->whenLoaded('transaction', fn () => new TransactionResource($this->transaction)),
            'transaction_id' => $this->transaction_id,
            'savings_goal_id' => $this->savings_goal_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
