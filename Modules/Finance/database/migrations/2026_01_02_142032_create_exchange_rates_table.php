<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('target_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->decimal('bid_rate', 20, 10)->nullable();
            $table->decimal('ask_rate', 20, 10)->nullable();
            $table->string('source', 50)->default('manual');
            $table->timestamp('rate_date');
            $table->timestamps();

            $table->foreign('base_currency')->references('code')->on('currencies')->cascadeOnDelete();
            $table->foreign('target_currency')->references('code')->on('currencies')->cascadeOnDelete();
            $table->unique(['base_currency', 'target_currency', 'rate_date', 'source'], 'unique_rate');
            $table->index(['base_currency', 'target_currency']);
            $table->index('rate_date');
        });
    }
};
