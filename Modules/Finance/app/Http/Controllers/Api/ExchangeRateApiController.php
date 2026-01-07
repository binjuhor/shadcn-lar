<?php

namespace Modules\Finance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Finance\Http\Resources\CurrencyResource;
use Modules\Finance\Http\Resources\ExchangeRateResource;
use Modules\Finance\Models\Currency;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Services\ExchangeRateService;

class ExchangeRateApiController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ExchangeRate::with(['baseCurrencyInfo', 'targetCurrencyInfo']);

        if ($request->has('base')) {
            $query->where('base_currency', $request->base);
        }

        if ($request->has('target')) {
            $query->where('target_currency', $request->target);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $rates = $query->latest('rate_date')
            ->take($request->integer('limit', 100))
            ->get();

        return ExchangeRateResource::collection($rates);
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->load(['baseCurrencyInfo', 'targetCurrencyInfo']);

        return response()->json([
            'data' => new ExchangeRateResource($exchangeRate),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'target_currency' => ['required', 'string', 'size:3', 'exists:currencies,code', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'min:0'],
            'bid_rate' => ['nullable', 'numeric', 'min:0'],
            'ask_rate' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'rate_date' => ['nullable', 'date'],
        ]);

        $exchangeRate = ExchangeRate::create([
            'base_currency' => $validated['base_currency'],
            'target_currency' => $validated['target_currency'],
            'rate' => $validated['rate'],
            'bid_rate' => $validated['bid_rate'] ?? null,
            'ask_rate' => $validated['ask_rate'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'rate_date' => $validated['rate_date'] ?? now(),
        ]);

        $exchangeRate->load(['baseCurrencyInfo', 'targetCurrencyInfo']);

        return response()->json([
            'message' => 'Exchange rate created successfully',
            'data' => new ExchangeRateResource($exchangeRate),
        ], 201);
    }

    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        return response()->json([
            'message' => 'Exchange rate deleted successfully',
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $request->validate([
            'base' => ['required', 'string', 'size:3'],
            'target' => ['required', 'string', 'size:3'],
            'source' => ['nullable', 'string'],
        ]);

        $exchangeRate = ExchangeRate::getLatestRate(
            $request->base,
            $request->target,
            $request->source
        );

        if (! $exchangeRate) {
            return response()->json([
                'message' => 'Exchange rate not found',
            ], 404);
        }

        $exchangeRate->load(['baseCurrencyInfo', 'targetCurrencyInfo']);

        return response()->json([
            'data' => new ExchangeRateResource($exchangeRate),
        ]);
    }

    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'source' => ['nullable', 'string'],
        ]);

        try {
            $convertedAmount = $this->exchangeRateService->convert(
                $validated['amount'],
                $validated['from'],
                $validated['to'],
                $validated['source'] ?? null
            );

            $rate = $this->exchangeRateService->getRate(
                $validated['from'],
                $validated['to'],
                $validated['source'] ?? null
            );

            return response()->json([
                'data' => [
                    'original_amount' => (float) $validated['amount'],
                    'converted_amount' => $convertedAmount,
                    'from' => $validated['from'],
                    'to' => $validated['to'],
                    'rate' => $rate,
                    'source' => $validated['source'] ?? 'best_available',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function fetchRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:exchangerate_api,open_exchange_rates,vietcombank,payoneer'],
        ]);

        try {
            $count = $this->exchangeRateService->updateRates($validated['provider']);

            return response()->json([
                'message' => "Successfully fetched {$count} exchange rates from {$validated['provider']}",
                'data' => [
                    'provider' => $validated['provider'],
                    'rates_updated' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function currencies(): AnonymousResourceCollection
    {
        $currencies = Currency::orderBy('code')->get();

        return CurrencyResource::collection($currencies);
    }

    public function providers(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 'exchangerate_api', 'name' => 'ExchangeRate-API'],
                ['id' => 'open_exchange_rates', 'name' => 'Open Exchange Rates'],
                ['id' => 'vietcombank', 'name' => 'Vietcombank'],
                ['id' => 'payoneer', 'name' => 'Payoneer'],
            ],
        ]);
    }
}
