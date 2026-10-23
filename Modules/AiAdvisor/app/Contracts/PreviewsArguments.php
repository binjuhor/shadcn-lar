<?php

namespace Modules\AiAdvisor\Contracts;

use Modules\AiAdvisor\DTOs\ToolContext;

/**
 * Write tools that can describe their arguments in human terms (names instead
 * of IDs) for the confirmation card. Only the acting user's records resolve.
 */
interface PreviewsArguments
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function previewArguments(ToolContext $context, array $arguments): array;
}
