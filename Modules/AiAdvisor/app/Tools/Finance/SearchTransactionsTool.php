<?php

namespace Modules\AiAdvisor\Tools\Finance;

class SearchTransactionsTool extends GetTransactionsTool
{
    protected const NAME = 'searchTransactions';

    protected const DESCRIPTION = 'Search transaction descriptions and notes with optional account, category, type, and date filters.';

    protected const METHOD = 'searchTransactions';

    public function rules(): array
    {
        return [...parent::rules(), 'query' => ['required', 'string', 'max:200']];
    }
}
