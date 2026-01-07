<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->transaction_type,
            'amount' => (float) $this->amount,
            'currency_code' => $this->currency_code,
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'is_reconciled' => $this->reconciled_at !== null,
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'account' => $this->whenLoaded('account', fn () => new AccountResource($this->account)),
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'transfer_account' => $this->whenLoaded('transferAccount', fn () => new AccountResource($this->transferAccount)),
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'transfer_account_id' => $this->transfer_account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
