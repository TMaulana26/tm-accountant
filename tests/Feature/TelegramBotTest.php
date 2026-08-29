<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\TelegramMessageStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Services\Ai\AiServiceManager;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $parent = Account::where('code', '1-10000')->first();
    Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    // Mock Telegram outbound HTTP API
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    Config::set('telegram.allowed_user_ids', ['123456789']);
    Config::set('telegram.bot_token', 'mock_bot_token');
});

test('unauthorized telegram user is rejected', function () {
    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 101,
            'chat_id' => 999999,
            'from' => ['id' => 999999, 'username' => 'stranger'],
            'text' => 'beli telur 25k',
        ],
    ]);

    expect(JournalEntry::count())->toBe(0);
});

test('records expense from natural telegram message via mocked AiServiceManager', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('beli telur 1 kg 25k')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 25000,
                'description' => 'Beli telur 1 kg',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Kas Tunai (Dompet)',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);
    $botService->handleUpdate([
        'message' => [
            'message_id' => 1,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'beli telur 1 kg 25k',
        ],
    ]);

    expect(JournalEntry::count())->toBe(1);

    $journal = JournalEntry::first();
    expect($journal->description)->toBe('Beli telur 1 kg')
        ->and($journal->total_debit)->toBe(25000.0)
        ->and($journal->source)->toBe(JournalSource::Telegram);

    $log = TelegramMessage::first();
    expect($log->status)->toBe(TelegramMessageStatus::Processed)
        ->and($log->journal_entry_id)->toBe($journal->id);
});

test('handles undo callback button from telegram', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 50000,
                'description' => 'Bensin motor',
                'expense_account' => 'Transportasi & Bensin',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    // 1. Record expense
    $botService->handleUpdate([
        'message' => [
            'message_id' => 2,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'bensin motor 50rb',
        ],
    ]);

    $journal = JournalEntry::first();
    expect(JournalEntry::count())->toBe(1);

    // 2. Click Undo button
    $botService->handleUpdate([
        'callback_query' => [
            'id' => 'cb_123',
            'data' => "undo_journal_{$journal->id}",
            'from' => ['id' => 123456789],
            'message' => [
                'message_id' => 3,
                'chat' => ['id' => 123456789],
                'text' => 'Original confirmation text',
            ],
        ],
    ]);

    expect(JournalEntry::count())->toBe(0);

    $log = TelegramMessage::first();
    expect($log->status)->toBe(TelegramMessageStatus::Reverted);
});

test('telegram webhook endpoint processes payload successfully', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->andReturn([
            'intent' => 'record_income',
            'parameters' => [
                'amount' => 15000000,
                'description' => 'Gaji bulanan',
                'income_account' => 'Gaji & Tunjangan',
                'deposit_account' => 'Bank BCA',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $response = $this->postJson('/api/telegram/webhook', [
        'message' => [
            'message_id' => 5,
            'chat' => ['id' => 123456789],
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'gaji masuk 15jt ke bca',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson(['ok' => true]);

    expect(JournalEntry::count())->toBe(1);
});

test('bot directs user to web admin when no wallets configured', function () {
    // Delete all wallets to simulate unconfigured state
    Account::wallets()->delete();

    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 201,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'beli telur 25k',
        ],
    ]);

    // No journal should be created
    expect(JournalEntry::count())->toBe(0);

    // Assert outbound HTTP sent guide message with /admin link
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'DOMPET & REKENING BELUM DIATUR');
    });
});

test('bot appends warning when expense causes wallet to be zero or negative', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('beli makan 50rb')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 50000,
                'description' => 'Makan malam',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Kas Tunai (Dompet Fisik)',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    // Initial cash is 0, so 50,000 expense causes balance = -50,000
    $botService->handleUpdate([
        'message' => [
            'message_id' => 301,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'beli makan 50rb',
        ],
    ]);

    expect(JournalEntry::count())->toBe(1);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'PERINGATAN SALDO') &&
            str_contains($request['text'], 'Defisit / Minus');
    });
});

test('bot automatically converts markdown bold and bullets to Telegram HTML', function () {
    $botService = app(TelegramBotService::class);

    $rawMarkdown = "- 💸 **Pengeluaran** (belanja, makan)\n- 💰 **Pemasukan** (gaji)\n- `code_sample`";
    $converted = $botService->formatMarkdownToTelegramHtml($rawMarkdown);

    expect($converted)->toContain('• 💸 <b>Pengeluaran</b> (belanja, makan)')
        ->and($converted)->toContain('• 💰 <b>Pemasukan</b> (gaji)')
        ->and($converted)->toContain('<code>code_sample</code>');
});

test('bot queries single account balance from natural language question', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('Saldo shopee saya berapa kang')
        ->andReturn([
            'intent' => 'query_account_balance',
            'parameters' => [
                'account_name' => 'Kas Tunai',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);
    $botService->handleUpdate([
        'message' => [
            'message_id' => 401,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'Saldo shopee saya berapa kang',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'INFORMASI SALDO AKUN') &&
            str_contains($request['text'], 'Kas Tunai');
    });
});

test('bot records debt payment to liability account correctly', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('Bayar hutang ke Tante Lany 300rb via BCA')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 300000,
                'description' => 'Bayar hutang ke Tante Lany',
                'expense_account' => 'Hutang Pribadi / Pinjaman',
                'payment_account' => 'Kas Tunai',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);
    $botService->handleUpdate([
        'message' => [
            'message_id' => 501,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'Bayar hutang ke Tante Lany 300rb via BCA',
        ],
    ]);

    expect(JournalEntry::count())->toBe(1);

    $journal = JournalEntry::with('items.account')->first();
    $debitItem = $journal->items->firstWhere('debit', '>', 0);
    $creditItem = $journal->items->firstWhere('credit', '>', 0);

    expect($debitItem->account->type)->toBe(AccountType::Liability)
        ->and($debitItem->account->code)->toBe('2-10003')
        ->and($creditItem->account->type)->toBe(AccountType::Asset);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'PEMBAYARAN HUTANG') &&
            str_contains($request['text'], 'Akun Kewajiban (Hutang)');
    });
});

test('bot records investment to other current asset account correctly', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('Investasi Reksadana dari bank jago 500rb')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 500000,
                'description' => 'Investasi Reksadana dari Bank Jago',
                'expense_account' => 'Investasi & Tabungan Berjangka',
                'payment_account' => 'Kas Tunai',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);
    $botService->handleUpdate([
        'message' => [
            'message_id' => 601,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'Investasi Reksadana dari bank jago 500rb',
        ],
    ]);

    expect(JournalEntry::count())->toBe(1);

    $journal = JournalEntry::with('items.account')->first();
    $debitItem = $journal->items->firstWhere('debit', '>', 0);
    $creditItem = $journal->items->firstWhere('credit', '>', 0);

    expect($debitItem->account->type)->toBe(AccountType::Asset)
        ->and($debitItem->account->code)->toBe('1-10201')
        ->and($debitItem->account->category)->toBe(AccountCategory::OtherCurrentAsset)
        ->and($creditItem->account->type)->toBe(AccountType::Asset);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'INVESTASI BERHASIL DICATAT') &&
            str_contains($request['text'], 'Akun Investasi / Aset Lancar');
    });
});

test('bot records arisan contribution to other current asset account correctly', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('Bayar arisan dari bank jago 500k')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 500000,
                'description' => 'Bayar arisan',
                'expense_account' => 'Investasi & Tabungan Berjangka',
                'payment_account' => 'Kas Tunai',
            ],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);
    $botService->handleUpdate([
        'message' => [
            'message_id' => 701,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'owner'],
            'text' => 'Bayar arisan dari bank jago 500k',
        ],
    ]);

    expect(JournalEntry::count())->toBe(1);

    $journal = JournalEntry::with('items.account')->first();
    $debitItem = $journal->items->firstWhere('debit', '>', 0);
    $creditItem = $journal->items->firstWhere('credit', '>', 0);

    expect($debitItem->account->type)->toBe(AccountType::Asset)
        ->and($debitItem->account->code)->toBe('1-10201')
        ->and($debitItem->account->category)->toBe(AccountCategory::OtherCurrentAsset)
        ->and($creditItem->account->type)->toBe(AccountType::Asset);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'INVESTASI BERHASIL DICATAT') &&
            str_contains($request['text'], 'Akun Investasi / Aset Lancar');
    });
});
