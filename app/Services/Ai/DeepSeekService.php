<?php

namespace App\Services\Ai;

use App\Models\Account;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('deepseek.api_key', '');
        $this->baseUrl = rtrim((string) config('deepseek.base_url', 'https://api.deepseek.com'), '/');
        $this->model = (string) config('deepseek.model', 'deepseek-chat');
        $this->timeout = (int) config('deepseek.timeout', 30);
    }

    /**
     * Process natural language chat message from user and extract structured tool calls.
     *
     * @return array{
     *     intent: string,
     *     parameters: array,
     *     reply_text: ?string,
     *     raw_response: array
     * }
     */
    public function processMessage(string $userMessage, array $conversationHistory = []): array
    {
        if (empty($this->apiKey)) {
            throw new Exception('DEEPSEEK_API_KEY belum dikonfigurasi di file .env.');
        }

        $systemPrompt = $this->buildSystemPrompt();

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($conversationHistory as $hist) {
            $messages[] = $hist;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $tools = $this->getToolsSchema();

        $response = Http::timeout($this->timeout)
            ->withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.1, // Lower temperature for reliable extraction
            ]);

        if (! $response->successful()) {
            Log::error('DeepSeek API Error: '.$response->body());
            throw new Exception('Gagal menghubungi DeepSeek AI: '.($response->json('error.message') ?? $response->status()));
        }

        $data = $response->json();
        $choice = $data['choices'][0]['message'] ?? [];

        // Check if a tool was called
        if (! empty($choice['tool_calls'])) {
            $toolCall = $choice['tool_calls'][0];
            $functionName = $toolCall['function']['name'] ?? 'unknown';
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

            return [
                'intent' => $functionName,
                'parameters' => $arguments,
                'reply_text' => $choice['content'] ?? null,
                'raw_response' => $data,
            ];
        }

        // Standard text response without tool call
        return [
            'intent' => 'general_chat',
            'parameters' => [],
            'reply_text' => $choice['content'] ?? 'Pesan tidak dapat dipahami.',
            'raw_response' => $data,
        ];
    }

    /**
     * Build system prompt with current date, time, and dynamic Chart of Accounts snapshot.
     */
    protected function buildSystemPrompt(): string
    {
        $now = now()->setTimezone('Asia/Jakarta');
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        $cashAccounts = $accounts->where('category.value', 'cash_and_bank')->map(fn ($a) => "- [{$a->code}] {$a->name}")->implode("\n");
        $expenseAccounts = $accounts->where('type.value', 'expense')->map(fn ($a) => "- [{$a->code}] {$a->name}")->implode("\n");
        $revenueAccounts = $accounts->where('type.value', 'revenue')->map(fn ($a) => "- [{$a->code}] {$a->name}")->implode("\n");
        $liabilityAccounts = $accounts->where('type.value', 'liability')->map(fn ($a) => "- [{$a->code}] {$a->name}")->implode("\n");

        return <<<PROMPT
Anda adalah asisten AI akuntan pribadi cerdas untuk aplikasi pencatatan keuangan pribadi.
Tugas Anda adalah memahami pesan pengguna dalam bahasa Indonesia (termasuk bahasa gaul/slang seperti "25k" = 25000, "1.5jt" = 1500000, "gocap" = 50000, "cepek" = 100000, "bayar qris bca", dll.) dan memanggil fungsi (tool call) yang tepat.

Waktu Sekarang: {$now->translatedFormat('l, d F Y H:i:s')} (WIB)

DAFTAR AKUN (CHART OF ACCOUNTS) AKTIF:
--- AKUN KAS & BANK (SUMBER/TUJUAN PEMBAYARAN) ---
{$cashAccounts}

--- AKUN BEBAN / PENGELUARAN ---
{$expenseAccounts}

--- AKUN PENDAPATAN ---
{$revenueAccounts}

--- AKUN KEWAJIBAN / HUTANG ---
{$liabilityAccounts}

1. PANGGILAN & GAYA BAHASA:
   - Nama pemilik/pengguna buku kas ini adalah: **Kang Tama**.
   - Selalu panggil pengguna dengan sapaan akrab yang ramah dan santun: **"Kang Tama"** atau **"Kang"** (JANGAN pernah gunakan sapaan "Mas").
2. Jika pengguna mencatat pengeluaran (misal: "beli telur 25k", "bensin 50rb pake bca", "makan siang 35k"), panggil `record_expense`.
   - Pilih `expense_account` yang paling relevan dari daftar akun beban di atas. Jika tidak ada yang cocok, gunakan nama kategori baru yang spesifik.
   - Jika pengguna menyebutkan rekening/dompet (misal: "BCA", "Mandiri", "Gopay", "Tunai"), set `payment_account` ke akun tersebut. Jika tidak disebutkan, kosongkan agar sistem menggunakan default (Kas Tunai).
   - Tentukan `amount` dalam angka murni (misal: 25000).
3. Jika pengguna mencatat pemasukan (misal: "gaji masuk 15jt ke bca", "dapat bonus 500k", "hasil jualan 2jt"), panggil `record_income`.
4. Jika pengguna memindahkan uang antar rekening/dompet (misal: "transfer dari BCA ke Gopay 200k", "tarik tunai 500rb dari mandiri"), panggil `record_transfer`.
5. Jika pengguna menanyakan kondisi keuangan (misal: "keuangan saya 1 minggu", "berapa pengeluaran bulan ini?", "ringkasan kas hari ini", "pengeluaran makan minggu lalu"), panggil `query_financial_summary`.
6. BATASAN & KEAMANAN (GUARDRAILS):
   - Kamu adalah asisten khusus PENCATATAN KEUANGAN PRIBADI.
   - Jika pengguna mengirim pesan di luar topik keuangan (misalnya meminta resep makanan, coding/programming, dongeng/cerita, opini politik, gosip, atau pertanyaan non-keuangan lainnya), ATAU jika pesan berupa teks panjang yang tidak memuat transaksi keuangan, JANGAN panggil tool transaksi apapun.
   - Berikan respon ramah yang menegaskan bahwa Anda hanya melayani pencatatan keuangan (pemasukan, pengeluaran, transfer, dan laporan).
PROMPT;
    }

    /**
     * Define OpenAPI Tools schema for DeepSeek.
     */
    protected function getToolsSchema(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_expense',
                    'description' => 'Mencatat transaksi pengeluaran / beban keuangan baru ke dalam sistem buku besar akuntansi.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal pengeluaran dalam Rupiah (contoh: 25000, 150000)',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan transaksi (contoh: Beli telur 1 kg, Makan siang nasi padang)',
                            ],
                            'expense_account' => [
                                'type' => 'string',
                                'description' => 'Nama akun beban/pengeluaran yang sesuai dari daftar akun (contoh: Makanan & Minuman (Harian), Transportasi & Bensin, Belanja Dapur & Groceries)',
                            ],
                            'payment_account' => [
                                'type' => 'string',
                                'description' => 'Nama dompet atau rekening sumber pembayaran jika disebutkan (contoh: Bank BCA, Kas Tunai (Dompet), E-Wallet (GoPay / OVO / Dana)). Kosongkan jika tidak disebut.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD (default hari ini jika tidak disebut)',
                            ],
                        ],
                        'required' => ['amount', 'description'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_income',
                    'description' => 'Mencatat transaksi pemasukan / pendapatan keuangan baru ke dalam sistem.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal pemasukan dalam Rupiah (contoh: 5000000, 250000)',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan sumber pendapatan (contoh: Gaji bulanan, Fee freelance website)',
                            ],
                            'income_account' => [
                                'type' => 'string',
                                'description' => 'Nama akun pendapatan (contoh: Gaji & Tunjangan, Pendapatan Freelance / Proyek, Pendapatan Lainnya)',
                            ],
                            'deposit_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet penerima (contoh: Bank BCA, Bank Mandiri, Kas Tunai)',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD',
                            ],
                        ],
                        'required' => ['amount', 'description'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_transfer',
                    'description' => 'Mencatat pemindahan dana atau transfer uang antar rekening/dompet.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal yang ditransfer dalam Rupiah',
                            ],
                            'from_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet sumber pengirim (contoh: Bank BCA, Bank Mandiri)',
                            ],
                            'to_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet tujuan penerima (contoh: E-Wallet (GoPay / OVO / Dana), Kas Tunai)',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan transfer (contoh: Top up Gopay dari BCA, Tarik tunai dari Mandiri)',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD',
                            ],
                        ],
                        'required' => ['amount', 'from_account', 'to_account'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_financial_summary',
                    'description' => 'Menanyakan data laporan keuangan, saldo, total pengeluaran/pemasukan berdasarkan rentang waktu.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period' => [
                                'type' => 'string',
                                'enum' => ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year', 'custom'],
                                'description' => 'Periode waktu yang ditanyakan',
                            ],
                            'start_date' => [
                                'type' => 'string',
                                'description' => 'Tanggal awal format YYYY-MM-DD jika custom',
                            ],
                            'end_date' => [
                                'type' => 'string',
                                'description' => 'Tanggal akhir format YYYY-MM-DD jika custom',
                            ],
                            'filter_category' => [
                                'type' => 'string',
                                'description' => 'Kategori atau akun khusus jika user menanyakan pengeluaran tertentu (contoh: makanan, bensin, hiburan)',
                            ],
                        ],
                        'required' => ['period'],
                    ],
                ],
            ],
        ];
    }
}
