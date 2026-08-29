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
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->index(['chat_id', 'telegram_message_id'], 'tm_chat_msg_id_idx');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index(['source', 'reference_number'], 'je_source_ref_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_messages', function (Blueprint $table) {
            $table->dropIndex('tm_chat_msg_id_idx');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('je_source_ref_idx');
        });
    }
};
