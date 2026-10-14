<?php

use App\Enums\TelegramMessageStatus;
use App\Filament\Pages\TelegramChatHistoryPage;
use App\Models\TelegramMessage;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);
});

test('authenticated user can view telegram chat history page with messenger UI', function () {
    $this->actingAs($this->user);

    TelegramMessage::create([
        'telegram_message_id' => 9991,
        'chat_id' => '12345678',
        'from_id' => '12345678',
        'from_username' => 'testuser',
        'raw_text' => 'Beli kopi 25rb',
        'intent' => 'record_expense',
        'ai_response' => '<b>Pengeluaran Berhasil Dicatat:</b> Rp 25.000',
        'status' => TelegramMessageStatus::Processed,
    ]);

    $response = $this->get('/admin/telegram-chat-history-page');
    $response->assertSuccessful();

    Livewire::test(TelegramChatHistoryPage::class)
        ->assertSuccessful()
        ->assertSee('Riwayat Percakapan Telegram Bot')
        ->assertSee('Beli kopi 25rb')
        ->assertSee('Pengeluaran Berhasil Dicatat');
});

test('filters telegram chat history by intent and period', function () {
    $this->actingAs($this->user);

    TelegramMessage::create([
        'telegram_message_id' => 9992,
        'chat_id' => '12345678',
        'from_id' => '12345678',
        'from_username' => 'testuser',
        'raw_text' => 'Gaji 10jt',
        'intent' => 'record_income',
        'ai_response' => 'Pemasukan Dicatat',
        'status' => TelegramMessageStatus::Processed,
        'created_at' => now(),
    ]);

    Livewire::test(TelegramChatHistoryPage::class)
        ->fillForm([
            'intent' => 'record_income',
            'period_preset' => 'today',
        ])
        ->assertSee('Gaji 10jt')
        ->fillForm([
            'intent' => 'record_expense',
        ])
        ->assertDontSee('Gaji 10jt');
});

test('formatChatResponse escapes malicious XSS tags while preserving safe formatting', function () {
    $page = new TelegramChatHistoryPage;

    $malicious = '<script>alert("xss")</script><img src=x onerror=alert(1)><b>Saldo:</b> **Rp 50.000** `1-10001`';
    $formatted = $page->formatChatResponse($malicious);

    expect($formatted)->not->toContain('<script>')
        ->and($formatted)->not->toContain('<img')
        ->and($formatted)->toContain('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;')
        ->and($formatted)->toContain('<strong>Saldo:</strong>')
        ->and($formatted)->toContain('<strong>Rp 50.000</strong>')
        ->and($formatted)->toContain('<code>1-10001</code>');
});
