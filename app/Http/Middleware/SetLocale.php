<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supportedLocales = ['en', 'vi'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    protected function determineLocale(Request $request): string
    {
        if ($user = $request->user()) {
            $userLocale = $user->language;
            if ($userLocale && in_array($userLocale, $this->supportedLocales)) {
                return $userLocale;
            }
        }

        $sessionLocale = session('locale');
        if ($sessionLocale && in_array($sessionLocale, $this->supportedLocales)) {
            return $sessionLocale;
        }

        return config('app.locale', 'en');
    }
}
