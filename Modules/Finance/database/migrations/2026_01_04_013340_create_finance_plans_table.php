<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main financial plan entity
        Schema::create('finance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->year('start_year');
            $table->year('end_year');
            $table->char('currency_code', 3)->default('VND');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Yearly breakdown within a plan
        Schema::create('finance_plan_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_plan_id')->constrained('finance_plans')->cascadeOnDelete();
            $table->year('year');
            $table->decimal('planned_income', 15, 2)->default(0);
            $table->decimal('planned_expense', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['financial_plan_id', 'year']);
            $table->index('year');
        });

        // Line items (income/expense entries) per period
        Schema::create('finance_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->constrained('finance_plan_periods')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->decimal('planned_amount', 15, 2)->default(0);
            $table->enum('recurrence', ['one_time', 'monthly', 'quarterly', 'yearly'])->default('one_time');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plan_period_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_plan_items');
        Schema::dropIfExists('finance_plan_periods');
        Schema::dropIfExists('finance_plans');
    }
};
