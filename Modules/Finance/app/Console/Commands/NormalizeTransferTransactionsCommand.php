<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeTransferTransactionsCommand extends Command
{
    protected $signature = 'finance:normalize-transfers
                            {--dry-run : Show what would change without updating transactions}
                            {--pair=* : Explicit transaction ID pair, formatted as expense_id,income_id}';

    protected $description = 'Repair linked transfer pairs, set their type and direction, and clear their categories';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $links = DB::table('finance_transactions')
            ->whereNull('deleted_at')
            ->whereNotNull('transfer_transaction_id')
            ->get(['id', 'transfer_transaction_id']);

        $explicitPairs = collect($this->option('pair'))
            ->map(fn (string $pair) => $this->parsePair($pair))
            ->filter();

        if ($links->isEmpty() && $explicitPairs->isEmpty()) {
            $this->info('No linked transfer transactions found.');

            return self::SUCCESS;
        }

        $pairs = $links
            ->filter(fn ($transaction) => (int) $transaction->id !== (int) $transaction->transfer_transaction_id)
            ->mapWithKeys(function ($transaction) {
                $ids = [(int) $transaction->id, (int) $transaction->transfer_transaction_id];
                sort($ids);

                return [implode(':', $ids) => $ids];
            })
            ->merge($explicitPairs->mapWithKeys(function (array $ids) {
                sort($ids);

                return [implode(':', $ids) => $ids];
            }));

        $normalized = 0;
        $skipped = 0;

        foreach ($pairs as [$firstId, $secondId]) {
            $transactions = DB::table('finance_transactions')
                ->whereNull('deleted_at')
                ->whereIn('id', [$firstId, $secondId])
                ->get()
                ->keyBy('id');

            $first = $transactions->get($firstId);
            $second = $transactions->get($secondId);

            if (! $first || ! $second) {
                $this->warn("Skipping pair #{$firstId}/#{$secondId}: linked transaction is missing");
                $skipped++;

                continue;
            }

            if ((int) $first->user_id !== (int) $second->user_id) {
                $this->warn("Skipping pair #{$firstId}/#{$secondId}: transactions belong to different users");
                $skipped++;

                continue;
            }

            if ($this->hasConflictingLink($first, $secondId) || $this->hasConflictingLink($second, $firstId)) {
                $this->warn("Skipping pair #{$firstId}/#{$secondId}: a transaction links to a different row");
                $skipped++;

                continue;
            }

            $directions = $this->resolvePairDirections($first, $second);

            if ($directions === null) {
                $this->warn("Skipping pair #{$firstId}/#{$secondId}: transfer direction is ambiguous");
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($first, $second, $directions) {
                    $this->normalizeTransaction($first, $second, $directions[0]);
                    $this->normalizeTransaction($second, $first, $directions[1]);
                });
            }

            $normalized++;
        }

        $verb = $dryRun ? 'Would normalize' : 'Normalized';
        $this->info("{$verb} {$normalized} linked transfer pair(s).");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} invalid or ambiguous pair(s); no assumptions were made.");
        }

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hasConflictingLink(object $transaction, int $expectedLinkedId): bool
    {
        return $transaction->transfer_transaction_id !== null
            && (int) $transaction->transfer_transaction_id !== $expectedLinkedId;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parsePair(string $pair): ?array
    {
        $ids = array_map('trim', explode(',', $pair));

        if (count($ids) !== 2 || ! ctype_digit($ids[0]) || ! ctype_digit($ids[1]) || (int) $ids[0] === (int) $ids[1]) {
            $this->warn("Ignoring invalid --pair value '{$pair}'; expected two different transaction IDs, e.g. --pair=12,13");

            return null;
        }

        return [(int) $ids[0], (int) $ids[1]];
    }

    /**
     * @return array{0: 'outgoing'|'incoming', 1: 'outgoing'|'incoming'}|null
     */
    private function resolvePairDirections(object $first, object $second): ?array
    {
        $firstDirection = $this->resolveDirection($first);
        $secondDirection = $this->resolveDirection($second);

        if ($firstDirection && $secondDirection) {
            return $firstDirection !== $secondDirection
                ? [$firstDirection, $secondDirection]
                : null;
        }

        if ($firstDirection) {
            return [$firstDirection, $this->oppositeDirection($firstDirection)];
        }

        if ($secondDirection) {
            return [$this->oppositeDirection($secondDirection), $secondDirection];
        }

        return null;
    }

    private function resolveDirection(object $transaction): ?string
    {
        if (in_array($transaction->transfer_direction, ['outgoing', 'incoming'], true)) {
            return $transaction->transfer_direction;
        }

        return match ($transaction->transaction_type) {
            'expense' => 'outgoing',
            'income' => 'incoming',
            default => null,
        };
    }

    private function oppositeDirection(string $direction): string
    {
        return $direction === 'outgoing' ? 'incoming' : 'outgoing';
    }

    private function normalizeTransaction(object $transaction, object $linkedTransaction, string $direction): void
    {
        DB::table('finance_transactions')
            ->where('id', $transaction->id)
            ->update([
                'transaction_type' => 'transfer',
                'transfer_direction' => $direction,
                'category_id' => null,
                'transfer_account_id' => $linkedTransaction->account_id,
                'transfer_transaction_id' => $linkedTransaction->id,
                'updated_at' => now(),
            ]);
    }
}
