<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->enum('transaction_type', ['income', 'expense', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->timestamp('reconciled_at')->nullable();

            // Transfer linking fields
            $table->foreignId('transfer_account_id')->nullable()
                ->constrained('finance_accounts')->nullOnDelete();
            $table->foreignId('transfer_transaction_id')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['user_id', 'transaction_date']);
            $table->index(['account_id', 'transaction_date']);
            $table->index('category_id');
            $table->index('transfer_transaction_id');
        });
    }
};
