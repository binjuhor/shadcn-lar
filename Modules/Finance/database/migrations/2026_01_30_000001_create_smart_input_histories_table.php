<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_smart_input_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('finance_transactions')->nullOnDelete();
            $table->enum('input_type', ['text', 'voice', 'image', 'text_image']);
            $table->text('raw_text')->nullable();
            $table->json('parsed_result')->nullable();
            $table->string('ai_provider', 50)->nullable();
            $table->string('language', 5)->default('vi');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->boolean('transaction_saved')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'input_type']);
        });
    }
};
