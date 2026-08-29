<?php

namespace App\Filament\Widgets;

use App\Enums\TelegramMessageStatus;
use App\Models\TelegramMessage;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class TelegramChatStatsWidget extends BaseWidget
{
    #[On('echo:accounting,TelegramMessageLogged')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshStats(): void
    {
        // Re-renders chat stats automatically
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Percakapan', number_format(TelegramMessage::count(), 0, ',', '.'))
                ->description('Semua pesan masuk bot')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('Transaksi Sukses', number_format(TelegramMessage::where('status', TelegramMessageStatus::Processed)->count(), 0, ',', '.'))
                ->description('Tercatat di jurnal')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('OCR Struk / Nota', number_format(TelegramMessage::whereNotNull('receipt_image')->count(), 0, ',', '.'))
                ->description('Ekstraksi foto struk AI')
                ->descriptionIcon('heroicon-m-camera')
                ->color('info'),

            Stat::make('Dibatalkan (Undo)', number_format(TelegramMessage::where('status', TelegramMessageStatus::Reverted)->count(), 0, ',', '.'))
                ->description('Transaksi dibatalkan')
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('warning'),
        ];
    }
}
