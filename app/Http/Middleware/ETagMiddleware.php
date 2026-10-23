<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isOk() || ! $request->isMethod('GET')) {
            return $response;
        }

        $etag = '"'.md5($response->getContent()).'"';
        $response->setEtag($etag);

        // isNotModified is on Response (Symfony), not Request
        if ($response->isNotModified($request)) {
            return $response->setNotModified();
        }

        return $response;
    }
}
