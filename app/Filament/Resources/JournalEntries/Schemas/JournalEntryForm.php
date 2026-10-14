<?php

namespace App\Filament\Resources\JournalEntries\Schemas;

use App\Models\Account;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JournalEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Transaksi')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('entry_number')
                                ->label('Nomor Jurnal')
                                ->disabled()
                                ->placeholder('Dibuat otomatis oleh sistem')
                                ->dehydrated(false),

                            DatePicker::make('date')
                                ->label('Tanggal')
                                ->default(now())
                                ->required(),

                            TextInput::make('reference_number')
                                ->label('No. Referensi / Bukti')
                                ->placeholder('e.g. INV-001, Struk, dll.')
                                ->nullable(),

                            TextInput::make('description')
                                ->label('Keterangan / Deskripsi Transaksi')
                                ->required()
                                ->placeholder('e.g. Pembelian bahan baku, Pembayaran listrik kantor')
                                ->columnSpanFull(),

                            FileUpload::make('receipt_image')
                                ->label('Lampiran Foto Struk / Bukti Transaksi')
                                ->image()
                                ->disk('public')
                                ->directory('receipts/'.now()->format('Y/m'))
                                ->visibility('public')
                                ->maxSize(10240)
                                ->columnSpanFull(),
                        ]),
                    ]),

                Section::make('Rincian Baris Jurnal (Debit & Kredit)')
                    ->description('Pastikan total Debit dan total Kredit bernilai seimbang.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Select::make('account_id')
                                    ->label('Akun')
                                    ->options(fn () => Account::where('is_active', true)->whereNotNull('parent_id')->orderBy('code')->get()->pluck('formatted_code_and_name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(6),

                                TextInput::make('debit')
                                    ->label('Debit (Rp)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('credit')
                                    ->label('Kredit (Rp)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->required()
                                    ->columnSpan(3),

                                TextInput::make('memo')
                                    ->label('Catatan Memo Baris')
                                    ->placeholder('Opsional')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(12)
                            ->minItems(2)
                            ->defaultItems(2)
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_array($value) || count($value) < 2) {
                                        $fail('Jurnal harus memiliki minimal dua baris item.');

                                        return;
                                    }

                                    $totalDebit = 0.0;
                                    $totalCredit = 0.0;

                                    foreach ($value as $item) {
                                        $debit = (float) ($item['debit'] ?? 0);
                                        $credit = (float) ($item['credit'] ?? 0);

                                        if ($debit < 0 || $credit < 0) {
                                            $fail('Nilai Debit atau Kredit tidak boleh negatif.');

                                            return;
                                        }

                                        if ($debit > 0 && $credit > 0) {
                                            $fail('Satu baris jurnal tidak boleh memiliki nilai Debit dan Kredit sekaligus.');

                                            return;
                                        }

                                        if ($debit <= 0 && $credit <= 0) {
                                            $fail('Setiap baris jurnal harus memiliki nilai Debit atau Kredit lebih dari 0.');

                                            return;
                                        }

                                        $totalDebit += $debit;
                                        $totalCredit += $credit;
                                    }

                                    if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                                        $fail(sprintf(
                                            'Jurnal tidak seimbang! Total Debit (Rp %s) harus sama dengan Total Kredit (Rp %s).',
                                            number_format($totalDebit, 2, ',', '.'),
                                            number_format($totalCredit, 2, ',', '.')
                                        ));

                                        return;
                                    }

                                    if ($totalDebit <= 0) {
                                        $fail('Total nilai transaksi jurnal harus lebih besar dari 0.');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
