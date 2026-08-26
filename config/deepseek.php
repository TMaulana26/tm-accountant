<?php

return [
    'api_key' => env('DEEPSEEK_API_KEY'),
    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
    'timeout' => env('DEEPSEEK_TIMEOUT', 30),
];
