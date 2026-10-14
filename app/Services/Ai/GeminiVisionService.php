<?php

namespace App\Services\Ai;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiVisionService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('gemini.api_key', '');
        $this->baseUrl = rtrim((string) config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->model = (string) config('gemini.model', 'gemini-3.5-flash-lite');
        $this->timeout = (int) config('gemini.timeout', 30);
    }

    /**
     * Extract full text, receipt details, and transfer information from an image using Gemini Vision.
     */
    public function extractTextFromImage(string $imageBytes, string $mimeType = 'image/jpeg'): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('GEMINI_API_KEY belum dikonfigurasi di file .env.');
        }

        $base64Data = base64_encode($imageBytes);

        $prompt = <<<'PROMPT'
Kamu adalah asisten OCR spesialis pembacaan dokumen finansial, struk belanja, nota kasir, bon toko, dan screenshot bukti transfer bank / e-wallet di Indonesia.

Tugasmu:
Transkripsikan SELURUH teks dan angka yang tertera pada gambar ini secara lengkap, teliti, dan terstruktur.

Pastikan mencakup informasi penting berikut:
1. Toko / Merchant / Penerima: (contoh: Indomaret, Alfamart, Warung Makan, Nama Penerima Transfer)
2. Aplikasi / Sumber Dana Pengirim (PENTING untuk screenshot m-Banking / E-Wallet):
   - Jika terlihat kata "Kantong", "Dari Kantong", "Jago", atau format ID Transaksi Bank Jago, WAJIB tulis jelas: "Sumber Dana: Bank Jago".
   - Jika terlihat "ShopeePay", "Saldo ShopeePay", atau rincian pembayaran Shopee, WAJIB tulis jelas: "Sumber Dana: ShopeePay".
   - Jika terlihat "GoPay", "DANA", "OVO", "BCA", "myBCA", "Livin Mandiri", "BRImo", "BSI", atau "SeaBank", WAJIB tulis jelas nama sumber dananya.
3. Tanggal & Waktu Transaksi: (contoh: 26 Agustus 2026 14:30)
4. Rincian Item / Barang / Catatan Transfer: (daftar nama produk, qty, harga satuan, atau catatan/berita transfer)
5. Total Nominal: (Total Belanja / Nominal Transfer / Tagihan Akhir)
6. Metode Pembayaran: (Tunai, Bank Jago, ShopeePay, GoPay, DANA, Debit BCA, QRIS)
7. Nomor Referensi / No. Struk: (jika tertera)

Keluarkan hasil transkripsi teks yang rapi dan mudah dipahami oleh sistem akuntansi.
PROMPT;

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
                $endpoint = "{$this->baseUrl}/models/{$modelName}:generateContent";

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
                    ->post($endpoint, $modelPayload);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if (! empty($text)) {
                        return trim($text);
                    }
                }

                $errorBody = $response->json('error.message') ?? $response->body();
                Log::warning("Gemini Vision OCR attempt with model {$modelName} failed ({$response->status()}): {$errorBody}");
                $lastException = new Exception("Gemini API Error ({$response->status()}): {$errorBody}");

                // If error is 404 (not found), 503 (high demand), or 429 (rate limited), loop to try next fallback model
                if (in_array($response->status(), [404, 429, 500, 502, 503, 504])) {
                    continue;
                }

                // If error is invalid API key or bad request, don't keep looping
                if ($response->status() === 400 || $response->status() === 403) {
                    break;
                }
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning("Gemini Vision exception with model {$modelName}: ".$e->getMessage());
            }
        }

        throw $lastException ?: new Exception('Gagal melakukan OCR gambar dengan Gemini Vision.');
    }
}
