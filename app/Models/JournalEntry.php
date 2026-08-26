<?php

namespace App\Models;

use App\Enums\JournalSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'date',
        'description',
        'source',
        'reference_number',
        'created_by',
        'receipt_image',
    ];

    public function getReceiptImageUrlAttribute(): ?string
    {
        if (empty($this->receipt_image)) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_image);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'source' => JournalSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JournalEntry $entry) {
            if (empty($entry->entry_number)) {
                $entry->entry_number = static::generateNextEntryNumber($entry->date ?? now());
            }
        });
    }

    public static function generateNextEntryNumber(?\DateTimeInterface $date = null): string
    {
        $date = $date ? Carbon::parse($date) : now();
        $prefix = 'JE-'.$date->format('Ym').'-';

        $lastEntry = static::where('entry_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($lastEntry && preg_match('/-(\d+)$/', $lastEntry->entry_number, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function telegramMessage(): HasOne
    {
        return $this->hasOne(TelegramMessage::class);
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->items->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->items->sum('credit');
    }

    public function isBalanced(): bool
    {
        return round($this->total_debit, 2) === round($this->total_credit, 2);
    }
}
