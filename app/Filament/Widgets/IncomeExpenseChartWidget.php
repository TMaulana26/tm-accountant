<?php

namespace App\Filament\Widgets;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\JournalItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class IncomeExpenseChartWidget extends ChartWidget
{
    protected ?string $heading = 'Tren Pemasukan vs Pengeluaran (6 Bulan Terakhir)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    #[On('refresh-transactions')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshChart(): void
    {
        // Re-renders chart automatically
    }

    protected function getData(): array
    {
        $months = [];
        $incomeData = [];
        $expenseData = [];

        $revenueAccountIds = Account::where('type', AccountType::Revenue)->pluck('id');
        $expenseAccountIds = Account::where('type', AccountType::Expense)->pluck('id');

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthLabel = $date->translatedFormat('M Y');
            $startOfMonth = $date->copy()->startOfMonth()->toDateString();
            $endOfMonth = $date->copy()->endOfMonth()->toDateString();

            $months[] = $monthLabel;

            // Income in this month (Credit - Debit on revenue accounts)
            $revTotals = JournalItem::whereIn('account_id', $revenueAccountIds)
                ->whereHas('journalEntry', function (Builder $q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('date', [$startOfMonth, $endOfMonth]);
                })
                ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as total')
                ->value('total') ?? 0;

            // Expense in this month (Debit - Credit on expense accounts)
            $expTotals = JournalItem::whereIn('account_id', $expenseAccountIds)
                ->whereHas('journalEntry', function (Builder $q) use ($startOfMonth, $endOfMonth) {
                    $q->whereBetween('date', [$startOfMonth, $endOfMonth]);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as total')
                ->value('total') ?? 0;

            $incomeData[] = (float) $revTotals;
            $expenseData[] = (float) $expTotals;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => $incomeData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'fill' => true,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => $expenseData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'fill' => true,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
