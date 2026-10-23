<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetGoalsTool extends AbstractFinanceTool
{
    protected const NAME = 'getGoals';

    protected const DESCRIPTION = 'List saving goals and progress.';

    protected const METHOD = 'getGoals';

    public function rules(): array
    {
        return ['status' => ['sometimes', 'in:active,paused,completed,cancelled']];
    }
}
