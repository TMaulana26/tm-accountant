<?php

namespace App\Models;

use App\Enums\TelegramMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TelegramMessage extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['raw_text', 'intent', 'status', 'journal_entry_id', 'from_username'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('telegram_bot')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Menerima pesan Telegram dari @{$this->from_username}",
                'updated' => "Memproses pesan Telegram: {$this->intent} (".($this->status?->value ?? 'processed').')',
                default => "Aktivitas pesan Telegram #{$this->telegram_message_id}",
            });
    }

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
