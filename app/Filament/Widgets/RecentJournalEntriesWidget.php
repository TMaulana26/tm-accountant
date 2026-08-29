<?php

namespace App\Filament\Widgets;

use App\Models\JournalEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class RecentJournalEntriesWidget extends TableWidget
{
    protected static ?string $heading = 'Transaksi Jurnal Terbaru (Web & Telegram)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshTable(): void
    {
        // Re-renders recent journal table automatically
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => JournalEntry::query()->latest('date')->latest('id'))
            ->columns([
                TextColumn::make('entry_number')
                    ->label('No. Jurnal')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y'),

                TextColumn::make('description')
                    ->label('Deskripsi / Keterangan')
                    ->wrap(),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge(),

                TextColumn::make('total_debit')
                    ->label('Total Nilai')
                    ->state(fn (JournalEntry $record): string => 'Rp '.number_format($record->total_debit, 0, ',', '.'))
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->paginated([5, 10]);
    }
}
