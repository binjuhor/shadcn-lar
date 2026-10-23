<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_borrowing_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrowing_id')->constrained('finance_borrowings')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('repaid_at');
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('borrowing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_borrowing_repayments');
    }
};
