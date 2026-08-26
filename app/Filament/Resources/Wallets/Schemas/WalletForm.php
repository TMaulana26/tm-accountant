<?php

namespace App\Filament\Resources\Wallets\Schemas;

use App\Enums\AccountCategory;
use App\Models\Account;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Dompet & Rekening')
                    ->description('Lengkapi identitas rekening bank, e-wallet, atau dompet tunai Anda')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Dompet / Bank')
                                ->placeholder('Contoh: Bank BCA, E-Wallet GoPay, Kas Dompet')
                                ->required()
                                ->maxLength(100),

                            TextInput::make('code')
                                ->label('Kode Akun Akuntansi')
                                ->placeholder('Contoh: 1-10012')
                                ->default(function () {
                                    $last = Account::where('category', AccountCategory::CashAndBank)->whereNotNull('parent_id')->orderByDesc('code')->value('code');
                                    if ($last && preg_match('/^1-(\d+)$/', $last, $m)) {
                                        return '1-'.((int) $m[1] + 1);
                                    }

                                    return '1-10099';
                                })
                                ->required()
                                ->unique(Account::class, 'code', ignoreRecord: true),

                            TextInput::make('account_number')
                                ->label('Nomor Rekening / No. HP E-Wallet')
                                ->placeholder('Contoh: 1234567890 atau 08123456789')
                                ->maxLength(50),

                            TextInput::make('account_holder')
                                ->label('Atas Nama Pemilik')
                                ->placeholder('Nama pemilik rekening')
                                ->maxLength(100),

                            TextInput::make('initial_balance')
                                ->label('Saldo Awal Saat Ini')
                                ->numeric()
                                ->prefix('Rp')
                                ->placeholder('0')
                                ->default(0)
                                ->visibleOn('create')
                                ->dehydrated(false)
                                ->helperText('Sistem otomatis membukukan saldo awal ini berpasangan ke Modal Awal.'),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('is_default')
                                ->label('Jadikan Dompet Utama (Default)')
                                ->helperText('Dipakai otomatis saat input transaksi di Telegram tanpa menyebutkan nama bank/dompet.')
                                ->default(false),

                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->helperText('Dompet nonaktif akan disembunyikan dari pilihan transaksi.')
                                ->default(true),
                        ]),

                        Textarea::make('description')
                            ->label('Catatan Tambahan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
