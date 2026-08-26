<?php

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Filament\Pages\Auth\Login;
use App\Models\Account;
use App\Models\User;
use App\Services\Ai\AiServiceManager;
use App\Services\Telegram\TelegramBotService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

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

    $this->user = User::factory()->create([
        'name' => 'Admin Test',
        'email' => 'admin@example.com',
        'password' => bcrypt('password123'),
        'telegram_chat_id' => '123456789',
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    Config::set('telegram.allowed_user_ids', ['123456789']);
    Config::set('telegram.bot_token', 'mock_bot_token');
});

test('login page renders with password-only and authenticates admin user', function () {
    Livewire::test(Login::class)
        ->fillForm([
            'password' => 'password123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($this->user);
});

test('passkey login options endpoint returns correct payload', function () {
    $response = $this->getJson('/auth/passkey/login-options');

    $response->assertStatus(200)
        ->assertJsonStructure(['challenge', 'rpId', 'userEmail', 'hasCredentials']);

    expect($response->json('userEmail'))->toBe('admin@example.com');
});

test('authenticated user can register and clear passkeys', function () {
    $this->actingAs($this->user);

    // Get register options
    $optionsRes = $this->getJson('/auth/passkey/register-options');
    $optionsRes->assertStatus(200)
        ->assertJsonStructure(['challenge', 'rp', 'user']);

    // Register credential
    $registerRes = $this->postJson('/auth/passkey/register', [
        'id' => 'test_credential_id_123',
    ]);
    $registerRes->assertStatus(200)
        ->assertJson(['ok' => true]);

    $this->user->refresh();
    expect($this->user->passkey_credentials)->toHaveCount(1)
        ->and($this->user->passkey_credentials[0]['id'])->toBe('test_credential_id_123');

    // Clear credentials
    $clearRes = $this->postJson('/auth/passkey/clear');
    $clearRes->assertStatus(200)
        ->assertJson(['ok' => true]);

    $this->user->refresh();
    expect($this->user->passkey_credentials)->toBe([]);
});

test('profile page renders successfully for authenticated user', function () {
    $this->actingAs($this->user);

    $response = $this->get('/admin/profile');
    $response->assertSuccessful();
});

test('telegram bot rejects long off-topic message with guidance', function () {
    $botService = app(TelegramBotService::class);

    $longText = str_repeat('Ini adalah teks panjang yang sengaja dibuat untuk mengetes apakah bot memiliki guardrails terhadap pesan yang terlalu panjang dan tidak ada hubungannya dengan transaksi. ', 3);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 901,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'text' => $longText,
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'ASISTEN PENCATATAN KEUANGAN PRIBADI');
    });
});

test('telegram bot handles out_of_topic intent gracefully', function () {
    $mockAi = mock(AiServiceManager::class);
    $mockAi->shouldReceive('processMessage')
        ->once()
        ->with('bagaimana cara masak rendang daging sapi enak?')
        ->andReturn([
            'intent' => 'out_of_topic',
            'parameters' => [],
            'reply_text' => null,
            'raw_response' => [],
        ]);

    $this->app->instance(AiServiceManager::class, $mockAi);

    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 902,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'testuser'],
            'text' => 'bagaimana cara masak rendang daging sapi enak?',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage') &&
            str_contains($request['text'], 'ASISTEN PENCATATAN KEUANGAN PRIBADI');
    });
});
