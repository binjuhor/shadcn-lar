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
            $table->enum('account_type', ['bank', 'investment', 'cash', 'credit_card']);
            $table->string('name');
            $table->char('currency_code', 3);
            $table->text('account_number')->nullable();
            $table->string('institution_name')->nullable();
            $table->bigInteger('current_balance')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['user_id', 'is_active']);
        });
    }
};
