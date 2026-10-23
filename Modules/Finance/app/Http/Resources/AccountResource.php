<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'currency_code' => $this->currency_code,
            'account_number' => $this->account_number,
            'institution_name' => $this->institution_name,
            'initial_balance' => (float) $this->initial_balance,
            'current_balance' => (float) $this->current_balance,
            'description' => $this->description,
            'color' => $this->color,
            'icon_url' => $this->icon_url,
            'is_active' => $this->is_active,
            'is_default_payment' => $this->is_default_payment,
            'exclude_from_total' => $this->exclude_from_total,
            'currency' => $this->whenLoaded('currency', fn () => [
                'code' => $this->currency->code,
                'name' => $this->currency->name,
                'symbol' => $this->currency->symbol,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
