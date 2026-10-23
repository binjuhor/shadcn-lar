<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\{Inertia, Response};
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Currency;
use Modules\Settings\Http\Requests\{
    UpdateAccountRequest,
    UpdateAiSettingsRequest,
    UpdateAppearanceRequest,
    UpdateDisplayRequest,
    UpdateFinanceSettingsRequest,
    UpdateInvoiceSettingsRequest,
    UpdateNotificationsRequest,
    UpdateProfileRequest
};
use Modules\Settings\Services\AiCredentialResolver;
use Nwidart\Modules\Facades\Module;

class SettingsController extends Controller
{
    public function ai(AiCredentialResolver $credentials): Response
    {
        return Inertia::render('settings/ai/index', [
            'providers' => $credentials->statuses(auth()->user()),
            'selectedProvider' => $credentials->provider(auth()->user()),
            'ollama' => [
                'baseUrl' => auth()->user()->ai_settings['ollama_base_url'] ?? '',
                'model' => auth()->user()->ai_settings['ollama_model'] ?? '',
            ],
        ]);
    }

    public function updateAi(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $settings = $request->user()->ai_settings ?? [];
        $validated = $request->validated();

        if (isset($validated['provider'])) {
            $settings['provider'] = $validated['provider'];
        }

        if (($validated['provider'] ?? null) === 'ollama') {
            foreach (['ollama_base_url', 'ollama_model'] as $key) {
                $value = $validated[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    $settings[$key] = $value;
                } else {
                    unset($settings[$key]);
                }
            }
        }

        foreach ($validated['keys'] ?? [] as $provider => $key) {
            if (in_array($provider, AiCredentialResolver::PROVIDERS, true) && is_string($key) && $key !== '') {
                $settings[$provider] = $key;
            }
        }

        foreach ($validated['clear'] ?? [] as $provider) {
            unset($settings[$provider]);
        }

        $request->user()->update([
            'ai_settings' => $settings === [] ? null : $settings,
        ]);

        return Redirect::back()->with('success', 'AI keys updated successfully.');
    }

    public function profile(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/profile/index', [
            'settings' => [
                'username' => $user->username,
                'email' => $user->email,
                'bio' => $user->bio,
                'urls' => $user->urls ?? [],
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return Redirect::back()->with('success', 'Profile updated successfully.');
    }

    public function account(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/account/index', [
            'settings' => [
                'name' => $user->name,
                'dob' => $user->dob?->format('Y-m-d'),
                'language' => $user->language ?? 'en',
            ],
        ]);
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return Redirect::back()->with('success', 'Account updated successfully.');
    }

    public function appearance(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/appearance/index', [
            'settings' => $user->appearance_settings ?? [
                'theme' => 'light',
                'font' => 'inter',
            ],
        ]);
    }

    public function updateAppearance(UpdateAppearanceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'appearance_settings' => $request->validated(),
        ]);

        return Redirect::back()->with('success', 'Appearance updated successfully.');
    }

    public function notifications(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/notifications/index', [
            'settings' => $user->notification_settings ?? [
                'type' => 'all',
                'mobile' => false,
                'communication_emails' => false,
                'social_emails' => true,
                'marketing_emails' => false,
                'security_emails' => true,
            ],
        ]);
    }

    public function updateNotifications(UpdateNotificationsRequest $request): RedirectResponse
    {
        $request->user()->update([
            'notification_settings' => $request->validated(),
        ]);

        return Redirect::back()->with('success', 'Notification preferences updated successfully.');
    }

    public function display(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/display/index', [
            'settings' => $user->display_settings ?? [
                'items' => ['recents', 'home'],
            ],
        ]);
    }

    public function updateDisplay(UpdateDisplayRequest $request): RedirectResponse
    {
        $request->user()->update([
            'display_settings' => $request->validated(),
        ]);

        return Redirect::back()->with('success', 'Display settings updated successfully.');
    }

    public function finance(): Response
    {
        if (! Module::has('Finance') || ! Module::isEnabled('Finance')) {
            abort(404);
        }

        $user = auth()->user();
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        // Get user's accounts for default smart input account selection
        $accounts = [];
        if (class_exists(Account::class)) {
            $accounts = Account::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'account_type', 'currency_code']);
        }

        $defaultSettings = [
            'default_currency' => 'VND',
            'default_exchange_rate_source' => null,
            'fiscal_year_start' => 1,
            'number_format' => 'thousand_comma',
            'default_smart_input_account_id' => null,
        ];

        return Inertia::render('settings/finance/index', [
            'settings' => array_merge($defaultSettings, $user->finance_settings ?? []),
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function updateFinance(UpdateFinanceSettingsRequest $request): RedirectResponse
    {
        if (! Module::has('Finance') || ! Module::isEnabled('Finance')) {
            abort(404);
        }

        $request->user()->update([
            'finance_settings' => $request->validated(),
        ]);

        return Redirect::back()->with('success', 'Finance settings updated successfully.');
    }

    public function invoice(): Response
    {
        if (! Module::has('Invoice') || ! Module::isEnabled('Invoice')) {
            abort(404);
        }

        $user = auth()->user();

        // Get currencies from Finance module if available, otherwise use a basic list
        $currencies = [];
        if (Module::has('Finance') && Module::isEnabled('Finance')) {
            $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);
        } else {
            $currencies = collect([
                ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫'],
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ]);
        }

        $defaultSettings = [
            'default_currency' => 'VND',
            'default_tax_rate' => 10,
            'default_payment_terms' => 30,
            'company_name' => null,
            'company_address' => null,
            'company_email' => null,
            'company_phone' => null,
        ];

        return Inertia::render('settings/invoice/index', [
            'settings' => array_merge($defaultSettings, $user->invoice_settings ?? []),
            'currencies' => $currencies,
        ]);
    }

    public function updateInvoice(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        if (! Module::has('Invoice') || ! Module::isEnabled('Invoice')) {
            abort(404);
        }

        $request->user()->update([
            'invoice_settings' => $request->validated(),
        ]);

        return Redirect::back()->with('success', 'Invoice settings updated successfully.');
    }
}
