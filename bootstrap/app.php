<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $shouldRenderError = ! app()->environment(['local', 'testing'])
              && in_array($response->getStatusCode(), [500, 503, 404, 403, 401]);

            if ($shouldRenderError) {
                $errorComponents = [
                    '401' => 'errors/unauthorized-error',
                    '403' => 'errors/forbidden',
                    '404' => 'errors/not-found-error',
                    '500' => 'errors/general-error',
                    '503' => 'errors/maintenance-error',
                ];
                $component = $errorComponents[$response->getStatusCode()] ?? 'errors/general-error';

                return Inertia::render($component, ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            } elseif ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();
