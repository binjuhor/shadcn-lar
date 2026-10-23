<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetAccountsTool extends AbstractFinanceTool
{
    protected const NAME = 'getAccounts';

    protected const DESCRIPTION = 'List the authenticated user’s financial accounts.';

    protected const METHOD = 'getAccounts';

    public function rules(): array
    {
        return ['active' => ['sometimes', 'boolean']];
    }
}
