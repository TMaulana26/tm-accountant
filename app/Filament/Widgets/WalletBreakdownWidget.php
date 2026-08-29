<?php

namespace App\Filament\Widgets;

use App\Enums\AccountCategory;
use App\Models\Account;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class WalletBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Komposisi Saldo Dompet & Rekening';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    #[On('echo:accounting,WalletBalanceUpdated')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshChart(): void
    {
        // Re-renders chart automatically
    }

    protected function getData(): array
    {
        $wallets = Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->get();

        $labels = [];
        $data = [];
        $colors = [
            '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6', '#f97316', '#64748b',
        ];

        foreach ($wallets as $wallet) {
            $labels[] = $wallet->name;
            $data[] = max(0, (float) $wallet->balance);
        }

        if (empty($data) || array_sum($data) === 0) {
            return [
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['#94a3b8'],
                    ],
                ],
                'labels' => ['Belum ada saldo'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Saldo (Rp)',
                    'data' => $data,
                    'backgroundColor' => array_slice(array_merge($colors, $colors), 0, count($data)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
