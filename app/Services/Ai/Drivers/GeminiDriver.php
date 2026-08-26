<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiDriver implements AiDriverInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $model = 'gemini-3.7-flash',
        protected int $timeout = 60,
        protected bool $supportsVision = true
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function supportsVision(): bool
    {
        return $this->supportsVision;
    }

    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array
    {
        $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => "{$systemPrompt}\n\nPesan Pengguna: {$userMessage}"],
                ],
            ],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.1,
            ],
        ];

        if (! empty($tools)) {
            $functionDeclarations = [];
            foreach ($tools as $tool) {
                if (isset($tool['function'])) {
                    $functionDeclarations[] = [
                        'name' => $tool['function']['name'],
                        'description' => $tool['function']['description'] ?? '',
                        'parameters' => $tool['function']['parameters'] ?? (object) [],
                    ];
                }
            }

            if (! empty($functionDeclarations)) {
                $payload['tools'] = [
                    ['functionDeclarations' => $functionDeclarations],
                ];
            }
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("Gemini AI Error ({$response->status()}): {$error}");
            throw new Exception("Gemini API Error ({$response->status()}): {$error}");
        }

        $data = $response->json();
        $candidate = $data['candidates'][0]['content']['parts'][0] ?? [];

        // Check for function call
        if (! empty($candidate['functionCall'])) {
            $functionName = $candidate['functionCall']['name'] ?? '';
            $arguments = $candidate['functionCall']['args'] ?? [];

            return [
                'intent' => $functionName,
                'parameters' => $arguments,
                'reply_text' => null,
                'raw_response' => $data,
            ];
        }

        return [
            'intent' => 'general_chat',
            'parameters' => [],
            'reply_text' => $candidate['text'] ?? 'Pesan tidak dapat dipahami.',
            'raw_response' => $data,
        ];
    }

    public function processVision(string $imageBytes, string $mimeType = 'image/jpeg', ?string $prompt = null): string
    {
        $prompt = $prompt ?: 'Transkripsikan seluruh teks dan angka dari nota/struk atau bukti transfer ini secara lengkap dan terstruktur.';
        $base64Data = base64_encode($imageBytes);

        $endpoint = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Data,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
            ],
        ];

        $modelsToTry = array_unique([$this->model, 'gemini-3.1-flash-lite', 'gemini-3.6-flash', 'gemini-3.7-flash', 'gemini-3.5-flash']);
        $lastException = null;

        foreach ($modelsToTry as $modelName) {
            try {
                $tryEndpoint = "{$this->baseUrl}/models/{$modelName}:generateContent?key={$this->apiKey}";

                $generationConfig = ['temperature' => 0.1];
                if (str_contains($modelName, '3.7')) {
                    $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
                }

                $modelPayload = [
                    'contents' => $payload['contents'],
                    'generationConfig' => $generationConfig,
                ];

                $response = Http::timeout($this->timeout)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($tryEndpoint, $modelPayload);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (! empty($text)) {
                        return trim($text);
                    }
                }

                $error = $response->json('error.message') ?? $response->body();
                Log::warning("Gemini model {$modelName} vision failed ({$response->status()}): {$error}");
                $lastException = new Exception("Gemini Vision Error ({$response->status()}): {$error}");

                if (in_array($response->status(), [404, 429, 500, 502, 503, 504])) {
                    continue;
                }
                break;
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?: new Exception('Gagal melakukan OCR dengan Gemini.');
    }
}
