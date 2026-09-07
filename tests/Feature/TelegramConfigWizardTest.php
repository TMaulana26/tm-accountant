<?php

use App\Models\User;
use App\Services\System\EnvironmentService;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    Config::set('telegram.allowed_user_ids', ['123456789']);
    Config::set('telegram.bot_token', 'mock_bot_token');

    // Create owner user
    User::query()->delete();
    $this->owner = User::factory()->create([
        'name' => 'Tama Owner',
        'email' => 'tama@example.com',
        'password' => Hash::make('secret123'),
    ]);

    Cache::flush();
});

test('/setup prompts for password when not authenticated', function () {
    $botService = app(TelegramBotService::class);

    $botService->handleUpdate([
        'message' => [
            'message_id' => 10,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/setup',
        ],
    ]);

    $state = Cache::get('tg_setup_state_123456789');
    expect($state)->toBeArray()
        ->and($state['step'])->toBe('awaiting_password')
        ->and(Cache::has('tg_auth_session_123456789'))->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'VERIFIKASI KEAMANAN');
    });
});

test('wrong password increases attempt counter and locks out after 3 failed attempts', function () {
    $botService = app(TelegramBotService::class);

    // Initial /setup call
    $botService->handleUpdate([
        'message' => [
            'message_id' => 10,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/setup',
        ],
    ]);

    // Attempt 1: wrong password
    $botService->handleUpdate([
        'message' => [
            'message_id' => 11,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => 'wrongpass1',
        ],
    ]);

    expect(Cache::get('tg_setup_state_123456789')['attempts'])->toBe(1);

    // Attempt 2: wrong password
    $botService->handleUpdate([
        'message' => [
            'message_id' => 12,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => 'wrongpass2',
        ],
    ]);

    expect(Cache::get('tg_setup_state_123456789')['attempts'])->toBe(2);

    // Attempt 3: wrong password -> lockout
    $botService->handleUpdate([
        'message' => [
            'message_id' => 13,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => 'wrongpass3',
        ],
    ]);

    expect(Cache::has('tg_auth_lockout_123456789'))->toBeTrue()
        ->and(Cache::has('tg_setup_state_123456789'))->toBeFalse();

    // Subsequent /setup attempt is rejected due to lockout
    $botService->handleUpdate([
        'message' => [
            'message_id' => 14,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/setup',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Terkunci Sementara');
    });
});

test('correct password authenticates session, deletes password message, and sends config main menu', function () {
    $botService = app(TelegramBotService::class);

    // Start /setup
    $botService->handleUpdate([
        'message' => [
            'message_id' => 20,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/setup',
        ],
    ]);

    // Send correct password
    $botService->handleUpdate([
        'message' => [
            'message_id' => 21,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => 'secret123',
        ],
    ]);

    expect(Cache::has('tg_auth_session_123456789'))->toBeTrue()
        ->and(Cache::has('tg_setup_state_123456789'))->toBeFalse();

    // Assert that deleteMessage was called for the password message (message_id 21)
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'deleteMessage')
            && $request['message_id'] === 21;
    });

    // Assert password verified message & main menu were sent
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Password Terverifikasi');
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'MENU PENGATURAN SISTEM (.ENV)');
    });
});

test('user can cancel setup state via /batal', function () {
    $botService = app(TelegramBotService::class);

    Cache::put('tg_setup_state_123456789', ['step' => 'awaiting_password'], now()->addMinutes(5));

    $botService->handleUpdate([
        'message' => [
            'message_id' => 30,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/batal',
        ],
    ]);

    expect(Cache::has('tg_setup_state_123456789'))->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'dibatalkan');
    });
});

test('handles config callback queries and updates AI provider', function () {
    $mockEnvService = mock(EnvironmentService::class);
    $mockEnvService->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function ($updates) {
            return ($updates['AI_PROVIDER'] ?? null) === 'deepseek';
        }))
        ->andReturn(true);

    $this->app->instance(EnvironmentService::class, $mockEnvService);
    $botService = app(TelegramBotService::class);

    // Active authenticated session
    Cache::put('tg_auth_session_123456789', true, now()->addMinutes(15));

    // Callback to change provider to deepseek
    $botService->handleUpdate([
        'callback_query' => [
            'id' => 'cb_123',
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'message' => ['message_id' => 50, 'chat' => ['id' => 123456789]],
            'data' => 'cfg_set_prov_deepseek',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'answerCallbackQuery')
            && str_contains($request['text'], 'Provider AI diubah ke: deepseek');
    });
});

test('handles callback exit and locks session', function () {
    $botService = app(TelegramBotService::class);

    Cache::put('tg_auth_session_123456789', true, now()->addMinutes(15));

    $botService->handleUpdate([
        'callback_query' => [
            'id' => 'cb_exit',
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'message' => ['message_id' => 60, 'chat' => ['id' => 123456789]],
            'data' => 'cfg_menu_exit',
        ],
    ]);

    expect(Cache::has('tg_auth_session_123456789'))->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'editMessageText')
            && str_contains($request['text'], 'SESI PENGATURAN DITUTUP');
    });
});

test('direct /set command requires authentication and rejects disallowed keys', function () {
    $botService = app(TelegramBotService::class);

    // 1. Without session -> prompt for password
    $botService->handleUpdate([
        'message' => [
            'message_id' => 70,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/set OPENROUTER_MODEL minimax/minimax-m3:free',
        ],
    ]);

    expect(Cache::has('tg_auth_session_123456789'))->toBeFalse();
    $state = Cache::get('tg_setup_state_123456789');
    expect($state['pending_action'])->toBe('direct_set');

    // 2. Authenticate session & clear pending setup state
    Cache::forget('tg_setup_state_123456789');
    Cache::put('tg_auth_session_123456789', true, now()->addMinutes(15));

    // 3. Try to update disallowed key (e.g., APP_KEY or DB_PASSWORD)
    $botService->handleUpdate([
        'message' => [
            'message_id' => 71,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/set DB_PASSWORD malicious_override',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'tidak diizinkan untuk diubah');
    });
});

test('direct /set command successfully updates allowed key when authenticated', function () {
    $mockEnvService = mock(EnvironmentService::class);
    $mockEnvService->shouldReceive('isKeyAllowed')
        ->with('OPENROUTER_MODEL')
        ->andReturn(true);
    $mockEnvService->shouldReceive('update')
        ->once()
        ->with(['OPENROUTER_MODEL' => 'minimax/minimax-m3:free'])
        ->andReturn(true);

    $this->app->instance(EnvironmentService::class, $mockEnvService);
    $botService = app(TelegramBotService::class);

    Cache::put('tg_auth_session_123456789', true, now()->addMinutes(15));

    $botService->handleUpdate([
        'message' => [
            'message_id' => 80,
            'chat_id' => 123456789,
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'text' => '/set OPENROUTER_MODEL minimax/minimax-m3:free',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && str_contains($request['text'], 'Konfigurasi Berhasil Diperbarui');
    });
});

test('handles gender selection callback and updates APP_OWNER_GENDER to perempuan', function () {
    $mockEnvService = mock(EnvironmentService::class);
    $mockEnvService->shouldReceive('update')
        ->once()
        ->with(['APP_OWNER_GENDER' => 'perempuan'])
        ->andReturn(true);

    $this->app->instance(EnvironmentService::class, $mockEnvService);
    $botService = app(TelegramBotService::class);

    Cache::put('tg_auth_session_123456789', true, now()->addMinutes(15));

    $botService->handleUpdate([
        'callback_query' => [
            'id' => 'cb_gender',
            'from' => ['id' => 123456789, 'username' => 'tama'],
            'message' => ['message_id' => 90, 'chat' => ['id' => 123456789]],
            'data' => 'cfg_set_gender_female',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'answerCallbackQuery')
            && str_contains($request['text'], 'Teteh');
    });
});
