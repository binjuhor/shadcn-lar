<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;
use Nwidart\Modules\Facades\Module;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->toArray()
                    : [],
                'roles' => $user
                    ? $user->getRoleNames()->toArray()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'locale' => app()->getLocale(),
            'translations' => fn () => $this->getTranslations(),
            'enabledModules' => fn () => collect(Module::allEnabled())->keys()->toArray(),
            'sidebarSettings' => fn () => $user?->sidebar_settings ?? [],
        ];
    }

    protected function getTranslations(): array
    {
        $locale = app()->getLocale();
        $path = lang_path("{$locale}.json");

        if (! File::exists($path)) {
            $path = lang_path('en.json');
        }

        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }
}
