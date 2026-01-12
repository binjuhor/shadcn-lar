<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('transaction_type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->tinyInteger('day_of_week')->nullable(); // 0-6 (Sunday-Saturday) for weekly
            $table->tinyInteger('day_of_month')->nullable(); // 1-31 for monthly
            $table->tinyInteger('month_of_year')->nullable(); // 1-12 for yearly
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = indefinite
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_create')->default(true); // auto-create or just remind
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['user_id', 'is_active']);
            $table->index(['next_run_date', 'is_active']);
        });
    }
};
