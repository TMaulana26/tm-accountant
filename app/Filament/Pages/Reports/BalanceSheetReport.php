<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\FinancialReportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class BalanceSheetReport extends Page
{
    protected string $view = 'filament.pages.reports.balance-sheet-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Neraca';

    protected static ?string $title = 'Laporan Neraca (Balance Sheet)';

    protected static ?int $navigationSort = 2;

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
            'as_of_date' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filter Tanggal Neraca')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('as_of_date')
                                ->label('Posisi Saldo Per Tanggal')
                                ->default(now())
                                ->live()
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function getReport(): array
    {
        $asOf = $this->data['as_of_date'] ?? now()->toDateString();

        return app(FinancialReportService::class)->getBalanceSheet($asOf);
    }
}
