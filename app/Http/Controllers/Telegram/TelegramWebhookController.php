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
        $configuredSecret = config('telegram.webhook_secret');
        if (! empty($configuredSecret)) {
            $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');
            if ($receivedSecret !== $configuredSecret) {
                Log::warning('Telegram webhook secret mismatch.');

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $update = $request->all();

        if (! empty($update)) {
            $this->telegramBotService->handleUpdate($update);
        }

        return response()->json(['ok' => true]);
    }
}
