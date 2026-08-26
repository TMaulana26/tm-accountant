<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiDriverInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiCompatibleDriver implements AiDriverInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected string $model,
        protected int $timeout = 60,
        protected bool $supportsVision = false
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function supportsVision(): bool
    {
        return $this->supportsVision;
    }

    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array
    {
        $endpoint = "{$this->baseUrl}/chat/completions";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature' => 0.1,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (! empty($this->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error("OpenAI-Compatible AI Error ({$response->status()}): {$error}");
            throw new Exception("AI Provider Error ({$response->status()}): {$error}");
        }

        $data = $response->json();
        $choice = $data['choices'][0]['message'] ?? [];

        // Check for Tool Calls (Function Calling)
        if (! empty($choice['tool_calls'][0])) {
            $toolCall = $choice['tool_calls'][0];
            $functionName = $toolCall['function']['name'] ?? '';
            $rawArgs = $toolCall['function']['arguments'] ?? '{}';
            $arguments = is_array($rawArgs) ? $rawArgs : (json_decode($rawArgs, true) ?? []);

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
            'reply_text' => $choice['content'] ?? 'Pesan tidak dapat dipahami.',
            'raw_response' => $data,
        ];
    }

    public function processVision(string $imageBytes, string $mimeType = 'image/jpeg', ?string $prompt = null): string
    {
        $prompt = $prompt ?: 'Transkripsikan seluruh teks dan angka dari nota/struk atau bukti transfer ini secara lengkap dan terstruktur.';
        $base64Image = base64_encode($imageBytes);

        $endpoint = "{$this->baseUrl}/chat/completions";

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mimeType};base64,{$base64Image}",
                            ],
                        ],
                    ],
                ],
            ],
            'temperature' => 0.1,
        ];

        $headers = ['Content-Type' => 'application/json'];
        if (! empty($this->apiKey)) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new Exception('Vision OCR error: '.$response->body());
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }
}
