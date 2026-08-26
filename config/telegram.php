<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'allowed_user_ids' => array_filter(array_map('trim', explode(',', env('TELEGRAM_ALLOWED_USER_IDS', '')))),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
];
