<?php

namespace App\Filament\Widgets;

use App\Enums\AccountCategory;
use App\Models\Account;
use App\Services\Accounting\FinancialReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AccountBalanceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    #[On('echo:accounting,WalletBalanceUpdated')]
    public function refreshStats(): void
    {
        // Re-renders widget stats automatically
    }

    protected function getStats(): array
    {
        $cashAccounts = Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->get();

        $totalCash = $cashAccounts->sum(fn ($a) => $a->balance);

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $reportService = app(FinancialReportService::class);
        $incomeStatement = $reportService->getIncomeStatement($startOfMonth, $endOfMonth);

        $totalRevenue = $incomeStatement['total_operating_revenue'] + $incomeStatement['total_other_revenue'];
        $totalExpense = $incomeStatement['total_operating_expenses'] + $incomeStatement['total_other_expenses'];
        $netProfit = $incomeStatement['net_profit'];

        return [
            Stat::make('Total Saldo Kas & Bank', 'Rp '.number_format($totalCash, 0, ',', '.'))
                ->description('Total dana likuid di semua rekening/dompet')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('primary'),

            Stat::make('Pemasukan Bulan Ini', 'Rp '.number_format($totalRevenue, 0, ',', '.'))
                ->description('Periode: '.now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Pengeluaran Bulan Ini', 'Rp '.number_format($totalExpense, 0, ',', '.'))
                ->description('Periode: '.now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Surplus / Laba Bersih', 'Rp '.number_format($netProfit, 0, ',', '.'))
                ->description($netProfit >= 0 ? 'Surplus bulan ini' : 'Defisit bulan ini')
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-sparkles' : 'heroicon-m-exclamation-triangle')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
        ];
    }
}
