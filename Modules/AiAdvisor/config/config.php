<?php

return [
    'enabled' => (bool) env('AI_ADVISOR_ENABLED', false),
    'mutations_enabled' => (bool) env('AI_ADVISOR_MUTATIONS_ENABLED', false),
    'max_tool_rounds' => 5,
    'max_result_items' => 50,
    'confirmation_ttl_seconds' => 600,
];
