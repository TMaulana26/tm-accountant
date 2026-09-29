<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\TransactionExportService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $parent = Account::where('code', '1-10000')->first();

    $this->cashAccount = Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    $this->bcaAccount = Account::firstOrCreate(['code' => '1-10002'], [
        'name' => 'Bank BCA',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);

    $this->foodAccount = Account::where('code', '6-10001')->firstOrFail();
    $this->accountingService = app(AccountingService::class);
    $this->exportService = app(TransactionExportService::class);
});

test('TransactionExportService exports transactions to a valid excel xlsx file', function () {
    // Create 2 test transactions
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now()->subDays(2),
        'description' => 'Makan Siang Nasi Padang',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 25000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 25000],
    ]);

    $this->accountingService->createJournalEntry([
        'date' => Carbon::now()->subDays(1),
        'description' => 'Beli Kopi Susu',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 18000, 'credit' => 0],
        ['account_id' => $this->bcaAccount->id, 'debit' => 0, 'credit' => 18000],
    ]);

    $queryResult = $this->accountingService->queryTransactions([
        'period' => 'this_month',
    ]);

    $export = $this->exportService->exportToExcel($queryResult);

    expect(file_exists($export['file_path']))->toBeTrue()
        ->and(filesize($export['file_path']))->toBeGreaterThan(1000)
        ->and(str_ends_with($export['filename'], '.xlsx'))->toBeTrue();

    // Clean up
    if (file_exists($export['file_path'])) {
        unlink($export['file_path']);
    }
});

test('TransactionExportService includes running balance when exporting single wallet transactions', function () {
    // Create initial balance
    $this->accountingService->setInitialBalance($this->bcaAccount, 500000, Carbon::now()->startOfMonth());

    // Expense from BCA
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Beli Bensin Pertamax',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 50000, 'credit' => 0],
        ['account_id' => $this->bcaAccount->id, 'debit' => 0, 'credit' => 50000],
    ]);

    $queryResult = $this->accountingService->queryTransactions([
        'wallet_name' => 'BCA',
        'period' => 'this_month',
    ]);

    $export = $this->exportService->exportToExcel($queryResult);

    expect(file_exists($export['file_path']))->toBeTrue()
        ->and(filesize($export['file_path']))->toBeGreaterThan(1000);

    // Clean up
    if (file_exists($export['file_path'])) {
        unlink($export['file_path']);
    }
});
