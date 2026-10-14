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
        protected string $model = 'gemini-3.5-flash-lite',
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

        $modelsToTry = array_values(array_unique([
            $this->model,
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
        ]));

        $lastException = null;
        $data = null;

        foreach ($modelsToTry as $idx => $modelName) {
            $endpoint = "{$this->baseUrl}/models/{$modelName}:generateContent";

            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $this->apiKey,
                    ])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    break;
                }

                $status = $response->status();
                $error = $response->json('error.message') ?? $response->body();
                Log::warning("Gemini AI chat attempt with model {$modelName} failed ({$status}): {$error}");
                $lastException = new Exception("Gemini API Error ({$status}): {$error}");

                if (in_array($status, [404, 429, 500, 502, 503, 504], true) && $idx < count($modelsToTry) - 1) {
                    continue;
                }

                break;
            } catch (Exception $e) {
                Log::warning("Gemini AI chat exception with model {$modelName}: ".$e->getMessage());
                $lastException = $e;

                if ($idx < count($modelsToTry) - 1) {
                    continue;
                }
            }
        }

        if ($data === null) {
            if ($lastException) {
                Log::error($lastException->getMessage());
                throw $lastException;
            }
            throw new Exception('Gagal menghubungi layanan Gemini AI.');
        }

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

        $modelsToTry = array_unique([$this->model, 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-3.5-flash-lite']);
        $lastException = null;
        $startTime = microtime(true);
        $maxTotalDuration = $this->timeout;

        foreach ($modelsToTry as $modelName) {
            $elapsed = microtime(true) - $startTime;
            $remaining = (int) ($maxTotalDuration - $elapsed);
            if ($remaining < 5) {
                break;
            }

            try {
                $tryEndpoint = "{$this->baseUrl}/models/{$modelName}:generateContent";

                $generationConfig = ['temperature' => 0.1];

                $modelPayload = [
                    'contents' => $payload['contents'],
                    'generationConfig' => $generationConfig,
                ];

                $response = Http::timeout($remaining)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $this->apiKey,
                    ])
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
