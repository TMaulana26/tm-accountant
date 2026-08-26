<?php

namespace App\Filament\Pages;

use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page
{
    protected string $view = 'filament.pages.activity-log-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & Riwayat';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $title = 'Log Aktivitas Sistem (Audit Trail)';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'period_preset' => 'all',
            'start_date' => null,
            'end_date' => null,
            'log_name' => 'all',
            'event' => 'all',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filter Log Aktivitas')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('period_preset')
                                ->label('Periode Waktu')
                                ->options([
                                    'all' => 'Semua Waktu',
                                    'today' => 'Hari Ini',
                                    'last_7_days' => '7 Hari Terakhir',
                                    'this_month' => 'Bulan Ini',
                                    'custom' => 'Kustom (Pilih Tanggal)',
                                ])
                                ->default('all')
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state === 'today') {
                                        $set('start_date', now()->format('Y-m-d'));
                                        $set('end_date', now()->format('Y-m-d'));
                                    } elseif ($state === 'last_7_days') {
                                        $set('start_date', now()->subDays(6)->format('Y-m-d'));
                                        $set('end_date', now()->format('Y-m-d'));
                                    } elseif ($state === 'this_month') {
                                        $set('start_date', now()->startOfMonth()->format('Y-m-d'));
                                        $set('end_date', now()->endOfMonth()->format('Y-m-d'));
                                    } elseif ($state === 'all') {
                                        $set('start_date', null);
                                        $set('end_date', null);
                                    }
                                }),

                            Select::make('log_name')
                                ->label('Kategori Log')
                                ->options([
                                    'all' => 'Semua Kategori',
                                    'transaksi_jurnal' => '🧾 Transaksi Jurnal',
                                    'dompet_rekening' => '👛 Dompet & Rekening',
                                    'pengguna_autentikasi' => '👤 Pengguna & Auth',
                                    'telegram_bot' => '🤖 Telegram Bot',
                                ])
                                ->default('all')
                                ->live(),

                            Select::make('event')
                                ->label('Tipe Aksi')
                                ->options([
                                    'all' => 'Semua Aksi',
                                    'created' => 'Dibuat (Created)',
                                    'updated' => 'Diubah (Updated)',
                                    'deleted' => 'Dihapus (Deleted)',
                                    'undo' => 'Dibatalkan (Undo)',
                                    'adjustment' => 'Penyesuaian Saldo',
                                ])
                                ->default('all')
                                ->live(),

                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('start_date')
                                        ->label('Dari')
                                        ->live(),
                                    DatePicker::make('end_date')
                                        ->label('Sampai')
                                        ->live(),
                                ])
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }

    public function getActivities()
    {
        $query = Activity::query()->with(['causer'])->latest('id');

        $logName = $this->data['log_name'] ?? 'all';
        if ($logName && $logName !== 'all') {
            $query->where('log_name', $logName);
        }

        $event = $this->data['event'] ?? 'all';
        if ($event && $event !== 'all') {
            $query->where('event', $event);
        }

        $startDate = $this->data['start_date'] ?? null;
        $endDate = $this->data['end_date'] ?? null;

        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query->paginate(30);
    }

    public function getStatistics(): array
    {
        return [
            'total' => Activity::count(),
            'transaksi' => Activity::where('log_name', 'transaksi_jurnal')->count(),
            'dompet' => Activity::where('log_name', 'dompet_rekening')->count(),
            'bot' => Activity::where('log_name', 'telegram_bot')->count(),
        ];
    }
}
