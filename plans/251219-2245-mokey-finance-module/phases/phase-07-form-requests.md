# Phase 07: Form Requests & Validation

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: Phase 02 (tables for foreign key validation)

## Overview
- Priority: medium
- Status: pending
- Description: Create FormRequest classes for all CRUD operations. Validate amounts as integers (cents). Handle transfer validation.

## Key Insights
From Laravel guidelines: Use array notation for rules. Custom rules use snake_case.

## Requirements
### Functional
- Request classes for Account, Transaction, Budget, Goal, Category
- Amount validation (positive integers)
- Date range validation for budgets
- Transfer validation (different accounts)

### Non-functional
- Separate Store and Update requests where logic differs
- Use array rule notation

## Related Code Files
### Files to Create
```
Modules/Mokey/Http/Requests/
├── StoreAccountRequest.php
├── UpdateAccountRequest.php
├── StoreTransactionRequest.php
├── UpdateTransactionRequest.php
├── StoreBudgetRequest.php
├── UpdateBudgetRequest.php
├── StoreGoalRequest.php
├── UpdateGoalRequest.php
├── StoreCategoryRequest.php
└── UpdateCategoryRequest.php
```

## Implementation Steps

### 1. Create StoreAccountRequest
```php
// Modules/Mokey/Http/Requests/StoreAccountRequest.php
namespace Modules\Mokey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return [
            'account_type' => [
                'required',
                'string',
                'in:checking,savings,credit_card,cash,investment',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:mokey_currencies,code',
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'institution_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'initial_balance' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'boolean',
            ],
            'include_in_net_worth' => [
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert float balance to cents if provided as float
        if ($this->has('initial_balance') && is_float($this->initial_balance)) {
            $this->merge([
                'initial_balance' => (int) round($this->initial_balance * 100),
            ]);
        }
    }
}
```

### 2. Create StoreTransactionRequest
```php
// Modules/Mokey/Http/Requests/StoreTransactionRequest.php
namespace Modules\Mokey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('mokey_accounts', 'id')->where('user_id', auth()->id()),
            ],
            'category_id' => [
                'nullable',
                'integer',
                'exists:mokey_categories,id',
            ],
            'transfer_account_id' => [
                'nullable',
                'required_if:transaction_type,transfer',
                'integer',
                'different:account_id',
                Rule::exists('mokey_accounts', 'id')->where('user_id', auth()->id()),
            ],
            'transaction_type' => [
                'required',
                'string',
                'in:income,expense,transfer',
            ],
            'amount' => [
                'required',
                'integer',
                'min:1', // At least 1 cent
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:mokey_currencies,code',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'transaction_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'transfer_account_id.different' => 'Cannot transfer to the same account.',
            'transfer_account_id.required_if' => 'Transfer destination account is required.',
            'amount.min' => 'Amount must be at least 1 cent.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert float amount to cents
        if ($this->has('amount') && is_float($this->amount)) {
            $this->merge([
                'amount' => (int) round($this->amount * 100),
            ]);
        }
    }
}
```

### 3. Create StoreBudgetRequest
```php
// Modules/Mokey/Http/Requests/StoreBudgetRequest.php
namespace Modules\Mokey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'integer',
                'exists:mokey_categories,id',
            ],
            'period_type' => [
                'required',
                'string',
                'in:monthly,yearly,custom',
            ],
            'allocated_amount' => [
                'required',
                'integer',
                'min:1',
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:mokey_currencies,code',
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after' => 'End date must be after start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Auto-calculate dates for monthly/yearly periods
        if ($this->period_type === 'monthly' && !$this->has('end_date')) {
            $startDate = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : now();
            $this->merge([
                'start_date' => $startDate->startOfMonth()->toDateString(),
                'end_date' => $startDate->endOfMonth()->toDateString(),
            ]);
        }

        if ($this->period_type === 'yearly' && !$this->has('end_date')) {
            $startDate = $this->start_date ? \Carbon\Carbon::parse($this->start_date) : now();
            $this->merge([
                'start_date' => $startDate->startOfYear()->toDateString(),
                'end_date' => $startDate->endOfYear()->toDateString(),
            ]);
        }
    }
}
```

### 4. Create StoreGoalRequest
```php
// Modules/Mokey/Http/Requests/StoreGoalRequest.php
namespace Modules\Mokey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_goal_id' => [
                'nullable',
                'integer',
                'exists:mokey_goals,id',
            ],
            'goal_type' => [
                'required',
                'string',
                'in:savings,debt_payoff,purchase',
            ],
            'timeframe' => [
                'required',
                'string',
                'in:short_term,medium_term,long_term',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'target_amount' => [
                'required',
                'integer',
                'min:1',
            ],
            'current_amount' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'exists:mokey_currencies,code',
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'target_date' => [
                'nullable',
                'date',
                'after:start_date',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'target_date.after' => 'Target date must be after start date.',
            'target_amount.min' => 'Target amount must be at least 1 cent.',
        ];
    }
}
```

### 5. Create StoreCategoryRequest
```php
// Modules/Mokey/Http/Requests/StoreCategoryRequest.php
namespace Modules\Mokey\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'type' => [
                'required',
                'string',
                'in:income,expense',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:mokey_categories,id',
                Rule::notIn([$this->route('category')?->id]), // Prevent self-referencing
            ],
            'icon' => [
                'nullable',
                'string',
                'max:50',
            ],
            'color' => [
                'nullable',
                'string',
                'regex:/^#[a-fA-F0-9]{6}$/', // Hex color
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Color must be a valid hex color (e.g., #FF5733).',
            'parent_id.not_in' => 'Category cannot be its own parent.',
        ];
    }
}
```

### 6. Create Update variants
Update requests extend Store requests but may have different rules (e.g., unique check with ignore).

```php
// Modules/Mokey/Http/Requests/UpdateAccountRequest.php
namespace Modules\Mokey\Http\Requests;

class UpdateAccountRequest extends StoreAccountRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Can't change currency on existing account with transactions
        if ($this->route('account')->transactions()->exists()) {
            $rules['currency_code'][] = 'in:' . $this->route('account')->currency_code;
        }

        return $rules;
    }
}
```

## Todo List
- [ ] Create StoreAccountRequest with balance conversion
- [ ] Create UpdateAccountRequest with currency lock
- [ ] Create StoreTransactionRequest with transfer validation
- [ ] Create UpdateTransactionRequest
- [ ] Create StoreBudgetRequest with date calculation
- [ ] Create UpdateBudgetRequest
- [ ] Create StoreGoalRequest
- [ ] Create UpdateGoalRequest
- [ ] Create StoreCategoryRequest with self-reference prevention
- [ ] Create UpdateCategoryRequest

## Success Criteria
- [ ] Invalid data returns 422 with proper error messages
- [ ] Amount conversion from float to cents works
- [ ] Transfer to same account prevented
- [ ] Date ranges validated correctly

## Risk Assessment
- **Risk:** Float precision loss in conversion. **Mitigation:** Use round() and cast to int.
- **Risk:** Validation passes invalid foreign key. **Mitigation:** Use exists rule with user scope.

## Security Considerations
- Validate account/goal ownership in exists rules
- Prevent changing currency on accounts with transactions (data integrity)
- Sanitize all string inputs

## Next Steps
Proceed to Phase 08: Routes & Navigation
