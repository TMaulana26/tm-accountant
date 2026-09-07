<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Ai\AiServiceManager;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

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

    $this->foodAccount = Account::where('code', '6-10001')->firstOrFail(); // Makanan & Minuman (Harian)
    $this->donationAccount = Account::where('code', '6-30001')->firstOrFail(); // Donasi, Zakat & Sedekah

    $this->accountingService = app(AccountingService::class);

    // Mock Telegram outbound HTTP API
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    Config::set('telegram.allowed_user_ids', ['123456789']);
    Config::set('telegram.bot_token', 'mock_bot_token');
});

test('AccountingService::queryTransactions finds transactions by keyword', function () {
    // Create 2 donation transactions
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Infaq Masjid Al-Ikhlas',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->donationAccount->id, 'debit' => 50000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 50000],
    ]);

    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Sedekah anak yatim',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->donationAccount->id, 'debit' => 100000, 'credit' => 0],
        ['account_id' => $this->bcaAccount->id, 'debit' => 0, 'credit' => 100000],
    ]);

    // Create 1 food transaction (should not be counted)
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Makan siang nasi padang',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 25000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 25000],
    ]);

    // Query for 'Infaq' (matches description only)
    $result = $this->accountingService->queryTransactions([
        'keyword' => 'Infaq',
        'period' => 'this_month',
    ]);

    expect($result['total_count'])->toBe(1)
        ->and($result['total_amount'])->toEqual(50000.0)
        ->and($result['transactions'])->toHaveCount(1)
        ->and($result['transactions'][0]['description'])->toBe('Infaq Masjid Al-Ikhlas');

    // Query for category 'Donasi' (matches Account name)
    $resultCat = $this->accountingService->queryTransactions([
        'account_category' => 'Donasi',
        'period' => 'this_month',
    ]);

    expect($resultCat['total_count'])->toBe(2)
        ->and($resultCat['total_amount'])->toEqual(150000.0);
});

test('AccountingService::queryTransactions filters by custom date range', function () {
    // 1 transaction on 2nd of month
    $startOfMonth = Carbon::now()->startOfMonth();
    $this->accountingService->createJournalEntry([
        'date' => $startOfMonth->copy()->addDays(1),
        'description' => 'Kopi pagi tanggal 2',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 18000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 18000],
    ]);

    // 1 transaction in previous month
    $this->accountingService->createJournalEntry([
        'date' => $startOfMonth->copy()->subDays(5),
        'description' => 'Kopi bulan lalu',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 20000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 20000],
    ]);

    $result = $this->accountingService->queryTransactions([
        'start_date' => $startOfMonth->toDateString(),
        'end_date' => Carbon::now()->toDateString(),
        'account_category' => 'Makanan',
    ]);

    expect($result['total_count'])->toBe(1)
        ->and($result['total_amount'])->toEqual(18000.0)
        ->and($result['transactions'][0]['description'])->toBe('Kopi pagi tanggal 2');
});

test('AccountingService::queryTransactions returns wallet mutations and remaining balance', function () {
    // Initial balance via cash account
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now()->subDays(2),
        'description' => 'Setor tunai ke BCA',
        'source' => JournalSource::Web,
    ], [
        ['account_id' => $this->bcaAccount->id, 'debit' => 500000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 500000],
    ]);

    // Expense from BCA
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Beli makan via BCA',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 50000, 'credit' => 0],
        ['account_id' => $this->bcaAccount->id, 'debit' => 0, 'credit' => 50000],
    ]);

    $result = $this->accountingService->queryTransactions([
        'wallet_name' => 'BCA',
        'period' => 'this_month',
    ]);

    expect($result['wallet_account'])->not->toBeNull()
        ->and($result['wallet_account']->name)->toBe('Bank BCA')
        ->and($result['wallet_balance'])->toEqual(450000.0)
        ->and($result['total_count'])->toBe(2);
});

test('Telegram bot handles query_transactions intent with frequency and nominal summary', function () {
    // Seed a donation
    $this->accountingService->createJournalEntry([
        'date' => Carbon::now(),
        'description' => 'Sedekah Subuh',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->donationAccount->id, 'debit' => 50000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 50000],
    ]);

    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('saya donasi berapa kali bulan ini dan habis berapa?')
        ->andReturn([
            'intent' => 'query_transactions',
            'parameters' => [
                'keyword' => 'donasi',
                'period' => 'this_month',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 201,
            'chat' => ['id' => 123456789],
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'text' => 'saya donasi berapa kali bulan ini dan habis berapa?',
        ],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $text = $body['text'] ?? '';

        return str_contains($text, 'DONASI & SEDEKAH')
            && str_contains($text, '1 Transaksi')
            && str_contains($text, 'Rp 50.000')
            && str_contains($text, 'Sedekah Subuh');
    });
});

test('Telegram bot handles query_transactions with empty results gracefully', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('beli pizza berapa kali bulan ini')
        ->andReturn([
            'intent' => 'query_transactions',
            'parameters' => [
                'keyword' => 'pizza',
                'period' => 'this_month',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 202,
            'chat' => ['id' => 123456789],
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'text' => 'beli pizza berapa kali bulan ini',
        ],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $text = $body['text'] ?? '';

        return str_contains($text, 'TIDAK DITEMUKAN')
            && str_contains($text, 'pizza');
    });
});

test('Telegram bot groups transactions by date and cleans wallet display names', function () {
    $parent = Account::where('code', '1-10000')->first();
    $gopay = Account::firstOrCreate(['code' => '1-10004'], [
        'name' => 'E-Wallet GoPay',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);

    // Day 1
    $this->accountingService->createJournalEntry([
        'date' => Carbon::parse('2026-09-02'),
        'description' => 'Nasi Uduk',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 8000, 'credit' => 0],
        ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 8000],
    ]);

    // Day 2
    $this->accountingService->createJournalEntry([
        'date' => Carbon::parse('2026-09-03'),
        'description' => 'Paket Ayam Crispy',
        'source' => JournalSource::Telegram,
    ], [
        ['account_id' => $this->foodAccount->id, 'debit' => 22000, 'credit' => 0],
        ['account_id' => $gopay->id, 'debit' => 0, 'credit' => 22000],
    ]);

    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->andReturn([
            'intent' => 'query_transactions',
            'parameters' => [
                'account_category' => 'Makanan',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-07',
            ],
            'reply_text' => null,
        ]);

    app()->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 203,
            'chat' => ['id' => 123456789],
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'text' => 'makan apa saja dari tanggal 1 sampai 7',
        ],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $text = $body['text'] ?? '';

        return str_contains($text, 'MAKANAN & MINUMAN')
            && str_contains($text, '2 Transaksi')
            && str_contains($text, 'Rp 30.000')
            && str_contains($text, '📅 <b>02 Sep 2026</b>')
            && str_contains($text, '• Nasi Uduk — <code>Rp 8.000</code> <i>(Kas Tunai)</i>')
            && str_contains($text, '📅 <b>03 Sep 2026</b>')
            && str_contains($text, '• Paket Ayam Crispy — <code>Rp 22.000</code> <i>(GoPay)</i>');
    });
});
