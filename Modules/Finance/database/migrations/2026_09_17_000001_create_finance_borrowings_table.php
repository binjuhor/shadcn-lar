<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_borrowings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions')->nullOnDelete();
            $table->string('lender_name');
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_repaid', 15, 2)->default(0);
            $table->char('currency_code', 3);
            $table->date('borrowed_date');
            $table->date('due_date')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['active', 'partially_repaid', 'repaid', 'written_off'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'borrowed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_borrowings');
    }
};
