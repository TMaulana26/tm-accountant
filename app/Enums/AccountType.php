<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasColor, HasLabel
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Kewajiban',
            self::Equity => 'Ekuitas',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Asset => 'success',
            self::Liability => 'warning',
            self::Equity => 'info',
            self::Revenue => 'primary',
            self::Expense => 'danger',
        };
    }

    /**
     * Determines if the account increases with Debit (Normal Debit).
     */
    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::Asset, self::Expense => true,
            self::Liability, self::Equity, self::Revenue => false,
        };
    }

    /**
     * Determines if the account increases with Credit (Normal Credit).
     */
    public function isCreditNormal(): bool
    {
        return ! $this->isDebitNormal();
    }
}
