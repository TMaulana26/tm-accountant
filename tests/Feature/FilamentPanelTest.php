<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Filament\Pages\QuickTransaction;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountSeeder;
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
