<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('account_type', ['bank', 'investment', 'cash', 'credit_card', 'loan', 'e_wallet', 'other'])->notNull();
            $table->string('name');
            $table->char('currency_code', 3);
            $table->string('rate_source')->nullable()
                ->comment('Preferred exchange rate provider: null=default, payoneer, vietcombank, etc.');
            $table->text('account_number')->nullable();
            $table->string('institution_name')->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default_payment')->default(false);
            $table->boolean('exclude_from_total')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['user_id', 'is_active']);
        });
    }
};
