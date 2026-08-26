<?php

namespace App\Models;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Account extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'account_number', 'account_holder', 'is_default', 'is_active', 'color', 'icon'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('dompet_rekening')
            ->setDescriptionForEvent(fn (string $eventName) => match ($eventName) {
                'created' => "Menambahkan dompet/rekening [{$this->code}] {$this->name}",
                'updated' => "Mengubah data dompet/rekening [{$this->code}] {$this->name}",
                'deleted' => "Menghapus dompet/rekening [{$this->code}] {$this->name}",
                default => "Aktivitas dompet [{$this->code}] {$this->name}",
            });
    }

    protected $fillable = [
        'code',
        'name',
        'account_number',
        'account_holder',
        'type',
        'category',
        'parent_id',
        'is_system',
        'is_default',
        'is_active',
        'color',
        'icon',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'category' => AccountCategory::class,
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function markAsDefault(): void
    {
        static::where('category', AccountCategory::CashAndBank)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true, 'is_active' => true]);
    }

    public function scopeWallets(Builder $query): Builder
    {
        return $query->where('category', AccountCategory::CashAndBank)->whereNotNull('parent_id');
    }

    public function getWalletTypeLabelAttribute(): string
    {
        $nameLower = strtolower($this->name);
        if (str_contains($nameLower, 'kas') || str_contains($nameLower, 'tunai') || str_contains($nameLower, 'dompet')) {
            return 'Uang Tunai';
        }
        if (str_contains($nameLower, 'gopay') || str_contains($nameLower, 'ovo') || str_contains($nameLower, 'dana') || str_contains($nameLower, 'shopeepay') || str_contains($nameLower, 'e-wallet') || str_contains($nameLower, 'ewallet')) {
            return 'E-Wallet';
        }

        return 'Rekening Bank';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('code');
    }

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfCategory(Builder $query, AccountCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    public function scopeCashAndBank(Builder $query): Builder
    {
        return $query->where('category', AccountCategory::CashAndBank->value);
    }

    public function scopeAsset(Builder $query): Builder
    {
        return $query->where('type', AccountType::Asset->value);
    }

    public function scopeLiability(Builder $query): Builder
    {
        return $query->where('type', AccountType::Liability->value);
    }

    public function scopeEquity(Builder $query): Builder
    {
        return $query->where('type', AccountType::Equity->value);
    }

    public function scopeRevenue(Builder $query): Builder
    {
        return $query->where('type', AccountType::Revenue->value);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', AccountType::Expense->value);
    }

    /**
     * Compute current balance based on normal balance direction.
     */
    public function getBalanceAttribute(): float
    {
        $totals = $this->journalItems()
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return $this->type->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);
    }

    /**
     * Calculate balance up to a specific date.
     */
    public function getBalanceAtDate(?CarbonInterface $date = null): float
    {
        $query = $this->journalItems();

        if ($date) {
            $dateStr = $date->format('Y-m-d');
            $query->whereHas('journalEntry', function (Builder $q) use ($dateStr) {
                $q->whereDate('date', '<=', $dateStr);
            });
        }

        $totals = $query->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return $this->type->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);
    }

    /**
     * Calculate net movement (or balance for revenue/expense) between date range.
     */
    public function getMovementBetween(CarbonInterface $startDate, CarbonInterface $endDate): float
    {
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        $totals = $this->journalItems()
            ->whereHas('journalEntry', function (Builder $q) use ($startStr, $endStr) {
                $q->whereDate('date', '>=', $startStr)
                    ->whereDate('date', '<=', $endStr);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return $this->type->isDebitNormal() ? ($debit - $credit) : ($credit - $debit);
    }

    public function getFormattedCodeAndNameAttribute(): string
    {
        return "[{$this->code}] {$this->name}";
    }
}
