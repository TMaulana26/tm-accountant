<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: "deepseek", "ollama", "openai", "gemini",
    |                      "groq", "openrouter", "lmstudio", "custom"
    |
    */
    'default' => env('AI_PROVIDER', 'deepseek'),

    /*
    |--------------------------------------------------------------------------
    | General Timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('AI_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | OCR Vision Strategy
    |--------------------------------------------------------------------------
    |
    | "gemini"   : Use dedicated Gemini Vision (Free Tier recommended) for OCR stage 1,
    |              then pass structured text to main LLM.
    | "auto"     : If main LLM supports vision, use it directly. Otherwise fallback to Gemini.
    | "disabled" : Disable image receipt processing.
    |
    */
    'ocr_mode' => env('AI_OCR_MODE', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'deepseek' => [
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key' => env('DEEPSEEK_API_KEY', env('AI_API_KEY', '')),
            'model' => env('DEEPSEEK_MODEL', env('AI_MODEL', 'deepseek-chat')),
            'supports_vision' => false,
            'driver' => 'openai_compatible',
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', env('AI_BASE_URL', 'http://localhost:11434/v1')),
            'api_key' => env('OLLAMA_API_KEY', env('AI_API_KEY', 'ollama')),
            'model' => env('OLLAMA_MODEL', env('AI_MODEL', 'llama3.3')),
            'supports_vision' => (bool) env('OLLAMA_VISION', false),
            'driver' => 'openai_compatible',
        ],

        'lmstudio' => [
            'base_url' => env('LMSTUDIO_BASE_URL', env('AI_BASE_URL', 'http://localhost:1234/v1')),
            'api_key' => env('LMSTUDIO_API_KEY', env('AI_API_KEY', 'lm-studio')),
            'model' => env('LMSTUDIO_MODEL', env('AI_MODEL', 'local-model')),
            'supports_vision' => (bool) env('LMSTUDIO_VISION', false),
            'driver' => 'openai_compatible',
        ],

        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', env('AI_BASE_URL', 'https://api.openai.com/v1')),
            'api_key' => env('OPENAI_API_KEY', env('AI_API_KEY', '')),
            'model' => env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4o-mini')),
            'supports_vision' => true,
            'driver' => 'openai_compatible',
        ],

        'gemini' => [
            'base_url' => env('GEMINI_BASE_URL', env('AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta')),
            'api_key' => env('GEMINI_API_KEY', env('AI_API_KEY', '')),
            'model' => env('GEMINI_MODEL', env('AI_MODEL', 'gemini-3.7-flash')),
            'supports_vision' => true,
            'driver' => 'gemini',
        ],

        'groq' => [
            'base_url' => env('GROQ_BASE_URL', env('AI_BASE_URL', 'https://api.groq.com/openai/v1')),
            'api_key' => env('GROQ_API_KEY', env('AI_API_KEY', '')),
            'model' => env('GROQ_MODEL', env('AI_MODEL', 'llama-3.3-70b-versatile')),
            'supports_vision' => false,
            'driver' => 'openai_compatible',
        ],

        'openrouter' => [
            'base_url' => env('OPENROUTER_BASE_URL', env('AI_BASE_URL', 'https://openrouter.ai/api/v1')),
            'api_key' => env('OPENROUTER_API_KEY', env('AI_API_KEY', '')),
            'model' => env('OPENROUTER_MODEL', env('AI_MODEL', 'anthropic/claude-3.5-sonnet')),
            'supports_vision' => true,
            'driver' => 'openai_compatible',
        ],

        'custom' => [
            'base_url' => env('CUSTOM_AI_BASE_URL', env('AI_BASE_URL', 'http://localhost:8000/v1')),
            'api_key' => env('CUSTOM_AI_API_KEY', env('AI_API_KEY', '')),
            'model' => env('CUSTOM_AI_MODEL', env('AI_MODEL', 'custom-model')),
            'supports_vision' => (bool) env('CUSTOM_AI_VISION', false),
            'driver' => 'openai_compatible',
        ],

    ],

];
