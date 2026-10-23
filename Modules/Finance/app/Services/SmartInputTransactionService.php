<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Exceptions\SmartInputException;
use Modules\Finance\Models\{Account, Category, SmartInputHistory, Transaction};

/**
 * Saves a reviewed Smart Input draft as a real transaction: verifies ownership, records
 * income/expense/transfer, links the SmartInputHistory row, copies the bill attachment,
 * and auto-creates a loan for loan categories.
 */
class SmartInputTransactionService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected BillAttachmentService $billAttachmentService,
        protected LoanService $loanService,
    ) {}

    /**
     * Validation rules shared by every endpoint that saves a draft.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'transfer_account_id' => ['nullable', 'integer'],
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'history_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated against rules()
     *
     * @throws SmartInputException
     */
    public function save(User $user, array $data): Transaction
    {
        $account = Account::where('user_id', $user->id)->find($data['account_id']);

        if (! $account) {
            throw new SmartInputException('Account not found.');
        }

        $history = null;
        if (! empty($data['history_id'])) {
            $history = SmartInputHistory::forUser($user->id)->find($data['history_id']);

            if (! $history) {
                throw new SmartInputException('Smart Input history not found.');
            }
        }

        $category = null;
        if (! empty($data['category_id'])) {
            $category = Category::where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
                ->find($data['category_id']);

            if (! $category) {
                throw new SmartInputException('Category not found.');
            }
        }

        $destination = null;
        if ($data['type'] === 'transfer') {
            if (empty($data['transfer_account_id'])) {
                throw new SmartInputException('Destination account is required for transfers.');
            }

            if ((int) $data['transfer_account_id'] === $account->id) {
                throw new SmartInputException('Source and destination accounts must be different.');
            }

            $destination = Account::where('user_id', $user->id)->find($data['transfer_account_id']);

            if (! $destination) {
                throw new SmartInputException('Destination account not found.');
            }
        }

        return DB::transaction(function () use ($user, $data, $account, $history, $category, $destination) {
            $transaction = $this->record($data, $account, $destination);

            if ($history) {
                $history->update([
                    'transaction_id' => $transaction->id,
                    'transaction_saved' => true,
                    'parsed_result' => array_merge($history->parsed_result ?? [], [
                        'type' => $data['type'],
                        'amount' => $data['amount'],
                        'description' => $data['description'],
                        'transaction_date' => $data['transaction_date'],
                    ]),
                ]);

                // Copy the receipt/bill attached during parsing onto the transaction
                $this->billAttachmentService->copyCollection($history, 'input_attachments', $transaction, 'bills');
            }

            if ($category?->is_loan) {
                $this->loanService->createLoan([
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'borrower_name' => $data['description'],
                    'amount' => $data['amount'],
                    'currency_code' => $account->currency_code,
                    'loan_date' => $data['transaction_date'],
                    'message' => $data['notes'] ?? null,
                ]);
            }

            return $transaction;
        });
    }

    /** @param array<string, mixed> $data */
    protected function record(array $data, Account $account, ?Account $destination): Transaction
    {
        if ($destination) {
            return $this->transactionService->recordTransfer([
                'from_account_id' => $account->id,
                'to_account_id' => $destination->id,
                'amount' => $data['amount'],
                'description' => $data['description'],
                'transaction_date' => $data['transaction_date'],
                'notes' => $data['notes'] ?? null,
            ])['debit'];
        }

        $payload = [
            'account_id' => $account->id,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'notes' => $data['notes'] ?? null,
        ];

        return $data['type'] === 'income'
            ? $this->transactionService->recordIncome($payload)
            : $this->transactionService->recordExpense($payload);
    }
}
