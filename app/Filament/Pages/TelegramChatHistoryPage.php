<?php

namespace App\Filament\Pages;

use App\Enums\TelegramMessageStatus;
use App\Filament\Widgets\TelegramChatStatsWidget;
use App\Models\TelegramMessage;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class TelegramChatHistoryPage extends Page
{
    protected string $view = 'filament.pages.telegram-chat-history-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & Riwayat';

    protected static ?string $navigationLabel = 'Riwayat Chat Bot';

    protected static ?string $title = 'Riwayat Percakapan Telegram Bot';

    protected static ?int $navigationSort = 2;

    #[On('refresh-telegram-messages')]
    #[On('refresh-transactions')]
    #[On('echo:accounting,TelegramMessageLogged')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshChat(): void
    {
        // Re-renders chat history live stream automatically
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TelegramChatStatsWidget::class,
        ];
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'period_preset' => 'all',
            'start_date' => null,
            'end_date' => null,
            'intent' => 'all',
            'status' => 'all',
            'search' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filter Percakapan Telegram')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'md' => 3,
                            'xl' => 6,
                        ])->schema([
                            Select::make('period_preset')
                                ->label('Periode Waktu')
                                ->options([
                                    'all' => 'Semua Waktu',
                                    'today' => 'Hari Ini',
                                    'yesterday' => 'Kemarin',
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
                                    } elseif ($state === 'yesterday') {
                                        $set('start_date', now()->subDay()->format('Y-m-d'));
                                        $set('end_date', now()->subDay()->format('Y-m-d'));
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

                            DatePicker::make('start_date')
                                ->label('Dari Tanggal')
                                ->live(),

                            DatePicker::make('end_date')
                                ->label('Sampai Tanggal')
                                ->live(),

                            Select::make('intent')
                                ->label('Kategori Intent')
                                ->options([
                                    'all' => 'Semua Intent',
                                    'record_expense' => '💸 Pengeluaran',
                                    'record_income' => '💰 Pemasukan',
                                    'record_transfer' => '🔄 Transfer Saldo',
                                    'check_balance' => '💳 Cek Saldo',
                                    'financial_report' => '📊 Laporan Keuangan',
                                    'out_of_topic' => '❓ Di Luar Topik',
                                ])
                                ->default('all')
                                ->live(),

                            Select::make('status')
                                ->label('Status Transaksi')
                                ->options([
                                    'all' => 'Semua Status',
                                    'processed' => '✓ Sukses Diproses',
                                    'reverted' => '↩️ Dibatalkan (Undo)',
                                    'failed' => '❌ Gagal',
                                    'pending' => '⏳ Pending',
                                ])
                                ->default('all')
                                ->live(),

                            TextInput::make('search')
                                ->label('Cari Percakapan')
                                ->placeholder('Ketik kata kunci...')
                                ->live(debounce: 400),
                        ]),
                    ]),
            ]);
    }

    public function getMessages()
    {
        $query = TelegramMessage::query()
            ->with(['journalEntry'])
            ->orderBy('created_at', 'asc');

        $intent = $this->data['intent'] ?? 'all';
        if ($intent && $intent !== 'all') {
            $query->where('intent', $intent);
        }

        $status = $this->data['status'] ?? 'all';
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = trim($this->data['search'] ?? '');
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('raw_text', 'like', "%{$search}%")
                    ->orWhere('ai_response', 'like', "%{$search}%");
            });
        }

        $startDate = $this->data['start_date'] ?? null;
        $endDate = $this->data['end_date'] ?? null;

        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        return $query->get()->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));
    }

    public function formatChatResponse(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // 1. Convert Markdown bold **text** or __text__ to <strong>text</strong>
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/s', '<strong>$1</strong>', $text);

        // 2. Convert Markdown bullet list `- ` to `• `
        $text = preg_replace('/^\s*[-*]\s+/m', '• ', $text);

        // 3. Convert markdown inline code `code` to <code>code</code>
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        return nl2br($text);
    }

    public function getStatistics(): array
    {
        return [
            'total' => TelegramMessage::count(),
            'processed' => TelegramMessage::where('status', TelegramMessageStatus::Processed)->count(),
            'ocr' => TelegramMessage::whereNotNull('receipt_image')->count(),
            'reverted' => TelegramMessage::where('status', TelegramMessageStatus::Reverted)->count(),
        ];
    }
}
