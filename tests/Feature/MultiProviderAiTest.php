<?php

use App\Services\Ai\AiServiceManager;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OpenAiCompatibleDriver;
use App\Services\Ai\GeminiVisionService;
use Database\Seeders\AccountSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(AccountSeeder::class);
});

test('AiServiceManager resolves correct drivers based on config', function () {
    $manager = app(AiServiceManager::class);

    expect($manager->driver('deepseek'))->toBeInstanceOf(OpenAiCompatibleDriver::class)
        ->and($manager->driver('ollama'))->toBeInstanceOf(OpenAiCompatibleDriver::class)
        ->and($manager->driver('openai'))->toBeInstanceOf(OpenAiCompatibleDriver::class)
        ->and($manager->driver('gemini'))->toBeInstanceOf(GeminiDriver::class);
});

test('AiServiceManager processes message using OpenAI-compatible driver with tool call', function () {
    Config::set('ai.default', 'ollama');
    Config::set('ai.providers.ollama.base_url', 'http://localhost:11434/v1');
    Config::set('ai.providers.ollama.api_key', 'test-key');
    Config::set('ai.providers.ollama.model', 'llama3.3');

    Http::fake([
        'http://localhost:11434/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'record_expense',
                                    'arguments' => json_encode([
                                        'amount' => 50000,
                                        'description' => 'Bensin motor',
                                        'expense_account' => 'Bahan Bakar & Bensin',
                                        'payment_account' => 'Bank BCA',
                                        'date' => '2026-08-26',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $manager = app(AiServiceManager::class);
    $result = $manager->processMessage('bensin 50rb pake bca');

    expect($result['intent'])->toBe('record_expense')
        ->and($result['parameters']['amount'])->toBe(50000)
        ->and($result['parameters']['description'])->toBe('Bensin motor');
});

test('AiServiceManager processes receipt image via hybrid OCR pipeline', function () {
    Config::set('ai.default', 'deepseek');
    Config::set('ai.ocr_mode', 'gemini');

    // 1. Mock Gemini OCR
    $mockGemini = mock(GeminiVisionService::class);
    $mockGemini->shouldReceive('extractTextFromImage')
        ->once()
        ->andReturn("INDOMARET\nTotal: Rp 35.000");

    $this->app->instance(GeminiVisionService::class, $mockGemini);

    // 2. Mock DeepSeek chat completion
    Http::fake([
        'https://api.deepseek.com/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_456',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'record_expense',
                                    'arguments' => json_encode([
                                        'amount' => 35000,
                                        'description' => 'Belanja Indomaret',
                                        'expense_account' => 'Belanja Dapur & Groceries',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $manager = app(AiServiceManager::class);
    $result = $manager->processReceiptImage('mock_image_bytes', 'image/jpeg', 'bayar tunai');

    expect($result['intent'])->toBe('record_expense')
        ->and($result['parameters']['amount'])->toBe(35000)
        ->and($result['ocr_text'])->toContain('INDOMARET');
});
