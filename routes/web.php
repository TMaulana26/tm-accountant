<?php

use App\Http\Controllers\Auth\PasskeyAuthController;
use App\Http\Controllers\Telegram\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/api/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Passkey & Biometric Routes
Route::prefix('auth/passkey')->name('passkey.')->group(function () {
    Route::get('/login-options', [PasskeyAuthController::class, 'loginOptions'])->name('login-options');
    Route::post('/login', [PasskeyAuthController::class, 'login'])->name('login');

    Route::middleware('auth')->group(function () {
        Route::get('/register-options', [PasskeyAuthController::class, 'registerOptions'])->name('register-options');
        Route::post('/register', [PasskeyAuthController::class, 'register'])->name('register');
        Route::post('/clear', [PasskeyAuthController::class, 'clear'])->name('clear');
    });
});
