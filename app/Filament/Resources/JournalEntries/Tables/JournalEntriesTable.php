<?php

namespace App\Filament\Resources\JournalEntries\Tables;

use App\Enums\JournalSource;
use App\Models\JournalEntry;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JournalEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->label('No. Jurnal')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Deskripsi / Keterangan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge(),

                TextColumn::make('reference_number')
                    ->label('No. Referensi')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_debit')
                    ->label('Total Transaksi')
                    ->state(fn (JournalEntry $record): string => 'Rp '.number_format($record->total_debit, 0, ',', '.'))
                    ->alignEnd()
                    ->weight('semibold'),

                ImageColumn::make('receipt_image')
                    ->label('Struk')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(null)
                    ->placeholder('-'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('source')
                    ->label('Sumber Transaksi')
                    ->options(JournalSource::class),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('viewReceipt')
                    ->label('Struk')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->visible(fn (JournalEntry $record): bool => ! empty($record->receipt_image))
                    ->modalHeading(fn (JournalEntry $record): string => "Lampiran Struk: {$record->entry_number}")
                    ->modalContent(fn (JournalEntry $record) => view('filament.components.receipt-modal-preview', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
