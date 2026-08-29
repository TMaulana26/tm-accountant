<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Events\TelegramMessageLogged;
use App\Events\TransactionRecorded;
use App\Events\TransactionReverted;
use App\Events\WalletBalanceUpdated;
use App\Filament\Widgets\PinnedWalletsWidget;
use App\Models\Account;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $parentKasBank = Account::where('code', '1-10000')->firstOrFail();
    $this->kasTunai = Account::firstOrCreate(
        ['code' => '1-10001'],
        [
            'name' => 'Kas Tunai (Dompet Fisik)',
            'parent_id' => $parentKasBank->id,
            'category' => AccountCategory::CashAndBank,
            'type' => AccountType::Asset,
            'is_default' => true,
            'is_active' => true,
            'is_pinned' => false,
        ]
    );
});

test('user can pin and unpin wallets and query them with pinned scope', function () {
    $parent = Account::where('code', '1-10000')->first();
    $bca = Account::firstOrCreate(
        ['code' => '1-10002'],
        [
            'name' => 'Bank BCA',
            'parent_id' => $parent->id,
            'category' => AccountCategory::CashAndBank,
            'type' => AccountType::Asset,
            'is_active' => true,
            'is_pinned' => false,
        ]
    );

    expect($bca->is_pinned)->toBeFalse();

    // Toggle PIN
    $newStatus = $bca->togglePin();
    expect($newStatus)->toBeTrue()
        ->and($bca->fresh()->is_pinned)->toBeTrue();

    // Scope check
    $pinnedWallets = Account::wallets()->pinned()->get();
    expect($pinnedWallets->pluck('id'))->toContain($bca->id);

    // Toggle PIN again to unpin
    $newStatus = $bca->togglePin();
    expect($newStatus)->toBeFalse()
        ->and($bca->fresh()->is_pinned)->toBeFalse();
});

test('pinned wallets widget displays pinned wallets and allows unpinning', function () {
    $this->actingAs($this->user);

    $this->kasTunai->update(['is_pinned' => true]);

    Livewire::test(PinnedWalletsWidget::class)
        ->assertSee('Kas Tunai')
        ->assertSee('Favorit Tersemat')
        ->call('unpinWallet', $this->kasTunai->id)
        ->assertNotified();

    expect($this->kasTunai->fresh()->is_pinned)->toBeFalse();
});

test('creating transaction dispatches TransactionRecorded and WalletBalanceUpdated broadcast events', function () {
    Event::fake([
        TransactionRecorded::class,
        WalletBalanceUpdated::class,
    ]);

    $service = app(AccountingService::class);
    $bebanMakan = Account::where('code', '6-10001')->firstOrFail();

    $journal = $service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 50000,
        sourceAccount: $this->kasTunai,
        destinationAccount: $bebanMakan,
        description: 'Beli Nasi Padang',
        source: JournalSource::Telegram
    );

    Event::assertDispatched(TransactionRecorded::class, function ($event) use ($journal) {
        return $event->journalEntryId === $journal->id
            && $event->entryNumber === $journal->entry_number
            && $event->amount === 50000.0
            && $event->source === 'telegram'
            && $event->broadcastOn()[0]->name === 'accounting';
    });

    Event::assertDispatched(WalletBalanceUpdated::class, function ($event) {
        return $event->accountId === $this->kasTunai->id
            && $event->broadcastOn()[0]->name === 'accounting';
    });
});

test('reverting transaction dispatches TransactionReverted and WalletBalanceUpdated broadcast events', function () {
    $service = app(AccountingService::class);
    $bebanMakan = Account::where('code', '6-10001')->firstOrFail();

    $journal = $service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 25000,
        sourceAccount: $this->kasTunai,
        destinationAccount: $bebanMakan,
        description: 'Beli Kopi',
        source: JournalSource::Telegram
    );

    Event::fake([
        TransactionReverted::class,
        WalletBalanceUpdated::class,
    ]);

    $reverted = $service->revertJournalEntry($journal);
    expect($reverted)->toBeTrue();

    Event::assertDispatched(TransactionReverted::class, function ($event) use ($journal) {
        return $event->journalEntryId === $journal->id
            && $event->entryNumber === $journal->entry_number
            && $event->broadcastOn()[0]->name === 'accounting';
    });

    Event::assertDispatched(WalletBalanceUpdated::class, function ($event) {
        return $event->accountId === $this->kasTunai->id
            && $event->broadcastOn()[0]->name === 'accounting';
    });
});

test('saving TelegramMessage dispatches TelegramMessageLogged broadcast event', function () {
    Event::fake([
        TelegramMessageLogged::class,
    ]);

    $message = TelegramMessage::create([
        'telegram_message_id' => 99999,
        'chat_id' => '7163641352',
        'from_id' => '7163641352',
        'from_username' => 'tmaulana',
        'raw_text' => 'beli bensin 50rb',
        'intent' => 'record_expense',
        'ai_response' => '✅ Pengeluaran berhasil dicatat!',
    ]);

    Event::assertDispatched(TelegramMessageLogged::class, function ($event) use ($message) {
        return $event->messageId === $message->id
            && $event->fromUsername === 'tmaulana'
            && $event->rawText === 'beli bensin 50rb'
            && $event->intent === 'record_expense'
            && $event->broadcastOn()[0]->name === 'accounting';
    });
});
