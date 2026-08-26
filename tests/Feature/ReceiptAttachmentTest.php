<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReceiptImageService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    Storage::fake('public');

    $parent = Account::where('code', '1-10000')->first();
    Account::firstOrCreate(['code' => '1-10001'], [
        'name' => 'Kas Tunai (Dompet Fisik)',
        'type' => AccountType::Asset,
        'category' => AccountCategory::CashAndBank,
        'parent_id' => $parent?->id,
        'is_active' => true,
        'is_default' => true,
    ]);
});

test('ReceiptImageService compresses and stores image binary using PHP GD', function () {
    // Generate a dummy truecolor image using GD
    $img = imagecreatetruecolor(1600, 1200);
    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 1600, 1200, $bg);
    $textColor = imagecolorallocate($img, 0, 0, 0);
    imagestring($img, 5, 50, 50, 'STRUK BELANJA TEST', $textColor);

    ob_start();
    imagejpeg($img, null, 100);
    $rawImageBytes = ob_get_clean();
    imagedestroy($img);

    $service = new ReceiptImageService;
    $storedPath = $service->compressAndStore($rawImageBytes, maxWidth: 800, quality: 75);

    expect($storedPath)->not->toBeNull()
        ->and($storedPath)->toStartWith('receipts/');

    Storage::disk('public')->assertExists($storedPath);
});

test('AccountingService attaches receipt_image to journal entry', function () {
    $kas = Account::where('code', '1-10001')->firstOrFail();
    $food = Account::where('code', '6-10001')->firstOrFail();

    $service = app(AccountingService::class);
    $entry = $service->createSimpleTransaction(
        date: now(),
        type: 'expense',
        amount: 50000,
        sourceAccount: $kas,
        destinationAccount: $food,
        description: 'Beli makan malam',
        source: JournalSource::Web,
        receiptImage: 'receipts/2026/08/test_receipt.jpg'
    );

    expect($entry->receipt_image)->toBe('receipts/2026/08/test_receipt.jpg')
        ->and($entry->receipt_image_url)->toContain('receipts/2026/08/test_receipt.jpg');
});
