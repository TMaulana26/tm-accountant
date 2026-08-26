<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
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
    Account::firstOrCreate(['code' => '1-10002'], [
        'name' => 'Bank BCA',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
    ]);

    Config::set('telegram.allowed_user_ids', ['123456789']);
    Config::set('telegram.bot_token', 'mock_bot_token');
});

test('bot processes photo receipt via Gemini OCR and AI accounting pipeline', function () {
    // Generate a valid 100x100 dummy JPEG binary
    $img = imagecreatetruecolor(100, 100);
    ob_start();
    imagejpeg($img);
    $validImageBytes = ob_get_clean();
    imagedestroy($img);

    // 1. Fake Telegram HTTP endpoints (getFile, download file, sendMessage)
    Http::fake([
        'https://api.telegram.org/botmock_bot_token/getFile' => Http::response([
            'ok' => true,
            'result' => [
                'file_id' => 'photo_12345',
                'file_path' => 'photos/file_99.jpg',
            ],
        ], 200),
        'https://api.telegram.org/file/botmock_bot_token/photos/file_99.jpg' => Http::response($validImageBytes, 200),
        'https://api.telegram.org/botmock_bot_token/sendMessage' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    // 2. Mock AiServiceManager
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processReceiptImage')
        ->once()
        ->with($validImageBytes, 'image/jpeg', 'struk belanja dapur tadi siang')
        ->andReturn([
            'intent' => 'record_expense',
            'parameters' => [
                'amount' => 43000,
                'description' => 'Belanja Alfamart (Indomie & Telur)',
                'expense_account' => 'Belanja Dapur & Groceries',
                'payment_account' => 'Bank BCA',
                'date' => '2026-08-26',
            ],
            'ocr_text' => "ALFAMART KEBON JERUK\nTotal: Rp 43.000",
            'reply_text' => null,
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    // 4. Send Telegram update with photo
    $botService->handleUpdate([
        'message' => [
            'message_id' => 555,
            'chat' => ['id' => 123456789],
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'caption' => 'struk belanja dapur tadi siang',
            'photo' => [
                ['file_id' => 'thumb_1', 'width' => 100, 'height' => 100],
                ['file_id' => 'photo_12345', 'width' => 800, 'height' => 1200], // highest resolution
            ],
        ],
    ]);

    // 5. Assertions
    expect(JournalEntry::count())->toBe(1);
    $entry = JournalEntry::first();
    expect($entry->isBalanced())->toBeTrue()
        ->and($entry->total_debit)->toBe(43000.0)
        ->and($entry->description)->toBe('Belanja Alfamart (Indomie & Telur)')
        ->and($entry->receipt_image)->not->toBeNull()
        ->and($entry->receipt_image)->toStartWith('receipts/');

    $log = TelegramMessage::first();
    expect($log->status)->toBe(TelegramMessageStatus::Processed)
        ->and($log->receipt_image)->toBe($entry->receipt_image);

    // Assert message sent with receipt confirmation
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'NOTA / STRUK BERHASIL DIPROSES') &&
            str_contains($request['text'], 'Belanja Alfamart');
    });
});
