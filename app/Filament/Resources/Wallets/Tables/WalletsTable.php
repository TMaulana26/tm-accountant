<?php

namespace App\Filament\Resources\Wallets\Tables;

use App\Enums\JournalSource;
use App\Events\WalletBalanceUpdated;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Dompet / Bank')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Account $record): string => $record->wallet_type_label.' • Kode: '.$record->code),

                TextColumn::make('account_number')
                    ->label('No. Rekening / HP')
                    ->placeholder('-')
                    ->copyable()
                    ->description(fn (Account $record): ?string => $record->account_holder ? 'a.n. '.$record->account_holder : null),

                TextColumn::make('balance')
                    ->label('Saldo Real-Time')
                    ->state(fn (Account $record): string => 'Rp '.number_format($record->balance, 0, ',', '.'))
                    ->alignEnd()
                    ->weight('black')
                    ->color(fn (Account $record): string => $record->balance >= 0 ? 'success' : 'danger'),

                IconColumn::make('is_default')
                    ->label('Utama (Default)')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter(),

                IconColumn::make('is_pinned')
                    ->label('📌 Pin Dashboard')
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('primary')
                    ->falseColor('gray')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('togglePin')
                        ->label(fn (Account $record): string => $record->is_pinned ? 'Lepas Pin Dashboard' : '📌 Pin ke Dashboard')
                        ->icon(fn (Account $record): string => $record->is_pinned ? 'heroicon-m-bookmark-slash' : 'heroicon-m-bookmark')
                        ->color(fn (Account $record): string => $record->is_pinned ? 'gray' : 'primary')
                        ->action(function (Account $record) {
                            $isPinned = $record->togglePin();
                            event(new WalletBalanceUpdated($record));
                            Notification::make()
                                ->title($isPinned ? '📌 Dompet Disematkan ke Dashboard' : 'Pin Dompet Dilepas')
                                ->body("{$record->name} ".($isPinned ? 'sekarang tampil di widget favorit Dashboard.' : 'tidak lagi disematkan di Dashboard.'))
                                ->success()
                                ->send();
                        }),

                    Action::make('setDefault')
                        ->label('Jadikan Dompet Utama')
                        ->icon('heroicon-m-star')
                        ->color('warning')
                        ->hidden(fn (Account $record): bool => $record->is_default)
                        ->action(function (Account $record) {
                            $record->markAsDefault();
                            event(new WalletBalanceUpdated($record));
                            Notification::make()
                                ->title('Dompet Utama Diperbarui')
                                ->body("{$record->name} sekarang menjadi dompet default.")
                                ->success()
                                ->send();
                        }),

                    Action::make('adjustBalance')
                        ->label('Penyesuaian Saldo (Opname)')
                        ->icon('heroicon-m-scale')
                        ->color('info')
                        ->schema([
                            TextInput::make('current_balance')
                                ->label('Saldo Buku Saat Ini')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn (Account $record): string => 'Rp '.number_format($record->balance, 0, ',', '.')),

                            TextInput::make('real_balance')
                                ->label('Saldo Riil / Fisik Sebenarnya')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->placeholder('0'),

                            TextInput::make('reason')
                                ->label('Alasan Penyesuaian')
                                ->default('Penyesuaian Saldo Fisik / Opname')
                                ->required(),

                            DatePicker::make('date')
                                ->label('Tanggal Penyesuaian')
                                ->default(now())
                                ->required(),
                        ])
                        ->action(function (Account $record, array $data) {
                            $journal = app(AccountingService::class)->adjustBalance(
                                account: $record,
                                realBalance: (float) $data['real_balance'],
                                reason: $data['reason'],
                                date: Carbon::parse($data['date']),
                                creator: auth()->user()
                            );

                            if ($journal) {
                                Notification::make()
                                    ->title('Saldo Berhasil Disesuaikan')
                                    ->body("Jurnal penyesuaian {$journal->entry_number} telah dibuat.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Tidak Ada Perubahan Saldo')
                                    ->body('Saldo riil yang dimasukkan sama persis dengan saldo buku.')
                                    ->info()
                                    ->send();
                            }
                        }),

                    Action::make('transfer')
                        ->label('Transfer ke Dompet Lain')
                        ->icon('heroicon-m-arrows-right-left')
                        ->color('primary')
                        ->schema([
                            Select::make('destination_account_id')
                                ->label('Pilih Dompet Tujuan')
                                ->options(fn (Account $record) => Account::wallets()->where('id', '!=', $record->id)->where('is_active', true)->pluck('name', 'id'))
                                ->required(),

                            TextInput::make('amount')
                                ->label('Nominal Transfer')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),

                            TextInput::make('description')
                                ->label('Keterangan Transfer')
                                ->default('Transfer Antar Rekening / Dompet')
                                ->required(),

                            DatePicker::make('date')
                                ->label('Tanggal Transfer')
                                ->default(now())
                                ->required(),
                        ])
                        ->action(function (Account $record, array $data) {
                            $dest = Account::findOrFail($data['destination_account_id']);
                            $journal = app(AccountingService::class)->createSimpleTransaction(
                                date: Carbon::parse($data['date']),
                                type: 'transfer',
                                amount: (float) $data['amount'],
                                sourceAccount: $record,
                                destinationAccount: $dest,
                                description: $data['description'],
                                source: JournalSource::Web
                            );

                            Notification::make()
                                ->title('Transfer Berhasil Dibukukan')
                                ->body('Transfer Rp '.number_format($data['amount'], 0, ',', '.')." dari {$record->name} ke {$dest->name} berhasil dicatat ({$journal->entry_number}).")
                                ->success()
                                ->send();
                        }),

                    Action::make('viewLedger')
                        ->label('Lihat Buku Besar (Mutasi)')
                        ->icon('heroicon-m-book-open')
                        ->color('gray')
                        ->url(fn (Account $record): string => '/admin/general-ledger-report'),

                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }
}
