<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->boolean('is_passive')->default(false)->after('is_active');
        });
    }
};
