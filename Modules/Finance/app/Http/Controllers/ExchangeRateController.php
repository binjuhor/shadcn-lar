<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Redirect;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\{Currency, ExchangeRate};
use Modules\Finance\Services\ExchangeRateService;

class ExchangeRateController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {}

    public function index(Request $request): Response
    {
        $query = ExchangeRate::with(['baseCurrencyInfo', 'targetCurrencyInfo'])
            ->latest('rate_date');

        if ($request->filled('base_currency')) {
            $query->where('base_currency', $request->base_currency);
        }

        if ($request->filled('target_currency')) {
            $query->where('target_currency', $request->target_currency);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        $rates = $query->paginate(50)->withQueryString();
        $currencies = Currency::orderBy('code')->get();
        $accountCurrencies = $this->exchangeRateService->getAccountCurrencies(auth()->id());

        $latestRates = ExchangeRate::selectRaw('base_currency, target_currency, MAX(id) as latest_id')
            ->groupBy('base_currency', 'target_currency')
            ->pluck('latest_id');

        $currentRates = ExchangeRate::whereIn('id', $latestRates)
            ->with(['baseCurrencyInfo', 'targetCurrencyInfo'])
            ->orderBy('base_currency')
            ->orderBy('target_currency')
            ->get();

        return Inertia::render('Finance::exchange-rates/index', [
            'rates' => $rates,
            'currentRates' => $currentRates,
            'currencies' => $currencies,
            'accountCurrencies' => $accountCurrencies,
            'filters' => $request->only(['base_currency', 'target_currency', 'source']),
            'providers' => ['manual', 'exchangerate_api', 'open_exchange_rates', 'vietcombank', 'payoneer'],
        ]);
    }

    public function create(): Response
    {
        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::exchange-rates/create', [
            'currencies' => $currencies,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'target_currency' => ['required', 'string', 'size:3', 'exists:currencies,code', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'bid_rate' => ['nullable', 'numeric', 'gt:0'],
            'ask_rate' => ['nullable', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date'],
        ]);

        ExchangeRate::create([
            'base_currency' => $validated['base_currency'],
            'target_currency' => $validated['target_currency'],
            'rate' => $validated['rate'],
            'bid_rate' => $validated['bid_rate'] ?? null,
            'ask_rate' => $validated['ask_rate'] ?? null,
            'source' => 'manual',
            'rate_date' => $validated['rate_date'],
        ]);

        return Redirect::route('dashboard.finance.exchange-rates.index')
            ->with('success', 'Exchange rate added successfully');
    }

    public function edit(ExchangeRate $exchangeRate): Response
    {
        $currencies = Currency::orderBy('code')->get();

        return Inertia::render('Finance::exchange-rates/edit', [
            'exchangeRate' => $exchangeRate,
            'currencies' => $currencies,
        ]);
    }

    public function update(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $validated = $request->validate([
            'base_currency' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'target_currency' => ['sometimes', 'string', 'size:3', 'exists:currencies,code'],
            'rate' => ['sometimes', 'numeric', 'gt:0'],
            'bid_rate' => ['nullable', 'numeric', 'gt:0'],
            'ask_rate' => ['nullable', 'numeric', 'gt:0'],
            'rate_date' => ['sometimes', 'date'],
        ]);

        $exchangeRate->update($validated);

        return Redirect::back()->with('success', 'Exchange rate updated successfully');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->delete();

        return Redirect::route('dashboard.finance.exchange-rates.index')
            ->with('success', 'Exchange rate deleted successfully');
    }

    public function fetchRates(Request $request, ExchangeRateService $service): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:exchangerate_api,open_exchange_rates,vietcombank,payoneer,all'],
            'account_only' => ['nullable', 'boolean'],
        ]);

        $accountOnly = $validated['account_only'] ?? false;
        $userId = $accountOnly ? auth()->id() : null;

        try {
            if ($validated['provider'] === 'all') {
                $providers = ['exchangerate_api', 'open_exchange_rates', 'vietcombank', 'payoneer'];
                $totalCount = 0;

                foreach ($providers as $provider) {
                    $count = $service->updateRates($provider, $accountOnly, $userId);
                    $totalCount += $count;
                }

                $suffix = $accountOnly ? ' (account currencies only)' : '';

                return Redirect::back()->with('success', "Fetched {$totalCount} rates from all providers{$suffix}");
            }

            $count = $service->updateRates($validated['provider'], $accountOnly, $userId);
            $suffix = $accountOnly ? ' (account currencies only)' : '';

            return Redirect::back()->with('success', "Fetched {$count} rates from {$validated['provider']}{$suffix}");
        } catch (\Exception $e) {
            return Redirect::back()->withErrors(['error' => "Failed to fetch rates: {$e->getMessage()}"]);
        }
    }

    public function convert(Request $request, ExchangeRateService $service): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
        ]);

        try {
            $result = $service->convert(
                (float) $validated['amount'],
                $validated['from'],
                $validated['to']
            );

            $rate = $service->getRate($validated['from'], $validated['to']);

            return response()->json([
                'success' => true,
                'result' => $result,
                'rate' => $rate,
                'from' => $validated['from'],
                'to' => $validated['to'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
