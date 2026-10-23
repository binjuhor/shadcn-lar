<?php

namespace Modules\AiAdvisor\Enums;

enum ToolRiskLevel: string
{
    case Read = 'read';
    case Write = 'write';
}
