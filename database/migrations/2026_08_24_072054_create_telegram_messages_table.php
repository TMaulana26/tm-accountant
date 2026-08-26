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
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('chat_id')->index();
            $table->string('from_id')->index();
            $table->string('from_username')->nullable();
            $table->text('raw_text');
            $table->string('intent')->nullable();
            $table->text('ai_response')->nullable();
            $table->json('raw_ai_payload')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('status')->default('processed'); // TelegramMessageStatus enum
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
