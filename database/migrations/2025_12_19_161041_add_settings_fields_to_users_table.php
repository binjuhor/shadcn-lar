<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->text('bio')->nullable()->after('email');
            $table->json('urls')->nullable()->after('bio');
            $table->string('language')->default('en')->after('urls');
            $table->date('dob')->nullable()->after('language');
            $table->json('appearance_settings')->nullable()->after('password');
            $table->json('notification_settings')->nullable()->after('appearance_settings');
            $table->json('display_settings')->nullable()->after('notification_settings');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'bio',
                'urls',
                'language',
                'dob',
                'appearance_settings',
                'notification_settings',
                'display_settings',
            ]);
        });
    }
};
