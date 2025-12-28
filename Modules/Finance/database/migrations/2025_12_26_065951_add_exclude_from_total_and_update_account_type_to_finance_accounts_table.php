<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->boolean('exclude_from_total')->default(false)->after('is_active');
        });

        DB::statement("ALTER TABLE `finance_accounts` MODIFY `account_type` ENUM('bank', 'investment', 'cash', 'credit_card', 'loan') NOT NULL");
    }
};