<?php

namespace Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Settings\Http\Requests\UpdateAccountRequest;
use Modules\Settings\Http\Requests\UpdateAppearanceRequest;
use Modules\Settings\Http\Requests\UpdateDisplayRequest;
use Modules\Settings\Http\Requests\UpdateNotificationsRequest;
use Modules\Settings\Http\Requests\UpdateProfileRequest;

class SettingsController extends Controller
{
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
}
