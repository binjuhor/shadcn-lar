<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_currency' => $this->base_currency,
            'target_currency' => $this->target_currency,
            'rate' => (float) $this->rate,
            'bid_rate' => $this->bid_rate ? (float) $this->bid_rate : null,
            'ask_rate' => $this->ask_rate ? (float) $this->ask_rate : null,
            'source' => $this->source,
            'rate_date' => $this->rate_date?->toIso8601String(),
            'base_currency_info' => $this->whenLoaded('baseCurrencyInfo'),
            'target_currency_info' => $this->whenLoaded('targetCurrencyInfo'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
