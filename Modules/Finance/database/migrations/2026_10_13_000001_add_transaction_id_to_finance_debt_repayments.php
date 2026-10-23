<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a repayment to the ledger transaction recorded with it, so deleting
 * the repayment can reverse the account balance it moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['finance_loan_repayments', 'finance_borrowing_repayments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('transaction_id')->nullable()->after('amount')->constrained('finance_transactions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['finance_loan_repayments', 'finance_borrowing_repayments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('transaction_id');
            });
        }
    }
};
