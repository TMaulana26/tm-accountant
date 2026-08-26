<?php

namespace App\Models;

use App\Enums\TelegramMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_message_id',
        'chat_id',
        'from_id',
        'from_username',
        'raw_text',
        'intent',
        'ai_response',
        'raw_ai_payload',
        'receipt_image',
        'journal_entry_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'raw_ai_payload' => 'array',
            'status' => TelegramMessageStatus::class,
        ];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
