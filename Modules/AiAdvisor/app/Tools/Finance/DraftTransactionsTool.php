<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Modules\AiAdvisor\Contracts\AgentTool;
use Modules\AiAdvisor\DTOs\{ToolContext, ToolResult};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Services\SmartInputParsingService;
use Modules\Settings\Services\AiCredentialResolver;

/**
 * Turns transactions the user described in chat into editable drafts. Nothing is written
 * to the ledger: the user reviews and saves each draft in the app. Full drafts go to the
 * client through result meta; the model only sees a compact summary.
 */
class DraftTransactionsTool implements AgentTool
{
    public const NAME = 'draftTransactions';

    public function __construct(
        private SmartInputParsingService $parsing,
        private AiCredentialResolver $credentials,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function description(): string
    {
        return 'Prepare editable transaction drafts from income, expenses, or transfers the user wants recorded. Only use it for clear log entries or after the user agrees to record amounts mentioned in a question. Call once per user message with every transaction in it. Drafts are reviewed and saved by the user; nothing is recorded by this tool.';
    }

    public function inputSchema(): array
    {
        $nullableString = fn (string $description) => ['type' => ['string', 'null'], 'description' => $description];

        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'One entry per transaction.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['income', 'expense', 'transfer']],
                            'amount' => ['type' => 'number', 'description' => 'Positive amount in full units (50k = 50000).'],
                            'description' => ['type' => 'string', 'description' => 'Merchant or purpose.'],
                            'transaction_date' => $nullableString('Date format: Y-m-d. Omit for today.'),
                            'category_hint' => $nullableString('Short category word, e.g. food, salary.'),
                            'account_hint' => $nullableString('Bank, wallet, or card the money came from.'),
                            'transfer_account_hint' => $nullableString('Destination account for transfers.'),
                        ],
                        'required' => ['type', 'amount', 'description'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.type' => ['required', 'in:income,expense,transfer'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.transaction_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.category_hint' => ['nullable', 'string', 'max:100'],
            'items.*.account_hint' => ['nullable', 'string', 'max:100'],
            'items.*.transfer_account_hint' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function riskLevel(): ToolRiskLevel
    {
        return ToolRiskLevel::Read;
    }

    public function execute(ToolContext $context, array $arguments): ToolResult
    {
        $items = collect($arguments['items'])
            ->map(fn (array $item) => [...$item, 'confidence' => 0.8])
            ->all();

        $drafts = $this->parsing->draftsFromResults(
            $context->actor,
            $items,
            $this->credentials->provider($context->actor),
            $context->locale === 'en' ? 'en' : 'vi',
        );

        return ToolResult::success([
            'drafted' => count($drafts),
            'items' => collect($drafts)->map(fn (array $draft) => [
                'type' => $draft['type'],
                'amount' => $draft['amount'],
                'description' => $draft['description'],
                'transaction_date' => $draft['transaction_date'],
                'category' => $draft['suggested_category']['name'] ?? null,
                'account' => $draft['suggested_account']['name'] ?? null,
            ])->all(),
            'status' => 'Drafts shown to the user for review. Not saved yet.',
        ], ['drafts' => $drafts]);
    }
}
