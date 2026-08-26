<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FinancialReportService;
use Database\Seeders\AccountSeeder;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->accountingService = app(AccountingService::class);
    $this->reportService = app(FinancialReportService::class);

    $parent = Account::where('code', '1-10000')->first();
    $kas = Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);
    $bca = Account::firstOrCreate(['code' => '1-10002'], [
        'name' => 'Bank BCA',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);
    $modal = Account::where('code', '3-10001')->firstOrFail();
    $gaji = Account::where('code', '4-10001')->firstOrFail();
    $makanan = Account::where('code', '6-10001')->firstOrFail();
    $utilitas = Account::where('code', '6-10005')->firstOrFail();

    // 1. Modal Awal: Rp 50,000,000 ke Kas
    $this->accountingService->createJournalEntry([
        'date' => now()->startOfMonth(),
        'description' => 'Setoran Modal Awal',
    ], [
        ['account_id' => $kas->id, 'debit' => 50000000, 'credit' => 0],
        ['account_id' => $modal->id, 'debit' => 0, 'credit' => 50000000],
    ]);

    // 2. Gaji Masuk: Rp 15,000,000 ke BCA
    $this->accountingService->createSimpleTransaction(
        date: now(),
        type: 'income',
        amount: 15000000,
        sourceAccount: $gaji,
        destinationAccount: $bca,
        description: 'Gaji Bulanan'
    );

    // 3. Beban Makanan dari BCA: Rp 2,000,000
    $this->accountingService->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 2000000,
        sourceAccount: $bca,
        destinationAccount: $makanan,
        description: 'Makan & Groceries'
    );

    // 4. Beban Listrik dari Kas: Rp 1,000,000
    $this->accountingService->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 1000000,
        sourceAccount: $kas,
        destinationAccount: $utilitas,
        description: 'Listrik & Internet'
    );
});

test('calculates accurate Income Statement (Laba Rugi)', function () {
    $report = $this->reportService->getIncomeStatement(now()->startOfMonth(), now()->endOfMonth());

    expect($report['total_operating_revenue'])->toBe(15000000.0)
        ->and($report['total_operating_expenses'])->toBe(3000000.0)
        ->and($report['net_profit'])->toBe(12000000.0);
});

test('calculates accurate and balanced Balance Sheet (Neraca)', function () {
    $report = $this->reportService->getBalanceSheet(now()->endOfMonth());

    expect($report['total_assets'])->toBe(62000000.0)
        ->and($report['total_liabilities'])->toBe(0.0)
        ->and($report['total_equity'])->toBe(62000000.0) // 50jt modal + 12jt net profit
        ->and($report['total_liabilities_and_equity'])->toBe(62000000.0)
        ->and($report['is_balanced'])->toBeTrue();
});

test('calculates accurate Cash Flow Statement (Arus Kas)', function () {
    $report = $this->reportService->getCashFlow(now()->startOfMonth(), now()->endOfMonth());

    expect($report['operating_inflows'])->toBe(15000000.0)
        ->and($report['operating_outflows'])->toBe(3000000.0)
        ->and($report['net_operating_cashflow'])->toBe(12000000.0)
        ->and($report['financing_inflows'])->toBe(50000000.0)
        ->and($report['net_change_in_cash'])->toBe(62000000.0)
        ->and($report['ending_cash'])->toBe(62000000.0);
});

test('calculates accurate General Ledger (Buku Besar) with running balances', function () {
    $bca = Account::where('code', '1-10002')->firstOrFail();
    $report = $this->reportService->getGeneralLedger($bca, now()->startOfMonth(), now()->endOfMonth());

    expect($report['beginning_balance'])->toBe(0.0)
        ->and($report['transactions'])->toHaveCount(2)
        ->and($report['ending_balance'])->toBe(13000000.0);
});

test('calculates accurate and balanced Trial Balance (Neraca Saldo)', function () {
    $report = $this->reportService->getTrialBalance(now());

    expect($report['is_balanced'])->toBeTrue()
        ->and($report['rows'])->not->toBeEmpty()
        ->and($report['total_debit'])->toBe(65000000.0) // 50jt kas + 13jt bca + 2jt makan = 65jt
        ->and($report['total_credit'])->toBe(65000000.0);
});
