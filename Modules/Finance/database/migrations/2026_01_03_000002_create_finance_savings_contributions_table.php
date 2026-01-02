<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_savings_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained('finance_savings_goals')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions')->nullOnDelete();
            $table->bigInteger('amount');
            $table->char('currency_code', 3);
            $table->date('contribution_date');
            $table->text('notes')->nullable();
            $table->enum('type', ['manual', 'linked'])->default('manual');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['savings_goal_id', 'contribution_date'], 'savings_contributions_goal_date_idx');
        });
    }
};
