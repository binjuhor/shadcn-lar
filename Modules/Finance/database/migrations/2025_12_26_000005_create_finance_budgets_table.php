<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->enum('period_type', ['weekly', 'monthly', 'quarterly', 'yearly', 'custom']);
            $table->bigInteger('allocated_amount');
            $table->bigInteger('spent_amount')->default(0);
            $table->char('currency_code', 3);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('rollover')->default(false);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['user_id', 'category_id']);
            $table->index(['start_date', 'end_date']);
        });
    }
};
