<?php

use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FinancialReportService;
use Database\Seeders\AccountSeeder;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
});

test('can dynamically create wallets from presets and custom inputs in onboarding wizard', function () {
    $service = app(AccountingService::class);

    $walletsData = [
        'preset_kas_tunai' => [
            'name' => 'Kas Tunai (Dompet Fisik)',
            'type' => 'Uang Tunai',
            'initial_balance' => 500000,
            'account_number' => null,
            'account_holder' => 'Budi Santoso',
        ],
        'preset_bca' => [
            'name' => 'Bank BCA',
            'type' => 'Rekening Bank',
            'initial_balance' => 15000000,
            'account_number' => '1234567890',
            'account_holder' => 'Budi Santoso',
        ],
        'custom_0' => [
            'name' => 'Bank Jenius BTPN',
            'type' => 'Rekening Bank',
            'initial_balance' => 2500000,
            'account_number' => '9001234567',
            'account_holder' => 'Budi Santoso',
        ],
    ];

    $service->completeDynamicWalletOnboarding($this->user, $walletsData, 'preset_bca');

    // Assert user wizard marked as completed
    $this->user->refresh();
    expect($this->user->wallet_setup_completed_at)->not->toBeNull();

    // Assert accounts created dynamically in DB
    $bca = Account::where('name', 'Bank BCA')->firstOrFail();
    expect($bca->is_active)->toBeTrue()
        ->and($bca->is_default)->toBeTrue()
        ->and($bca->account_number)->toBe('1234567890')
        ->and($bca->balance)->toBe(15000000.0);

    $jenius = Account::where('name', 'Bank Jenius BTPN')->firstOrFail();
    expect($jenius->is_active)->toBeTrue()
        ->and($jenius->balance)->toBe(2500000.0);

    // Assert initial balances created balanced journals to Equity (Modal Awal)
    $reportService = app(FinancialReportService::class);
    $balanceSheet = $reportService->getBalanceSheet(now());

    // Total Assets = 500k + 15M + 2.5M = 18M
    expect($balanceSheet['total_assets'])->toBe(18000000.0)
        ->and($balanceSheet['is_balanced'])->toBeTrue();
});

test('can adjust wallet balance surplus and deficit', function () {
    $service = app(AccountingService::class);

    // Create a wallet first
    $service->completeDynamicWalletOnboarding($this->user, [
        'preset_bca' => [
            'name' => 'Bank BCA',
            'type' => 'Rekening Bank',
            'initial_balance' => 1000000,
        ],
    ]);

    $bca = Account::where('name', 'Bank BCA')->firstOrFail();
    expect($bca->balance)->toBe(1000000.0);

    // Surplus adjustment: Actual physical balance is 1,200,000 (+200,000)
    $journalSurplus = $service->adjustBalance($bca, 1200000, 'Koreksi bunga / bonus');
    expect($journalSurplus)->not->toBeNull()
        ->and($bca->refresh()->balance)->toBe(1200000.0);

    // Deficit adjustment: Actual physical balance is 1,150,000 (-50,000)
    $journalDeficit = $service->adjustBalance($bca, 1150000, 'Koreksi biaya admin');
    expect($journalDeficit)->not->toBeNull()
        ->and($bca->refresh()->balance)->toBe(1150000.0);
});

test('default payment account prioritizes wallet marked as default', function () {
    $service = app(AccountingService::class);
    $service->completeDynamicWalletOnboarding($this->user, [
        'preset_gopay' => [
            'name' => 'E-Wallet GoPay',
            'type' => 'E-Wallet',
        ],
    ], 'preset_gopay');

    $gopay = Account::where('name', 'E-Wallet GoPay')->firstOrFail();

    $defaultAcc = $service->getDefaultPaymentAccount();
    expect($defaultAcc->id)->toBe($gopay->id)
        ->and($defaultAcc->name)->toContain('GoPay');
});

test('authenticated user can view wallets list in Filament', function () {
    $this->actingAs($this->user);

    $this->get('/admin/wallets')->assertSuccessful();
});
