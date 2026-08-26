<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountCategory: string implements HasLabel
{
    // Aset
    case CashAndBank = 'cash_and_bank';
    case AccountsReceivable = 'accounts_receivable';
    case Inventory = 'inventory';
    case OtherCurrentAsset = 'other_current_asset';
    case FixedAsset = 'fixed_asset';

    // Kewajiban
    case AccountsPayable = 'accounts_payable';
    case CreditCard = 'credit_card';
    case OtherCurrentLiability = 'other_current_liability';
    case LongTermLiability = 'long_term_liability';

    // Ekuitas
    case Equity = 'equity';
    case RetainedEarnings = 'retained_earnings';

    // Pendapatan
    case OperatingRevenue = 'operating_revenue';
    case OtherRevenue = 'other_revenue';

    // Beban
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case OperatingExpense = 'operating_expense';
    case OtherExpense = 'other_expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::CashAndBank => 'Kas & Bank',
            self::AccountsReceivable => 'Piutang',
            self::Inventory => 'Persediaan',
            self::OtherCurrentAsset => 'Aset Lancar Lainnya',
            self::FixedAsset => 'Aset Tetap',
            self::AccountsPayable => 'Hutang Usaha',
            self::CreditCard => 'Kartu Kredit / Paylater',
            self::OtherCurrentLiability => 'Kewajiban Lancar Lainnya',
            self::LongTermLiability => 'Kewajiban Jangka Panjang',
            self::Equity => 'Modal',
            self::RetainedEarnings => 'Laba Ditahan',
            self::OperatingRevenue => 'Pendapatan Usaha / Gaji',
            self::OtherRevenue => 'Pendapatan Lainnya',
            self::CostOfGoodsSold => 'Harga Pokok Penjualan',
            self::OperatingExpense => 'Beban Operasional',
            self::OtherExpense => 'Beban Lainnya',
        };
    }

    public function defaultAccountType(): AccountType
    {
        return match ($this) {
            self::CashAndBank,
            self::AccountsReceivable,
            self::Inventory,
            self::OtherCurrentAsset,
            self::FixedAsset => AccountType::Asset,

            self::AccountsPayable,
            self::CreditCard,
            self::OtherCurrentLiability,
            self::LongTermLiability => AccountType::Liability,

            self::Equity,
            self::RetainedEarnings => AccountType::Equity,

            self::OperatingRevenue,
            self::OtherRevenue => AccountType::Revenue,

            self::CostOfGoodsSold,
            self::OperatingExpense,
            self::OtherExpense => AccountType::Expense,
        };
    }
}
