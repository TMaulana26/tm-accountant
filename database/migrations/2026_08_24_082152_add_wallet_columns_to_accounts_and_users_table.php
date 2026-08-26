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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('name');
            $table->string('account_holder')->nullable()->after('account_number');
            $table->boolean('is_default')->default(false)->after('is_system');
            $table->string('color')->nullable()->after('is_default');
            $table->string('icon')->nullable()->after('color');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('wallet_setup_completed_at')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['account_number', 'account_holder', 'is_default', 'color', 'icon']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_setup_completed_at']);
        });
    }
};
