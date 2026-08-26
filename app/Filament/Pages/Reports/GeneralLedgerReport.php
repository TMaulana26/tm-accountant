<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use App\Services\Accounting\FinancialReportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GeneralLedgerReport extends Page
{
    protected string $view = 'filament.pages.reports.general-ledger-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Buku Besar';

    protected static ?string $title = 'Laporan Buku Besar (General Ledger)';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        $defaultAccount = Account::whereNotNull('parent_id')->orderBy('code')->value('id');

        $this->form->fill([
            'account_id' => $defaultAccount,
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
                Section::make('Filter Akun & Periode Buku Besar')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('account_id')
                                ->label('Pilih Akun')
                                ->options(fn () => Account::where('is_active', true)->whereNotNull('parent_id')->orderBy('code')->get()->pluck('formatted_code_and_name', 'id'))
                                ->searchable()
                                ->live()
                                ->required()
                                ->columnSpan(2),

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

    public function getReport(): ?array
    {
        $accountId = $this->data['account_id'] ?? null;
        if (! $accountId) {
            return null;
        }

        $start = $this->data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $this->data['end_date'] ?? now()->endOfMonth()->toDateString();

        return app(FinancialReportService::class)->getGeneralLedger((int) $accountId, $start, $end);
    }
}
