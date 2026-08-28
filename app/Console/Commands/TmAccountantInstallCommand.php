<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TmAccountantInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tmaccountant {--force : Force overwrite without prompts}';

    /**
     * The aliases of the console command.
     */
    protected $aliases = ['tmaccountant:install'];

    /**
     * The console command description.
     */
    protected $description = 'Interactive setup and installation wizard for TM Accountant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('');
        $this->info('================================================================');
        $this->info('    🌟 WELCOME TO TM ACCOUNTANT (OPEN SOURCE ACCOUNTING AI) 🌟   ');
        $this->info('================================================================');
        $this->line('');

        $envUpdates = [];

        // 1. Prepare Environment & Database
        $this->ensureEnvironmentFile();
        $this->ensureApplicationKey();
        $this->ensureStorageLink();
        $this->ensureDatabaseMigration();

        // 2. Setup Admin User Account
        $this->line('');
        $this->comment('----------------------------------------------------------------');
        $this->info(' [1/3] 👤 ADMIN ACCOUNT SETUP');
        $this->comment('----------------------------------------------------------------');

        $adminName = $this->ask('Admin / Owner Name', 'Admin');
        $adminEmail = $this->ask('Admin Login Email', 'admin@example.com');
        $adminPassword = $this->secret('Admin Login Password [default: password123]');

        if (empty($adminPassword)) {
            $adminPassword = 'password123';
        }

        $user = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
            ]
        );

        $this->info("✓ Admin account [{$user->name} ({$user->email})] is ready.");

        // 3. Setup Telegram Bot Integration
        $this->line('');
        $this->comment('----------------------------------------------------------------');
        $this->info(' [2/3] 📱 TELEGRAM BOT SETUP');
        $this->comment('----------------------------------------------------------------');

        $configureTelegram = $this->confirm('Would you like to configure the Telegram Bot now?', true);

        if ($configureTelegram) {
            $botToken = $this->ask('Enter Telegram Bot Token (from @BotFather)');
            $userId = $this->ask('Enter Whitelisted Telegram User ID(s) (from @userinfobot, comma-separated)');

            if (! empty($botToken)) {
                $envUpdates['TELEGRAM_BOT_TOKEN'] = $botToken;
            }
            if (! empty($userId)) {
                $envUpdates['TELEGRAM_ALLOWED_USER_IDS'] = $userId;
            }
        }

        // 4. Setup AI Provider & Vision OCR
        $this->line('');
        $this->comment('----------------------------------------------------------------');
        $this->info(' [3/3] 🤖 AI PROVIDER & VISION OCR SETUP');
        $this->comment('----------------------------------------------------------------');

        $configureAi = $this->confirm('Would you like to configure the AI Provider now?', true);

        if ($configureAi) {
            $providerOptions = [
                'deepseek' => 'DeepSeek AI (Cloud API - High Intelligence & Ultra Low Cost)',
                'bai' => 'B.AI (DeepSeek Vision Experimental - deepseek-v4-flash-vision-exp)',
                'ollama' => 'Ollama (Local / 100% Offline & Free - Llama 3.3, Qwen 2.5)',
                'gemini' => 'Google Gemini (Cloud API - gemini-3.7-flash, gemini-3.1-flash-lite)',
                'openai' => 'OpenAI (Cloud API - gpt-4o, gpt-4o-mini)',
                'groq' => 'Groq (Cloud API - Ultra Fast Llama 3.3)',
                'openrouter' => 'OpenRouter (Multi-Model Cloud Gateway)',
                'lmstudio' => 'LM Studio / vLLM (Local OpenAI-compatible server)',
                'custom' => 'Custom Endpoint (OpenAI-compatible)',
            ];

            $provider = $this->choice(
                'Select AI Provider:',
                array_keys($providerOptions),
                0
            );

            $envUpdates['AI_PROVIDER'] = $provider;

            switch ($provider) {
                case 'deepseek':
                    $apiKey = $this->ask('Enter DeepSeek API Key (sk-...)');
                    if ($apiKey) {
                        $envUpdates['DEEPSEEK_API_KEY'] = $apiKey;
                        $envUpdates['AI_API_KEY'] = $apiKey;
                    }
                    break;

                case 'bai':
                    $apiKey = $this->ask('Enter B.AI API Key');
                    $model = $this->ask('Enter B.AI Model Name', 'deepseek-v4-flash-vision-exp');
                    if ($apiKey) {
                        $envUpdates['BAI_API_KEY'] = $apiKey;
                        $envUpdates['AI_API_KEY'] = $apiKey;
                    }
                    $envUpdates['BAI_BASE_URL'] = 'https://api.b.ai/v1';
                    $envUpdates['BAI_MODEL'] = $model;
                    break;

                case 'ollama':
                    $baseUrl = $this->ask('Enter Ollama API Base URL', 'http://localhost:11434/v1');
                    $model = $this->ask('Enter Ollama Model Name (ensure pulled, e.g. llama3.3, qwen2.5)', 'llama3.3');
                    $envUpdates['OLLAMA_BASE_URL'] = $baseUrl;
                    $envUpdates['OLLAMA_MODEL'] = $model;
                    break;

                case 'gemini':
                    $apiKey = $this->ask('Enter Google Gemini API Key');
                    $model = $this->ask('Gemini Model Name', 'gemini-3.7-flash');
                    if ($apiKey) {
                        $envUpdates['GEMINI_API_KEY'] = $apiKey;
                    }
                    $envUpdates['GEMINI_MODEL'] = $model;
                    break;

                case 'openai':
                    $apiKey = $this->ask('Enter OpenAI API Key (sk-proj-...)');
                    $model = $this->ask('OpenAI Model Name', 'gpt-4o-mini');
                    if ($apiKey) {
                        $envUpdates['OPENAI_API_KEY'] = $apiKey;
                    }
                    $envUpdates['OPENAI_MODEL'] = $model;
                    break;

                case 'groq':
                    $apiKey = $this->ask('Enter Groq API Key (gsk_...)');
                    if ($apiKey) {
                        $envUpdates['GROQ_API_KEY'] = $apiKey;
                    }
                    break;

                case 'openrouter':
                    $apiKey = $this->ask('Enter OpenRouter API Key (sk-or-...)');
                    $model = $this->ask('OpenRouter Model Name', 'anthropic/claude-3.5-sonnet');
                    if ($apiKey) {
                        $envUpdates['OPENROUTER_API_KEY'] = $apiKey;
                    }
                    $envUpdates['OPENROUTER_MODEL'] = $model;
                    break;

                case 'lmstudio':
                case 'custom':
                    $baseUrl = $this->ask('Enter API Base URL (OpenAI-compatible)', 'http://localhost:1234/v1');
                    $apiKey = $this->ask('API Key (leave blank if not required)', 'local-key');
                    $model = $this->ask('Model Name', 'local-model');
                    $envUpdates['CUSTOM_AI_BASE_URL'] = $baseUrl;
                    $envUpdates['CUSTOM_AI_API_KEY'] = $apiKey;
                    $envUpdates['CUSTOM_AI_MODEL'] = $model;
                    break;
            }

            // OCR Strategy
            $ocrChoice = $this->choice(
                'Select Vision OCR Strategy for Receipts & Invoice Screenshots:',
                [
                    'gemini' => 'Gemini Free Tier (Recommended - Cost-Effective & High Accuracy)',
                    'auto' => 'Use Primary AI Model (If primary model supports multimodal vision)',
                    'disabled' => 'Disable Receipt Image Processing (Text Only)',
                ],
                'gemini'
            );

            $envUpdates['AI_OCR_MODE'] = $ocrChoice;

            if ($ocrChoice === 'gemini' && empty($envUpdates['GEMINI_API_KEY'])) {
                $geminiKey = $this->ask('Enter Gemini API Key for OCR (Get free key at Google AI Studio)');
                if ($geminiKey) {
                    $envUpdates['GEMINI_API_KEY'] = $geminiKey;
                }
            }
        } else {
            $this->line('');
            $this->warn('💡 Manual AI Configuration Hint:');
            $this->line('You can configure AI settings anytime in your .env file:');
            $this->line('- AI_PROVIDER=deepseek (or ollama / openai / gemini / groq)');
            $this->line('- DEEPSEEK_API_KEY=sk-...');
            $this->line('- GEMINI_API_KEY=... (for receipt OCR)');
        }

        // 5. Write to .env
        if (! empty($envUpdates)) {
            $this->updateEnvironmentFile($envUpdates);
            $this->info('✓ Environment file (.env) updated successfully.');
        }

        // 6. Clear optimize cache
        Artisan::call('optimize:clear');

        $this->line('');
        $this->info('================================================================');
        $this->info('  🎉 INSTALLATION COMPLETE & APP IS READY TO USE! 🎉             ');
        $this->info('================================================================');
        $this->line('');
        $this->line('👉 Web Admin Panel : '.config('app.url', 'http://localhost:8000').'/admin');
        $this->line("👉 Login Email     : {$adminEmail}");
        $this->line('👉 Run Telegram Bot: php artisan telegram:poll');
        $this->line('👉 Run Tests       : php artisan test');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Ensure .env file exists.
     */
    protected function ensureEnvironmentFile(): void
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (! File::exists($envPath) && File::exists($envExamplePath)) {
            File::copy($envExamplePath, $envPath);
            $this->info('✓ Created .env file from .env.example.');
        }
    }

    /**
     * Ensure APP_KEY exists.
     */
    protected function ensureApplicationKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('✓ Application key generated successfully.');
        }
    }

    /**
     * Ensure storage symlink exists.
     */
    protected function ensureStorageLink(): void
    {
        if (! File::exists(public_path('storage'))) {
            Artisan::call('storage:link');
            $this->info('✓ Storage symlink created successfully.');
        }
    }

    /**
     * Ensure database migration and default seeder.
     */
    protected function ensureDatabaseMigration(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            if (! Schema::hasTable('accounts') || Account::count() === 0) {
                Artisan::call('db:seed', ['--class' => AccountSeeder::class, '--force' => true]);
            }
        } catch (Exception $e) {
            // Silently proceed
        }
    }

    /**
     * Update environment file values safely.
     */
    protected function updateEnvironmentFile(array $values): void
    {
        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            return;
        }

        $envContent = File::get($envPath);

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $value = trim($value);
            if (str_contains($value, ' ') && ! str_starts_with($value, '"')) {
                $value = '"'.$value.'"';
            }

            if (preg_match("/^{$key}=/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        File::put($envPath, $envContent);
    }
}
