<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Filament\Pages\QuickTransaction;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Filament\Actions\DeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create();
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/admin');
    $response->assertRedirect('/admin/login');
});

test('authenticated user can access dashboard and report pages', function () {
    $this->actingAs($this->user);

    $this->get('/admin')->assertSuccessful();
    $this->get('/admin/accounts')->assertSuccessful();
    $this->get('/admin/journal-entries')->assertSuccessful();
    $this->get('/admin/quick-transaction')->assertSuccessful();
    $this->get('/admin/income-statement-report')->assertSuccessful();
    $this->get('/admin/balance-sheet-report')->assertSuccessful();
    $this->get('/admin/cash-flow-report')->assertSuccessful();
    $this->get('/admin/general-ledger-report')->assertSuccessful();
    $this->get('/admin/trial-balance-report')->assertSuccessful();
    $this->get('/admin/user-guide')->assertSuccessful();
    $this->get('/admin/wallets')->assertSuccessful();
    $this->get('/admin/activity-log-page')->assertSuccessful();
    $this->get('/admin/telegram-chat-history-page')->assertSuccessful();
});

test('can submit quick transaction from QuickTransaction page', function () {
    $this->actingAs($this->user);

    $parent = Account::where('code', '1-10000')->first();
    $kas = Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
        'is_default' => true,
    ]);
    $food = Account::where('code', '6-10001')->firstOrFail();

    Livewire::test(QuickTransaction::class)
        ->fillForm([
            'type' => 'expense',
            'date' => now()->toDateString(),
            'amount' => 45000,
            'source_account_id' => $kas->id,
            'destination_account_id' => $food->id,
            'description' => 'Makan malam sate ayam',
        ])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(JournalEntry::count())->toBe(1);

    $entry = JournalEntry::first();
    expect($entry->description)->toBe('Makan malam sate ayam')
        ->and($entry->total_debit)->toBe(45000.0);
});

test('QuickTransaction rejects self-transfer to the same account', function () {
    $this->actingAs($this->user);

    $parent = Account::where('code', '1-10000')->first();
    $kas = Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    Livewire::test(QuickTransaction::class)
        ->fillForm([
            'type' => 'transfer',
            'date' => now()->toDateString(),
            'amount' => 50000,
            'source_account_id' => $kas->id,
            'destination_account_id' => $kas->id,
            'description' => 'Transfer ke diri sendiri',
        ])
        ->call('submit')
        ->assertHasFormErrors(['destination_account_id']);

    expect(JournalEntry::count())->toBe(0);
});

test('CreateJournalEntry enforces balanced double-entry lines and sets created_by', function () {
    $this->actingAs($this->user);

    $parent = Account::where('code', '1-10000')->first();
    $kas = Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);
    $food = Account::where('code', '6-10001')->firstOrFail();

    // 1. Unbalanced entry should fail validation
    Livewire::test(CreateJournalEntry::class)
        ->fillForm([
            'date' => now()->toDateString(),
            'description' => 'Jurnal Tidak Seimbang',
            'items' => [
                ['account_id' => $food->id, 'debit' => 50000, 'credit' => 0, 'memo' => 'Debit'],
                ['account_id' => $kas->id, 'debit' => 0, 'credit' => 30000, 'memo' => 'Kredit'],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['items']);

    expect(JournalEntry::count())->toBe(0);

    // 2. Balanced entry should succeed and set created_by
    Livewire::test(CreateJournalEntry::class)
        ->fillForm([
            'date' => now()->toDateString(),
            'description' => 'Jurnal Manual Seimbang',
            'items' => [
                ['account_id' => $food->id, 'debit' => 50000, 'credit' => 0, 'memo' => 'Debit'],
                ['account_id' => $kas->id, 'debit' => 0, 'credit' => 50000, 'memo' => 'Kredit'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = JournalEntry::first();
    expect($entry)->not->toBeNull()
        ->and($entry->created_by)->toBe($this->user->id)
        ->and($entry->isBalanced())->toBeTrue();
});

test('EditAccount hides DeleteAction for system accounts and accounts with transactions', function () {
    $this->actingAs($this->user);

    $systemAccount = Account::where('code', '3-10001')->firstOrFail();
    expect($systemAccount->canBeDeleted())->toBeFalse();

    Livewire::test(EditAccount::class, ['record' => $systemAccount->id])
        ->assertActionHidden(DeleteAction::class);

    $customAccount = Account::create([
        'code' => '6-19999',
        'name' => 'Akun Kustom Kosong',
        'type' => AccountType::Expense,
        'category' => AccountCategory::OperatingExpense,
        'parent_id' => Account::where('code', '6-10000')->value('id'),
        'is_system' => false,
        'is_active' => true,
    ]);

    expect($customAccount->canBeDeleted())->toBeTrue();

    Livewire::test(EditAccount::class, ['record' => $customAccount->id])
        ->assertActionVisible(DeleteAction::class);
});
