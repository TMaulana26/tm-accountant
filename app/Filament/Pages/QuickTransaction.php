<?php

namespace App\Filament\Pages;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class QuickTransaction extends Page
{
    protected string $view = 'filament.pages.quick-transaction';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Input Transaksi Cepat';

    protected static ?string $title = 'Input Transaksi Cepat';

    protected static ?int $navigationSort = 0;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'expense',
            'date' => now()->format('Y-m-d'),
            'amount' => null,
            'source_account_id' => Account::where('category', AccountCategory::CashAndBank)->where('code', '1-10001')->value('id') ?? Account::where('category', AccountCategory::CashAndBank)->value('id'),
            'destination_account_id' => Account::where('type', AccountType::Expense)->whereNotNull('parent_id')->value('id'),
            'description' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Form Input Cepat Transaksi')
                    ->description('Catat pengeluaran harian, pemasukan, atau transfer saldo secara instan tanpa perlu repot mengatur debit/kredit manual.')
                    ->schema([
                        Radio::make('type')
                            ->label('Jenis Transaksi')
                            ->options([
                                'expense' => '💸 Pengeluaran (Beban)',
                                'income' => '💰 Pemasukan (Pendapatan)',
                                'transfer' => '🔄 Transfer Antar Dompet/Bank',
                            ])
                            ->default('expense')
                            ->inline()
                            ->live()
                            ->required(),

                        Grid::make(2)->schema([
                            DatePicker::make('date')
                                ->label('Tanggal Transaksi')
                                ->default(now())
                                ->required(),

                            TextInput::make('amount')
                                ->label('Nominal Transaksi')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->placeholder('e.g. 50000'),

                            // SOURCE ACCOUNT
                            Select::make('source_account_id')
                                ->label(fn (Get $get): string => match ($get('type')) {
                                    'expense' => 'Sumber Dana (Rekening / Kas Pembayar)',
                                    'income' => 'Kategori Pemasukan (Akun Pendapatan)',
                                    'transfer' => 'Rekening / Dompet Asal',
                                    default => 'Akun Sumber',
                                })
                                ->options(function (Get $get) {
                                    $type = $get('type');
                                    if ($type === 'income') {
                                        return Account::where('type', AccountType::Revenue)
                                            ->whereNotNull('parent_id')
                                            ->orderBy('code')
                                            ->get()
                                            ->pluck('formatted_code_and_name', 'id');
                                    }

                                    // For Expense & Transfer: Cash/Bank accounts
                                    return Account::where('category', AccountCategory::CashAndBank)
                                        ->whereNotNull('parent_id')
                                        ->orderBy('code')
                                        ->get()
                                        ->pluck('formatted_code_and_name', 'id');
                                })
                                ->searchable()
                                ->required(),

                            // DESTINATION ACCOUNT
                            Select::make('destination_account_id')
                                ->label(fn (Get $get): string => match ($get('type')) {
                                    'expense' => 'Kategori Pengeluaran (Akun Beban)',
                                    'income' => 'Masuk ke (Rekening / Dompet Penerima)',
                                    'transfer' => 'Rekening / Dompet Tujuan',
                                    default => 'Akun Tujuan',
                                })
                                ->options(function (Get $get) {
                                    $type = $get('type');
                                    if ($type === 'expense') {
                                        return Account::where('type', AccountType::Expense)
                                            ->whereNotNull('parent_id')
                                            ->orderBy('code')
                                            ->get()
                                            ->pluck('formatted_code_and_name', 'id');
                                    }

                                    // For Income & Transfer: Cash/Bank accounts
                                    return Account::where('category', AccountCategory::CashAndBank)
                                        ->whereNotNull('parent_id')
                                        ->orderBy('code')
                                        ->get()
                                        ->pluck('formatted_code_and_name', 'id');
                                })
                                ->searchable()
                                ->required(),

                            TextInput::make('description')
                                ->label('Keterangan Transaksi')
                                ->placeholder('e.g. Beli makan siang nasi padang, Beli bensin, Gaji freelance')
                                ->required()
                                ->columnSpanFull(),

                            FileUpload::make('receipt_image')
                                ->label('Lampiran Foto Struk / Bukti Transaksi (Opsional)')
                                ->image()
                                ->disk('public')
                                ->directory('receipts/'.now()->format('Y/m'))
                                ->visibility('public')
                                ->maxSize(10240)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public function submit(AccountingService $accountingService): void
    {
        $formData = $this->form->getState();

        $type = $formData['type'];
        $amount = (float) $formData['amount'];
        $date = $formData['date'];
        $sourceAccountId = (int) $formData['source_account_id'];
        $destAccountId = (int) $formData['destination_account_id'];
        $description = trim($formData['description']);
        $receiptImage = $formData['receipt_image'] ?? null;

        try {
            $journal = $accountingService->createSimpleTransaction(
                date: $date,
                type: $type,
                amount: $amount,
                sourceAccount: $sourceAccountId,
                destinationAccount: $destAccountId,
                description: $description,
                source: JournalSource::Web,
                createdBy: auth()->id(),
                receiptImage: $receiptImage
            );

            Notification::make()
                ->title('Transaksi Berhasil Dicatat')
                ->body("Jurnal <b>{$journal->entry_number}</b> (Rp ".number_format($amount, 0, ',', '.').') telah dibukukan.')
                ->success()
                ->send();

            $this->form->fill([
                'type' => $type,
                'date' => now()->format('Y-m-d'),
                'amount' => null,
                'source_account_id' => $sourceAccountId,
                'destination_account_id' => $destAccountId,
                'description' => '',
            ]);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Mencatat Transaksi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
