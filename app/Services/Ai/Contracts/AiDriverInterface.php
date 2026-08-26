<?php

namespace App\Services\Ai\Contracts;

interface AiDriverInterface
{
    /**
     * Send chat completion request with system prompt, user message, and tools.
     *
     * @param  string  $systemPrompt  System persona and context
     * @param  string  $userMessage  User prompt or receipt transcription
     * @param  array  $tools  OpenAPI-compatible tools definition
     * @return array{intent: string, parameters: array, reply_text: ?string, raw_response: array}
     */
    public function chat(string $systemPrompt, string $userMessage, array $tools = []): array;

    /**
     * Check if this driver supports vision/multimodal input directly.
     */
    public function supportsVision(): bool;

    /**
     * Extract or analyze text directly from image bytes if multimodal.
     */
    public function processVision(string $imageBytes, string $mimeType = 'image/jpeg', ?string $prompt = null): string;
}
