<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Database\Seeders\AccountSeeder;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->service = app(AccountingService::class);
    $parent = Account::where('code', '1-10000')->first();
    Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_system' => false,
        'is_active' => true,
        'is_default' => true,
    ]);
    Account::firstOrCreate(['code' => '1-10002'], [
        'name' => 'Bank BCA',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_system' => false,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '1-10003'], [
        'name' => 'Bank Mandiri',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_system' => false,
        'is_active' => true,
    ]);
    Account::firstOrCreate(['code' => '1-10004'], [
        'name' => 'E-Wallet GoPay',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_system' => false,
        'is_active' => true,
    ]);
});

test('can create a balanced multi-line journal entry', function () {
    $cashAccount = Account::where('code', '1-10001')->firstOrFail();
    $foodAccount = Account::where('code', '6-10001')->firstOrFail();

    $entry = $this->service->createJournalEntry([
        'date' => now(),
        'description' => 'Beli sarapan pagi',
        'source' => JournalSource::Web,
    ], [
        ['account_id' => $foodAccount->id, 'debit' => 35000, 'credit' => 0, 'memo' => 'Nasi uduk'],
        ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 35000, 'memo' => 'Tunai'],
    ]);

    expect($entry)->toBeInstanceOf(JournalEntry::class)
        ->and($entry->entry_number)->toStartWith('JE-')
        ->and($entry->isBalanced())->toBeTrue()
        ->and($entry->total_debit)->toBe(35000.0)
        ->and($entry->total_credit)->toBe(35000.0);

    expect($foodAccount->fresh()->balance)->toBe(35000.0);
    expect($cashAccount->fresh()->balance)->toBe(-35000.0);
});

test('throws exception when creating unbalanced journal entry', function () {
    $cashAccount = Account::where('code', '1-10001')->firstOrFail();
    $foodAccount = Account::where('code', '6-10001')->firstOrFail();

    $this->service->createJournalEntry([
        'date' => now(),
        'description' => 'Unbalanced test',
    ], [
        ['account_id' => $foodAccount->id, 'debit' => 50000, 'credit' => 0],
        ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => 40000],
    ]);
})->throws(InvalidArgumentException::class);

test('can create simple expense transaction', function () {
    $bca = Account::where('code', '1-10002')->firstOrFail();
    $transport = Account::where('code', '6-10003')->firstOrFail();

    $journal = $this->service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 50000,
        sourceAccount: $bca,
        destinationAccount: $transport,
        description: 'Isi bensin Pertamax',
        source: JournalSource::Telegram
    );

    expect($journal->isBalanced())->toBeTrue()
        ->and($journal->source)->toBe(JournalSource::Telegram)
        ->and($transport->fresh()->balance)->toBe(50000.0)
        ->and($bca->fresh()->balance)->toBe(-50000.0);
});

test('can create simple income transaction', function () {
    $mandiri = Account::where('code', '1-10003')->firstOrFail();
    $salary = Account::where('code', '4-10001')->firstOrFail();

    $journal = $this->service->createSimpleTransaction(
        date: now(),
        type: 'income',
        amount: 10000000,
        sourceAccount: $salary,
        destinationAccount: $mandiri,
        description: 'Gaji bulanan',
        source: JournalSource::Web
    );

    expect($journal->isBalanced())->toBeTrue()
        ->and($mandiri->fresh()->balance)->toBe(10000000.0)
        ->and($salary->fresh()->balance)->toBe(10000000.0);
});

test('can create transfer between cash/bank accounts', function () {
    $bca = Account::where('code', '1-10002')->firstOrFail();
    $gopay = Account::where('code', '1-10004')->firstOrFail();

    $journal = $this->service->createSimpleTransaction(
        date: now(),
        type: 'transfer',
        amount: 250000,
        sourceAccount: $bca,
        destinationAccount: $gopay,
        description: 'Top up GoPay dari BCA',
        source: JournalSource::Web
    );

    expect($journal->isBalanced())->toBeTrue()
        ->and($gopay->fresh()->balance)->toBe(250000.0)
        ->and($bca->fresh()->balance)->toBe(-250000.0);
});

test('can safely revert journal entry', function () {
    $cash = Account::where('code', '1-10001')->firstOrFail();
    $food = Account::where('code', '6-10001')->firstOrFail();

    $journal = $this->service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 25000,
        sourceAccount: $cash,
        destinationAccount: $food,
        description: 'Beli kopi'
    );

    expect(JournalEntry::count())->toBe(1);

    $reverted = $this->service->revertJournalEntry($journal);

    expect($reverted)->toBeTrue()
        ->and(JournalEntry::count())->toBe(0)
        ->and($food->fresh()->balance)->toBe(0.0)
        ->and($cash->fresh()->balance)->toBe(0.0);
});

test('dynamically finds or creates expense account if not existing', function () {
    $existing = $this->service->findOrCreateExpenseAccount('Makanan & Minuman');
    expect($existing->code)->toBe('6-10001');

    $newAccount = $this->service->findOrCreateExpenseAccount('Beban Hobi Kamera & Lensa');
    expect($newAccount)->toBeInstanceOf(Account::class)
        ->and($newAccount->type)->toBe(AccountType::Expense)
        ->and($newAccount->category)->toBe(AccountCategory::OperatingExpense)
        ->and($newAccount->name)->toBe('Beban Hobi Kamera & Lensa');
});
