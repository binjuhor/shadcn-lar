<?php

namespace Modules\AiAdvisor\Tools\Finance;

class GetCategoriesTool extends AbstractFinanceTool
{
    protected const NAME = 'getCategories';

    protected const DESCRIPTION = 'List available system and user categories.';

    protected const METHOD = 'getCategories';

    public function rules(): array
    {
        return ['type' => ['sometimes', 'in:income,expense,both']];
    }
}
