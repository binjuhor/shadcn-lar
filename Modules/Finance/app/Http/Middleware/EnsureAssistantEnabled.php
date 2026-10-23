<?php

namespace Modules\Finance\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Finance\Services\AssistantService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The unified assistant runs on the AI agent, so it is hidden while AI_ADVISOR_ENABLED is off.
 */
class EnsureAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AssistantService::enabled(), 404);

        return $next($request);
    }
}
