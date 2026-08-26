<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Filament\Pages\ActivityLogPage;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\AccountSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
});

test('logs activity when journal entry is created and reverted', function () {
    $service = app(AccountingService::class);

    $kas = Account::where('code', '1-10001')->first() ?? Account::create([
        'code' => '1-10001',
        'name' => 'Kas Tunai',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'is_active' => true,
    ]);

    $modal = Account::where('code', '3-10001')->firstOrFail();

    // 1. Create journal
    $journal = $service->createJournalEntry([
        'date' => now(),
        'description' => 'Setoran Modal Uji Coba',
    ], [
        ['account_id' => $kas->id, 'debit' => 1000000, 'credit' => 0],
        ['account_id' => $modal->id, 'debit' => 0, 'credit' => 1000000],
    ], $this->user);

    expect(Activity::where('log_name', 'transaksi_jurnal')->where('event', 'created')->exists())->toBeTrue();

    // 2. Revert journal
    $service->revertJournalEntry($journal);

    expect(Activity::where('log_name', 'transaksi_jurnal')->where('event', 'undo')->exists())->toBeTrue();
});

test('logs activity on wallet balance adjustment', function () {
    $service = app(AccountingService::class);

    $kas = Account::where('code', '1-10001')->first() ?? Account::create([
        'code' => '1-10001',
        'name' => 'Kas Tunai',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'is_active' => true,
    ]);

    $service->adjustBalance($kas, 750000, 'Koreksi fisik kas', now(), $this->user);

    expect(Activity::where('log_name', 'dompet_rekening')->where('event', 'adjustment')->exists())->toBeTrue();
});

test('authenticated user can view activity log page in filament', function () {
    $this->actingAs($this->user);

    $response = $this->get('/admin/activity-log-page');
    $response->assertSuccessful();

    Livewire::test(ActivityLogPage::class)
        ->assertSuccessful()
        ->assertSee('Log Aktivitas Sistem');
});
