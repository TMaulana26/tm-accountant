<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\TelegramMessageStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Services\Accounting\AccountingService;
use App\Services\Ai\AiServiceManager;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'telegram.bot_token' => 'test-bot-token',
        'telegram.allowed_user_ids' => ['999111'],
    ]);

    $cashParent = Account::create([
        'code' => '1-1000',
        'name' => 'Kas & Bank',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'is_active' => true,
        'is_system' => true,
    ]);

    Account::create([
        'code' => '3-10001',
        'name' => 'Modal Awal / Ekuitas Pemilik',
        'type' => AccountType::Equity,
        'category' => AccountCategory::Equity,
        'is_active' => true,
        'is_system' => true,
    ]);

    $kas = Account::create([
        'code' => '1-1101',
        'name' => 'Kas Tunai (Cash on Hand)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $cashParent->id,
        'is_active' => true,
        'is_default' => true,
    ]);

    $jago = Account::create([
        'code' => '1-1105',
        'name' => 'Bank Jago',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $cashParent->id,
        'is_active' => true,
        'is_default' => false,
    ]);

    $gopay = Account::create([
        'code' => '1-1103',
        'name' => 'E-Wallet GoPay',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $cashParent->id,
        'is_active' => true,
        'is_default' => false,
    ]);

    Account::create([
        'code' => '1-1201',
        'name' => 'Piutang Usaha / Pinjaman',
        'type' => AccountType::Asset,
        'category' => AccountCategory::AccountsReceivable,
        'is_active' => true,
    ]);

    Account::create([
        'code' => '2-1101',
        'name' => 'Utang Usaha / Pinjaman',
        'type' => AccountType::Liability,
        'category' => AccountCategory::AccountsPayable,
        'is_active' => true,
    ]);

    Account::create([
        'code' => '5-1101',
        'name' => 'Makanan & Minuman (Harian)',
        'type' => AccountType::Expense,
        'category' => AccountCategory::OperatingExpense,
        'is_active' => true,
    ]);

    $accounting = app(AccountingService::class);
    $accounting->setInitialBalance($kas, 500000);
    $accounting->setInitialBalance($jago, 1000000);
    $accounting->setInitialBalance($gopay, 50000);
});

it('supports reply-to-edit (update_transaction) to switch wallet or amount of an existing journal entry', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 501]]),
    ]);

    $accounting = app(AccountingService::class);
    $kas = Account::where('code', '1-1101')->firstOrFail();
    $makan = Account::where('code', '5-1101')->firstOrFail();

    $journal = $accounting->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 25000,
        sourceAccount: $kas,
        destinationAccount: $makan,
        description: 'Beli Bakso'
    );

    TelegramMessage::create([
        'telegram_message_id' => 100,
        'chat_id' => '999111',
        'from_id' => '999111',
        'raw_text' => 'beli bakso 25k',
        'journal_entry_id' => $journal->id,
        'status' => TelegramMessageStatus::Processed,
    ]);

    expect((float) $kas->fresh()->balance)->toBe(475000.0);

    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->once()
        ->with('ralat pakai bank jago dan jadi 30rb')
        ->andReturnUsing(function () use ($journal) {
            expect(collect(AiServiceManager::$activeConversationContext)->contains(
                fn ($c) => ($c['journal_number'] ?? null) === $journal->entry_number
            ))->toBeTrue();

            return [
                'intent' => 'update_transaction',
                'parameters' => [
                    'payment_account' => 'Bank Jago',
                    'amount' => 30000,
                ],
                'reply_text' => null,
            ];
        });

    app()->instance(AiServiceManager::class, $aiMock);

    $bot = app(TelegramBotService::class);
    $bot->handleUpdate([
        'message' => [
            'message_id' => 101,
            'chat' => ['id' => '999111'],
            'from' => ['id' => '999111', 'username' => 'kangtama'],
            'text' => 'ralat pakai bank jago dan jadi 30rb',
            'reply_to_message' => [
                'message_id' => 100,
                'text' => "✅ PENGELUARAN BERHASIL DICATAT\nNo. Jurnal: {$journal->entry_number}",
            ],
        ],
    ]);

    $jago = Account::where('code', '1-1105')->firstOrFail();
    expect((float) $kas->fresh()->balance)->toBe(500000.0)
        ->and((float) $jago->fresh()->balance)->toBe(970000.0)
        ->and((float) $makan->fresh()->balance)->toBe(30000.0);
});

it('automatically falls back to secondary Gemini models when primary model returns 503 high demand', function () {
    Http::fake([
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent*' => Http::response([
            'error' => ['message' => 'This model is currently experiencing high demand.'],
        ], 503),
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'functionCall' => [
                                    'name' => 'record_expense',
                                    'args' => [
                                        'amount' => 15000,
                                        'description' => 'Parkir & Kopi',
                                        'expense_account' => 'Makanan & Minuman (Harian)',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $driver = new GeminiDriver(
        baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
        apiKey: 'fake-gemini-key',
        model: 'gemini-3.5-flash-lite',
        timeout: 10
    );

    $result = $driver->chat('System prompt', 'kopi 15rb', [
        [
            'type' => 'function',
            'function' => [
                'name' => 'record_expense',
                'description' => 'Catat pengeluaran',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'amount' => ['type' => 'number'],
                    ],
                ],
            ],
        ],
    ]);

    expect($result['intent'])->toBe('record_expense')
        ->and($result['parameters']['amount'])->toBe(15000);
});

it('supports inline retry callback (retry_msg_{id}) when a message previously failed', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $failedLog = TelegramMessage::create([
        'telegram_message_id' => 202,
        'chat_id' => '999111',
        'from_id' => '999111',
        'from_username' => 'kangtama',
        'raw_text' => 'beli nasi padang 28rb pakai bank jago',
        'status' => TelegramMessageStatus::Failed,
        'ai_response' => 'cURL error 28: Operation timed out',
    ]);

    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->once()
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 28000,
                'description' => 'Beli Nasi Padang',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Bank Jago',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $aiMock);

    $bot = app(TelegramBotService::class);
    $bot->handleUpdate([
        'callback_query' => [
            'id' => 'cb-retry-1',
            'from' => ['id' => '999111'],
            'data' => "retry_msg_{$failedLog->id}",
            'message' => [
                'message_id' => 203,
                'chat' => ['id' => '999111'],
            ],
        ],
    ]);

    $failedLog->refresh();
    $jago = Account::where('code', '1-1105')->firstOrFail();

    expect($failedLog->status)->toBe(TelegramMessageStatus::Processed)
        ->and($failedLog->journal_entry_id)->not->toBeNull()
        ->and((float) $jago->balance)->toBe(972000.0);
});

it('tracks per-person receivables (/piutang) and auto-fills remaining balance on repayment without nominal', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $accounting = app(AccountingService::class);
    $jago = Account::where('code', '1-1105')->firstOrFail();
    $piutang = Account::where('code', '1-1201')->firstOrFail();

    // 1. A Vio borrows Rp 150,000 from Bank Jago
    $accounting->createSimpleTransaction(
        date: now()->subDays(2),
        type: 'expense',
        amount: 150000,
        sourceAccount: $jago,
        destinationAccount: $piutang,
        description: 'Pinjaman A Vio'
    );

    $summary = $accounting->getDebtReceivableSummary('Vio', 'receivable');
    expect($summary['total_receivable'])->toBe(150000.0)
        ->and($summary['receivables'][0]['name'])->toContain('Vio');

    // 2. User says "A Vio lunasin hutang ke bank Jago" (AI returns amount: 0)
    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->once()
        ->andReturn([
            'intent' => 'record_income',
            'parameters' => [
                'amount' => 0,
                'description' => 'Pelunasan piutang dari A Vio',
                'income_account' => 'Piutang Usaha / Pinjaman',
                'deposit_account' => 'Bank Jago',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $aiMock);

    $bot = app(TelegramBotService::class);
    $bot->handleUpdate([
        'message' => [
            'message_id' => 301,
            'chat' => ['id' => '999111'],
            'from' => ['id' => '999111', 'username' => 'kangtama'],
            'text' => 'A Vio lunasin hutang ke bank Jago',
        ],
    ]);

    $afterSummary = $accounting->getDebtReceivableSummary('Vio', 'receivable');
    expect($afterSummary['total_receivable'])->toBe(0.0)
        ->and((float) $jago->fresh()->balance)->toBe(1000000.0)
        ->and((float) $piutang->fresh()->balance)->toBe(0.0);
});

it('handles direct wallet balance adjustments (delta_amount and target_balance) via Telegram', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->once()
        ->andReturn([
            'intent' => 'adjust_account_balance',
            'parameters' => [
                'account_name' => 'GoPay',
                'delta_amount' => 7100,
                'description' => 'Penyesuaian dana GoPay',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $aiMock);

    $bot = app(TelegramBotService::class);
    $bot->handleUpdate([
        'message' => [
            'message_id' => 401,
            'chat' => ['id' => '999111'],
            'from' => ['id' => '999111', 'username' => 'kangtama'],
            'text' => 'Penyesuaian dana 7100 gopay',
        ],
    ]);

    $gopay = Account::where('code', '1-1103')->firstOrFail();
    expect((float) $gopay->fresh()->balance)->toBe(57100.0)
        ->and(JournalEntry::where('description', 'like', '%GoPay%')->exists())->toBeTrue();
});
