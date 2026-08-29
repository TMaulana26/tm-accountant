<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Ai\AiServiceManager;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    // Setup Kas Tunai & Modal Awal
    $parentKasBank = Account::where('code', '1-10000')->firstOrFail();
    $this->kasTunai = Account::firstOrCreate(
        ['code' => '1-10001'],
        [
            'name' => 'Kas Tunai',
            'parent_id' => $parentKasBank->id,
            'category' => AccountCategory::CashAndBank,
            'type' => AccountType::Asset,
            'is_default' => true,
            'is_active' => true,
        ]
    );

    // Initial balance 165.000
    $modalAwal = Account::where('code', '3-10001')->firstOrFail();
    $service = app(AccountingService::class);
    $service->createJournalEntry([
        'date' => now(),
        'description' => 'Saldo Awal Kas Tunai',
        'source' => JournalSource::System,
    ], [
        ['account_id' => $this->kasTunai->id, 'debit' => 165000, 'credit' => 0],
        ['account_id' => $modalAwal->id, 'debit' => 0, 'credit' => 165000],
    ]);

    config(['services.telegram.allowed_user_ids' => '7163641352']);
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
    ]);
});

test('duplicate Telegram webhook message creates only one journal entry and prevents duplicate deductions', function () {
    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->with('Beli Tempe Oreg 10rb')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 10000,
                'description' => 'Beli Tempe Oreg',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Kas Tunai',
                'date' => now()->toDateString(),
            ],
            'confidence' => 0.98,
        ]);
    app()->instance(AiServiceManager::class, $aiMock);

    $botService = app(TelegramBotService::class);

    $payload = [
        'update_id' => 1001,
        'message' => [
            'message_id' => 55555,
            'from' => [
                'id' => 7163641352,
                'username' => 'tmaulana',
            ],
            'chat' => [
                'id' => 7163641352,
            ],
            'text' => 'Beli Tempe Oreg 10rb',
        ],
    ];

    // 1st Attempt: Legitimate transaction
    $botService->handleUpdate($payload);

    $initialEntriesCount = JournalEntry::where('source', JournalSource::Telegram)->count();
    expect($initialEntriesCount)->toBe(1);
    expect($this->kasTunai->fresh()->balance)->toBe(155000.0); // 165.000 - 10.000 = 155.000

    // 2nd Attempt: Webhook retry with exact same message_id
    $botService->handleUpdate($payload);

    // 3rd Attempt: Webhook retry with exact same message_id
    $botService->handleUpdate($payload);

    // Assert NO DUPLICATES were created
    $finalEntriesCount = JournalEntry::where('source', JournalSource::Telegram)->count();
    expect($finalEntriesCount)->toBe(1)
        ->and($this->kasTunai->fresh()->balance)->toBe(155000.0) // Still 155.000, NOT 135.000!
        ->and(TelegramMessage::where('telegram_message_id', 55555)->count())->toBe(1);
});

test('different Telegram messages are processed independently', function () {
    $aiMock = Mockery::mock(AiServiceManager::class);
    $aiMock->shouldReceive('processMessage')
        ->with('Beli Tempe Oreg 10rb')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 10000,
                'description' => 'Beli Tempe Oreg',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Kas Tunai',
                'date' => now()->toDateString(),
            ],
            'confidence' => 0.98,
        ]);
    $aiMock->shouldReceive('processMessage')
        ->with('Beli Kopi 15rb')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 15000,
                'description' => 'Beli Kopi',
                'expense_account' => 'Makanan & Minuman (Harian)',
                'payment_account' => 'Kas Tunai',
                'date' => now()->toDateString(),
            ],
            'confidence' => 0.98,
        ]);
    app()->instance(AiServiceManager::class, $aiMock);

    $botService = app(TelegramBotService::class);

    // 1st Message
    $botService->handleUpdate([
        'update_id' => 1001,
        'message' => [
            'message_id' => 55555,
            'from' => ['id' => 7163641352, 'username' => 'tmaulana'],
            'chat' => ['id' => 7163641352],
            'text' => 'Beli Tempe Oreg 10rb',
        ],
    ]);

    // 2nd Message (Different message_id & content)
    $botService->handleUpdate([
        'update_id' => 1002,
        'message' => [
            'message_id' => 55556,
            'from' => ['id' => 7163641352, 'username' => 'tmaulana'],
            'chat' => ['id' => 7163641352],
            'text' => 'Beli Kopi 15rb',
        ],
    ]);

    expect(JournalEntry::where('source', JournalSource::Telegram)->count())->toBe(2)
        ->and($this->kasTunai->fresh()->balance)->toBe(140000.0); // 165k - 10k - 15k = 140k
});

test('AccountingService::createSimpleTransaction is idempotent with same reference_number', function () {
    $service = app(AccountingService::class);
    $bebanMakan = Account::where('code', '6-10001')->firstOrFail();

    $entry1 = $service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 20000,
        sourceAccount: $this->kasTunai,
        destinationAccount: $bebanMakan,
        description: 'Makan Bakso',
        source: JournalSource::Telegram,
        referenceNumber: 'TG-7163641352-88888'
    );

    $entry2 = $service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 20000,
        sourceAccount: $this->kasTunai,
        destinationAccount: $bebanMakan,
        description: 'Makan Bakso',
        source: JournalSource::Telegram,
        referenceNumber: 'TG-7163641352-88888'
    );

    expect($entry1->id)->toBe($entry2->id)
        ->and(JournalEntry::where('reference_number', 'TG-7163641352-88888')->count())->toBe(1)
        ->and($this->kasTunai->fresh()->balance)->toBe(145000.0); // Only deducted once
});
