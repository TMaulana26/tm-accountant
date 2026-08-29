<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\FinancialReportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class CashFlowReport extends Page
{
    protected string $view = 'filament.pages.reports.cash-flow-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Arus Kas';

    protected static ?string $title = 'Laporan Arus Kas (Cash Flow Statement)';

    protected static ?int $navigationSort = 3;

    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    #[On('echo:accounting,WalletBalanceUpdated')]
    public function refreshReport(): void
    {
        // Re-renders report automatically
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'preset' => 'this_month',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->endOfMonth()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filter Periode Laporan Arus Kas')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('preset')
                                ->label('Pilihan Periode')
                                ->options([
                                    'this_month' => 'Bulan Ini',
                                    'last_month' => 'Bulan Lalu',
                                    'this_year' => 'Tahun Ini',
                                    'custom' => 'Kustom Rentang Tanggal',
                                ])
                                ->default('this_month')
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    switch ($state) {
                                        case 'this_month':
                                            $set('start_date', now()->startOfMonth()->toDateString());
                                            $set('end_date', now()->endOfMonth()->toDateString());
                                            break;
                                        case 'last_month':
                                            $set('start_date', now()->subMonth()->startOfMonth()->toDateString());
                                            $set('end_date', now()->subMonth()->endOfMonth()->toDateString());
                                            break;
                                        case 'this_year':
                                            $set('start_date', now()->startOfYear()->toDateString());
                                            $set('end_date', now()->endOfYear()->toDateString());
                                            break;
                                    }
                                }),

                            DatePicker::make('start_date')
                                ->label('Tanggal Awal')
                                ->live()
                                ->required(),

                            DatePicker::make('end_date')
                                ->label('Tanggal Akhir')
                                ->live()
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function getReport(): array
    {
        $start = $this->data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $this->data['end_date'] ?? now()->endOfMonth()->toDateString();

        return app(FinancialReportService::class)->getCashFlow($start, $end);
    }
}
