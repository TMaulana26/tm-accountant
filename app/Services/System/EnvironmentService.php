<?php

namespace App\Services\System;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class EnvironmentService
{
    /**
     * Allowed environment keys that can be modified via Telegram / Wizard.
     */
    protected const ALLOWED_KEYS = [
        'APP_NAME',
        'APP_OWNER_NAME',
        'APP_OWNER_GENDER',
        'AI_PROVIDER',
        'AI_TIMEOUT',
        'AI_OCR_MODE',
        'AI_MODEL',
        'AI_API_KEY',
        'AI_BASE_URL',
        'BAI_API_KEY',
        'BAI_BASE_URL',
        'BAI_MODEL',
        'OPENROUTER_API_KEY',
        'OPENROUTER_MODEL',
        'OPENROUTER_BASE_URL',
        'DEEPSEEK_API_KEY',
        'DEEPSEEK_MODEL',
        'DEEPSEEK_BASE_URL',
        'GEMINI_API_KEY',
        'GEMINI_MODEL',
        'GEMINI_BASE_URL',
        'OPENAI_API_KEY',
        'OPENAI_MODEL',
        'OPENAI_BASE_URL',
        'GROQ_API_KEY',
        'GROQ_MODEL',
        'GROQ_BASE_URL',
        'OLLAMA_BASE_URL',
        'OLLAMA_MODEL',
        'OLLAMA_API_KEY',
        'LMSTUDIO_BASE_URL',
        'LMSTUDIO_API_KEY',
        'LMSTUDIO_MODEL',
        'CUSTOM_AI_BASE_URL',
        'CUSTOM_AI_API_KEY',
        'CUSTOM_AI_MODEL',
        'TELEGRAM_BOT_TOKEN',
        'TELEGRAM_ALLOWED_USER_IDS',
        'TELEGRAM_WEBHOOK_SECRET',
    ];

    /**
     * Update environment file values safely.
     *
     * @param  array<string, string|null>  $values
     */
    public function update(array $values, ?string $customEnvPath = null): bool
    {
        $envPath = $customEnvPath ?? base_path('.env');
        if (! File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            $key = strtoupper(trim($key));

            // Whitelist check
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                Log::warning("EnvironmentService: Attempt to update disallowed key [{$key}]");

                continue;
            }

            if ($value === null) {
                continue;
            }

            // Strip CR/LF characters to prevent arbitrary multiline .env variable injection
            $value = str_replace(["\r", "\n"], '', trim($value));
            if ((str_contains($value, ' ') || str_contains($value, '#')) && ! str_starts_with($value, '"')) {
                $escaped = str_replace('"', '\\"', $value);
                $value = '"'.$escaped.'"';
            }

            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = (string) preg_replace_callback(
                    "/^{$key}=.*/m",
                    fn () => "{$key}={$value}",
                    $envContent
                );
            } else {
                $envContent = rtrim($envContent, "\r\n")."\n{$key}={$value}\n";
            }
        }

        try {
            @chmod($envPath, 0600);
            if (@file_put_contents($envPath, $envContent, LOCK_EX) === false) {
                File::put($envPath, $envContent, true);
            }

            if ($customEnvPath === null) {
                $wasConfigCached = app()->configurationIsCached();
                Artisan::call('config:clear');
                if ($wasConfigCached) {
                    Artisan::call('config:cache');
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('EnvironmentService update error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get a specific value directly from the .env file.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return env($key, $default);
        }

        $content = File::get($envPath);
        if (preg_match("/^{$key}=(.*)$/m", $content, $matches)) {
            $val = trim($matches[1]);

            return trim($val, '"\'');
        }

        return env($key, $default);
    }

    /**
     * Mask sensitive strings (API keys, bot tokens).
     */
    public function maskSecret(?string $secret): string
    {
        if (empty($secret)) {
            return '<i>(Belum diisi)</i>';
        }

        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($secret, 0, 4).str_repeat('•', max(6, $len - 8)).substr($secret, -4);
    }

    /**
     * Check if a key is permitted to be changed.
     */
    public function isKeyAllowed(string $key): bool
    {
        return in_array(strtoupper(trim($key)), self::ALLOWED_KEYS, true);
    }
}
