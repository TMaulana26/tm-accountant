<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;
use Spatie\Activitylog\Models\Activity;

class ActivityLogStatsWidget extends BaseWidget
{
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    #[On('echo:accounting,TelegramMessageLogged')]
    #[On('echo:accounting,WalletBalanceUpdated')]
    public function refreshStats(): void
    {
        // Re-renders activity stats automatically
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Aktivitas', number_format(Activity::count(), 0, ',', '.'))
                ->description('Semua catatan audit trail')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color('primary'),

            Stat::make('Transaksi Jurnal', number_format(Activity::where('log_name', 'transaksi_jurnal')->count(), 0, ',', '.'))
                ->description('Pencatatan & pembatalan')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),

            Stat::make('Dompet & Rekening', number_format(Activity::where('log_name', 'dompet_rekening')->count(), 0, ',', '.'))
                ->description('Mutasi & penyesuaian saldo')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Telegram Bot', number_format(Activity::where('log_name', 'telegram_bot')->count(), 0, ',', '.'))
                ->description('Interaksi asisten AI')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('warning'),
        ];
    }
}
