<?php

namespace Modules\AiAdvisor\Tools\Finance;

use Illuminate\Support\Facades\Gate;
use Modules\AiAdvisor\Contracts\{AgentTool, PreviewsArguments};
use Modules\AiAdvisor\DTOs\{ToolContext, ToolResult};
use Modules\AiAdvisor\Enums\ToolRiskLevel;
use Modules\Finance\Models\{Account, Borrowing, Budget, Category, Loan, SavingsGoal, Transaction};
use Modules\Finance\Services\FinancialToolService;

abstract class AbstractFinanceTool implements AgentTool, PreviewsArguments
{
    protected const NAME = '';

    protected const DESCRIPTION = '';

    protected const METHOD = '';

    protected const RISK = ToolRiskLevel::Read;

    /** Finance service that implements METHOD with signature (int $userId, array $arguments). */
    protected const SERVICE = FinancialToolService::class;

    public function __construct(protected FinancialToolService $finance) {}

    public function name(): string
    {
        return static::NAME;
    }

    public function description(): string
    {
        return static::DESCRIPTION;
    }

    public function inputSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->rules() as $field => $rules) {
            $rules = is_array($rules) ? $rules : explode('|', $rules);
            $ruleSet = collect($rules);
            $type = match (true) {
                $ruleSet->contains('integer') => 'integer',
                $ruleSet->contains('numeric') => 'number',
                $ruleSet->contains('boolean') => 'boolean',
                default => 'string',
            };
            $nullable = $ruleSet->contains('nullable');
            $properties[$field] = ['type' => $nullable ? [$type, 'null'] : $type];

            $inRule = $ruleSet->first(fn (mixed $rule) => is_string($rule) && str_starts_with($rule, 'in:'));
            if ($inRule) {
                $properties[$field]['enum'] = explode(',', substr($inRule, 3));
            }

            $dateRule = $ruleSet->first(fn (mixed $rule) => is_string($rule) && str_starts_with($rule, 'date_format:'));
            if ($dateRule) {
                $properties[$field]['description'] = 'Date format: '.substr($dateRule, 12);
            }

            if ($ruleSet->contains('required')) {
                $required[] = $field;
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ], fn (mixed $value) => $value !== []);
    }

    public function riskLevel(): ToolRiskLevel
    {
        return static::RISK;
    }

    public function execute(ToolContext $context, array $arguments): ToolResult
    {
        $this->authorize($context, $arguments);
        $method = static::METHOD;

        $service = static::SERVICE === FinancialToolService::class ? $this->finance : app(static::SERVICE);

        return ToolResult::success($service->{$method}((int) $context->actor->getKey(), $arguments));
    }

    /**
     * Replace record IDs with the actor's own record names so the confirm card
     * is readable. IDs that do not belong to the actor are left untouched.
     */
    public function previewArguments(ToolContext $context, array $arguments): array
    {
        $userId = (int) $context->actor->getKey();
        $debt = fn (?object $item, string $person) => $item
            ? "{$item->{$person}} ({$item->remaining_amount} {$item->currency_code} remaining)"
            : null;

        $preview = [];
        foreach ($arguments as $key => $value) {
            $label = is_numeric($value) ? match ($key) {
                'account_id', 'from_account_id', 'to_account_id' => Account::where('user_id', $userId)->find($value)?->name,
                'category_id' => Category::userCategories($userId)->find($value)?->name,
                'loan_id' => $debt(Loan::where('user_id', $userId)->find($value), 'borrower_name'),
                'borrowing_id' => $debt(Borrowing::where('user_id', $userId)->find($value), 'lender_name'),
                'transaction_id' => Transaction::where('user_id', $userId)->find($value)?->description,
                'budget_id' => Budget::where('user_id', $userId)->find($value)?->name,
                default => null,
            } : null;

            $preview[$label === null ? $key : preg_replace('/_id$/', '', $key)] = $label ?? $value;
        }

        return $preview;
    }

    /** @param array<string, mixed> $arguments */
    private function authorize(ToolContext $context, array $arguments): void
    {
        $gate = Gate::forUser($context->actor);

        match ($this->name()) {
            'getTransactions', 'searchTransactions' => $gate->authorize('viewAny', Transaction::class),
            'createTransaction' => $gate->authorize('create', Transaction::class),
            'updateTransaction', 'categorizeTransaction' => $gate->authorize('update', Transaction::findOrFail($arguments['transaction_id'])),
            'getBudgets' => $gate->authorize('viewAny', Budget::class),
            'createBudget' => $gate->authorize('create', Budget::class),
            'updateBudget' => $gate->authorize('update', Budget::findOrFail($arguments['budget_id'])),
            'getGoals' => $gate->authorize('viewAny', SavingsGoal::class),
            'createGoal' => $gate->authorize('create', SavingsGoal::class),
            'getAccounts', 'getAccountBalances' => $gate->authorize('viewAny', Account::class),
            'createTransfer' => $gate->authorize('create', Transaction::class),
            'getLoans', 'getDebtSummary' => $gate->authorize('viewAny', Loan::class),
            'getBorrowings' => $gate->authorize('viewAny', Borrowing::class),
            'recordLoanRepayment', 'writeOffLoan' => $gate->authorize('update', Loan::findOrFail($arguments['loan_id'])),
            'recordBorrowingRepayment', 'writeOffBorrowing' => $gate->authorize('update', Borrowing::findOrFail($arguments['borrowing_id'])),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    protected function dateRangeRules(): array
    {
        return [
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
