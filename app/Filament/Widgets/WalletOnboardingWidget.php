<?php

namespace App\Filament\Widgets;

use App\Services\Accounting\AccountingService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

class WalletOnboardingWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.wallet-onboarding-widget';

    public static array $presets = [
        'kas_tunai' => ['name' => 'Kas Tunai (Dompet Fisik)', 'type' => 'Uang Tunai'],
        'bca' => ['name' => 'Bank BCA', 'type' => 'Rekening Bank'],
        'mandiri' => ['name' => 'Bank Mandiri', 'type' => 'Rekening Bank'],
        'bri' => ['name' => 'Bank BRI', 'type' => 'Rekening Bank'],
        'bni' => ['name' => 'Bank BNI', 'type' => 'Rekening Bank'],
        'jago' => ['name' => 'Bank Jago / Digital', 'type' => 'Rekening Bank'],
        'seabank' => ['name' => 'Bank SeaBank', 'type' => 'Rekening Bank'],
        'gopay' => ['name' => 'E-Wallet GoPay', 'type' => 'E-Wallet'],
        'ovo' => ['name' => 'E-Wallet OVO', 'type' => 'E-Wallet'],
        'dana' => ['name' => 'E-Wallet DANA', 'type' => 'E-Wallet'],
        'shopeepay' => ['name' => 'E-Wallet ShopeePay', 'type' => 'E-Wallet'],
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ! $user->wallet_setup_completed_at;
    }

    public function startWizardAction(): Action
    {
        return Action::make('startWizard')
            ->label('Mulai Setup Dompet (3 Langkah)')
            ->icon('heroicon-m-sparkles')
            ->size('lg')
            ->color('primary')
            ->steps([
                Step::make('Pilih & Tambah Dompet')
                    ->description('Centang rekomendasi bank/e-wallet atau tambah dompet custom Anda')
                    ->schema([
                        Section::make('Rekomendasi Cepat Bank & E-Wallet')
                            ->description('Centang rekening dan e-wallet yang Anda gunakan sehari-hari')
                            ->schema([
                                CheckboxList::make('selected_presets')
                                    ->label('Daftar Preset Dompet')
                                    ->options(function () {
                                        $options = [];
                                        foreach (static::$presets as $key => $item) {
                                            $options[$key] = "[{$item['type']}] {$item['name']}";
                                        }

                                        return $options;
                                    })
                                    ->default(['kas_tunai', 'bca', 'gopay'])
                                    ->columns(2)
                                    ->live(),
                            ]),

                        Section::make('Tambah Dompet / Rekening Lainnya (Custom)')
                            ->description('Tambahkan bank lain, e-wallet, atau tabungan khusus yang belum ada di daftar atas')
                            ->schema([
                                Repeater::make('custom_wallets')
                                    ->label('Dompet Custom')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nama Dompet / Bank')
                                            ->placeholder('Contoh: Bank Jenius, Tabungan Bisnis')
                                            ->required(),

                                        Select::make('type')
                                            ->label('Tipe Dompet')
                                            ->options([
                                                'Uang Tunai' => 'Uang Tunai',
                                                'Rekening Bank' => 'Rekening Bank',
                                                'E-Wallet' => 'E-Wallet',
                                            ])
                                            ->default('Rekening Bank')
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('+ Tambah Dompet Lain')
                                    ->live(),
                            ]),
                    ]),

                Step::make('Saldo Awal & No. Rekening')
                    ->description('Masukkan saldo uang riil Anda saat ini & nomor rekening')
                    ->schema(function (Get $get) {
                        $selectedPresets = (array) ($get('selected_presets') ?? []);
                        $customWallets = (array) ($get('custom_wallets') ?? []);

                        if (empty($selectedPresets) && empty($customWallets)) {
                            return [
                                Section::make('Pemberitahuan')
                                    ->description('Silakan kembali ke Langkah 1 untuk memilih minimal 1 dompet.')
                                    ->schema([]),
                            ];
                        }

                        $components = [];

                        // 1. Render Preset Sections
                        foreach ($selectedPresets as $presetKey) {
                            if (! isset(static::$presets[$presetKey])) {
                                continue;
                            }
                            $item = static::$presets[$presetKey];

                            $components[] = Section::make("[{$item['type']}] {$item['name']}")
                                ->compact()
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make("wallet_details.preset_{$presetKey}.initial_balance")
                                            ->label('Saldo Awal Saat Ini')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->placeholder('0')
                                            ->default(0)
                                            ->helperText('Saldo riil rekening ini sekarang.'),

                                        TextInput::make("wallet_details.preset_{$presetKey}.account_number")
                                            ->label('Nomor Rekening / HP')
                                            ->placeholder('Nomor rekening / No HP'),

                                        TextInput::make("wallet_details.preset_{$presetKey}.account_holder")
                                            ->label('Atas Nama Pemilik')
                                            ->placeholder('Nama pemilik'),
                                    ]),
                                ]);
                        }

                        // 2. Render Custom Wallet Sections
                        foreach ($customWallets as $index => $custom) {
                            $customName = trim($custom['name'] ?? '');
                            if (empty($customName)) {
                                continue;
                            }
                            $customType = $custom['type'] ?? 'Rekening Bank';

                            $components[] = Section::make("[{$customType}] {$customName} (Custom)")
                                ->compact()
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make("wallet_details.custom_{$index}.initial_balance")
                                            ->label('Saldo Awal Saat Ini')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->placeholder('0')
                                            ->default(0)
                                            ->helperText('Saldo riil rekening ini sekarang.'),

                                        TextInput::make("wallet_details.custom_{$index}.account_number")
                                            ->label('Nomor Rekening / HP')
                                            ->placeholder('Nomor rekening / No HP'),

                                        TextInput::make("wallet_details.custom_{$index}.account_holder")
                                            ->label('Atas Nama Pemilik')
                                            ->placeholder('Nama pemilik'),
                                    ]),
                                ]);
                        }

                        return $components;
                    }),

                Step::make('Pilih Dompet Utama (Default)')
                    ->description('Dompet utama otomatis dipakai saat chat transaksi di Telegram')
                    ->schema(function (Get $get) {
                        $selectedPresets = (array) ($get('selected_presets') ?? []);
                        $customWallets = (array) ($get('custom_wallets') ?? []);

                        $options = [];
                        foreach ($selectedPresets as $presetKey) {
                            if (isset(static::$presets[$presetKey])) {
                                $options["preset_{$presetKey}"] = static::$presets[$presetKey]['name'];
                            }
                        }
                        foreach ($customWallets as $index => $custom) {
                            $name = trim($custom['name'] ?? '');
                            if (! empty($name)) {
                                $options["custom_{$index}"] = $name;
                            }
                        }

                        if (empty($options)) {
                            $options['default'] = 'Kas Tunai (Dompet Fisik)';
                        }

                        return [
                            Radio::make('default_wallet_key')
                                ->label('Pilih Dompet Utama')
                                ->options($options)
                                ->default(array_key_first($options))
                                ->required()
                                ->helperText('Jika Anda chat di Telegram seperti "beli telur 25k", saldo dompet inilah yang otomatis dipotong.'),
                        ];
                    }),
            ])
            ->action(function (array $data) {
                $user = auth()->user();
                $selectedPresets = (array) ($data['selected_presets'] ?? []);
                $customWallets = (array) ($data['custom_wallets'] ?? []);
                $walletDetails = (array) ($data['wallet_details'] ?? []);
                $defaultKey = $data['default_wallet_key'] ?? null;

                $walletsToCreate = [];

                // 1. Process presets
                foreach ($selectedPresets as $presetKey) {
                    if (! isset(static::$presets[$presetKey])) {
                        continue;
                    }
                    $preset = static::$presets[$presetKey];
                    $details = $walletDetails["preset_{$presetKey}"] ?? [];

                    $walletsToCreate["preset_{$presetKey}"] = [
                        'name' => $preset['name'],
                        'type' => $preset['type'],
                        'initial_balance' => (float) ($details['initial_balance'] ?? 0),
                        'account_number' => $details['account_number'] ?? null,
                        'account_holder' => $details['account_holder'] ?? null,
                    ];
                }

                // 2. Process custom wallets
                foreach ($customWallets as $index => $custom) {
                    $customName = trim($custom['name'] ?? '');
                    if (empty($customName)) {
                        continue;
                    }
                    $details = $walletDetails["custom_{$index}"] ?? [];

                    $walletsToCreate["custom_{$index}"] = [
                        'name' => $customName,
                        'type' => $custom['type'] ?? 'Rekening Bank',
                        'initial_balance' => (float) ($details['initial_balance'] ?? 0),
                        'account_number' => $details['account_number'] ?? null,
                        'account_holder' => $details['account_holder'] ?? null,
                    ];
                }

                app(AccountingService::class)->completeDynamicWalletOnboarding(
                    user: $user,
                    walletsData: $walletsToCreate,
                    defaultWalletKey: $defaultKey
                );

                Notification::make()
                    ->title('Setup Dompet Berhasil Selesai! 🎉')
                    ->body('Dompet Anda telah dibuat dan saldo awal berhasil dibukukan ke Modal Awal.')
                    ->success()
                    ->send();

                $this->redirect('/admin');
            });
    }

    public function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('Nanti Saja / Lewati')
            ->color('gray')
            ->action(function () {
                auth()->user()?->update(['wallet_setup_completed_at' => now()]);
                Notification::make()
                    ->title('Setup Wizard Disembunyikan')
                    ->body('Anda bisa membuka kembali Wizard ini kapan saja melalui menu Kelola Dompet & Rekening.')
                    ->info()
                    ->send();

                $this->redirect('/admin');
            });
    }
}
