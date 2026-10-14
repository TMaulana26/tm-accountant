<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramBotService $telegramBotService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('telegram.webhook_secret', '');
        if ($configuredSecret !== '') {
            $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($configuredSecret, $receivedSecret)) {
                Log::warning('Telegram webhook secret mismatch.');

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } elseif (app()->isProduction()) {
            Log::warning('Telegram webhook rejected: TELEGRAM_WEBHOOK_SECRET is not configured in production.');

            return response()->json(['error' => 'Webhook secret not configured'], 401);
        }

        $update = $request->all();

        if (! empty($update)) {
            try {
                $this->telegramBotService->handleUpdate($update);
            } catch (\Throwable $e) {
                Log::error('Telegram Webhook Unhandled Exception: '.$e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
