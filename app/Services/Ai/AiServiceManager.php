<?php

namespace App\Services\Ai;

use App\Models\Account;
use App\Models\User;
use App\Services\Ai\Contracts\AiDriverInterface;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OpenAiCompatibleDriver;
use InvalidArgumentException;

class AiServiceManager
{
    protected array $drivers = [];

    /**
     * Resolve the AI driver instance.
     */
    public function driver(?string $name = null): AiDriverInterface
    {
        $name = $name ?: config('ai.default', 'deepseek');

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = config("ai.providers.{$name}");

        if (! $config) {
            throw new InvalidArgumentException("AI Provider [{$name}] is not configured in config/ai.php.");
        }

        $driverType = $config['driver'] ?? 'openai_compatible';
        $timeout = (int) ($config['timeout'] ?? config('ai.timeout', 90));

        if ($driverType === 'gemini') {
            $this->drivers[$name] = new GeminiDriver(
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'gemini-3.7-flash',
                timeout: $timeout,
                supportsVision: (bool) ($config['supports_vision'] ?? true)
            );
        } else {
            $this->drivers[$name] = new OpenAiCompatibleDriver(
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'deepseek-chat',
                timeout: $timeout,
                supportsVision: (bool) ($config['supports_vision'] ?? false)
            );
        }

        return $this->drivers[$name];
    }

    /**
     * Process natural language message via the configured AI provider.
     */
    public function processMessage(string $userMessage, ?string $provider = null): array
    {
        $driver = $this->driver($provider);
        $systemPrompt = $this->buildSystemPrompt();
        $tools = $this->getToolsSchema();

        return $driver->chat($systemPrompt, $userMessage, $tools);
    }

    /**
     * Extract receipt text and process financial transaction using Hybrid OCR pipeline.
     */
    public function processReceiptImage(string $imageBytes, string $mimeType = 'image/jpeg', ?string $caption = null): array
    {
        $ocrMode = config('ai.ocr_mode', 'gemini');
        $primaryDriver = $this->driver();
        $ocrText = '';

        if ($ocrMode === 'auto' && $primaryDriver->supportsVision()) {
            // Option A: Use primary multimodal driver directly for vision
            $ocrText = $primaryDriver->processVision($imageBytes, $mimeType);
        } else {
            // Option B (Recommended): Use dedicated Gemini Vision Free Tier for OCR
            $geminiVisionService = app(GeminiVisionService::class);
            $ocrText = $geminiVisionService->extractTextFromImage($imageBytes, $mimeType);
        }

        // Combine OCR Text with user caption
        $combinedPrompt = "Berikut adalah hasil pembacaan OCR dari struk/bukti transaksi yang dikirim pengguna:\n\n"
            ."--- HASIL OCR NOTA/STRUK/BUKTI PEMBAYARAN ---\n"
            .$ocrText."\n"
            ."--- AKHIR HASIL OCR ---\n";

        if (! empty($caption)) {
            $combinedPrompt .= "\nCatatan Tambahan / Caption dari Pengguna (PRIORITAS UTAMA):\n\"{$caption}\"\n"
                ."\n⚠️ ATURAN SINTESIS KETERANGAN (DESCRIPTION) CERDAS & PROFESIONAL:"
                ."\n1. SUMBER DANA: Jika pengguna menyebutkan rekening/dompet/kartu (misal: 'dari bank Jago', 'pakai BCA', 'dari Gopay', 'dari kartu debit', 'tunai'), kamu WAJIB menetapkan `payment_account` ke akun tersebut."
                ."\n2. FORMULASI KETERANGAN (DESCRIPTION):"
                ."   - HAPUS / BERSIHKAN frasa metode pembayaran dari caption (seperti 'dari kartu debit', 'pakai bca', 'via jago', 'dari gopay'). Frasa rekening ini TIDAK BOLEH ada di dalam kolom `description` karena sudah ada di Sumber Dana."
                .'   - GABUNGKAN maksud/keperluan caption dengan nama Merchant/Penyedia dari struk/bukti transaksi untuk menghasilkan keterangan yang informatif dan profesional.'
                ."   - Contoh: Caption 'Beli VPS ke Tencent dari kartu debit' + Struk 'Tencent Cloud InternatiSINGAPORE SG' => `description` = 'Beli VPS Tencent Cloud (Tencent Cloud Singapore)'"
                ."   - Contoh: Caption 'Makan siang bareng tim pake gopay' + Struk 'RM Padang Sederhana' => `description` = 'Makan Siang Bareng Tim di RM Padang Sederhana'"
                ."   - Contoh: Caption 'Beli bensin via bca' + Struk 'SPBU Pertamina 34.12345' => `description` = 'Beli Bensin Motor di SPBU Pertamina 34.12345'"
                ."   - Contoh: Caption 'Bayar hutang ke Tante Lany dari bank jago' => `description` = 'Bayar Hutang ke Tante Lany'"
                ."   - Contoh: Caption 'Investasi Reksadana dari bank jago' + Struk 'Stockbit Sekuritas RDN' => `description` = 'Investasi Reksadana (Stockbit Sekuritas RDN)'"
                ."\n3. AKUN BEBAN / ASET / HUTANG: Pilih akun yang paling akurat sesuai transaksi.";
        }

        $combinedPrompt .= "\n\nInstruksi: Tentukan transaksi keuangan yang sesuai (pengeluaran, pemasukan, atau transfer antar rekening), pilih akun beban/pendapatan dan sumber dana yang tepat, lalu panggil tool transaksi.";

        $aiResult = $this->processMessage($combinedPrompt);
        $aiResult['ocr_text'] = $ocrText;

        return $aiResult;
    }

    /**
     * Build dynamic system prompt with current date, time, and active Chart of Accounts.
     */
    public function buildSystemPrompt(): string
    {
        $now = now()->setTimezone('Asia/Jakarta');
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        $owner = User::first();
        $ownerName = $owner?->name ?: 'Owner';

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
   - Nama pemilik/pengguna buku kas ini adalah: **{$ownerName}**.
   - Panggil pengguna dengan sapaan akrab, ramah, dan santun (misal: "Kang {$ownerName}" atau sebutan ramah yang sesuai).
2. FORMULASI KETERANGAN (DESCRIPTION) BERSIH & INFORMATIF:
   - Kolom `description` harus ringkas, jelas, dan profesional (gunakan Title Case yang rapi).
   - JANGAN masukkan frasa metode pembayaran (seperti "pakai bca", "via jago", "dari kartu debit", "pake gopay", "tunai") ke dalam kolom `description`, karena informasi rekening sudah otomatis dicatat pada parameter `payment_account` / `deposit_account`.
   - Contoh: "beli nasi padang 25k via bca" => `description`: "Beli Nasi Padang", `payment_account`: "BCA"
   - Contoh: "bayar hutang ke tante lany dari bank jago" => `description`: "Bayar Hutang ke Tante Lany", `payment_account`: "Bank Jago"
   - Contoh: "beli bensin pertalite 50rb pake cash" => `description`: "Beli Bensin Pertalite", `payment_account`: "Kas Tunai"
   - Jika pengguna menyebutkan rekening/dompet (misal: "dari bank Jago", "pakai BCA", "dari DANA", "pake Gopay", "tunai"), kamu WAJIB menetapkan `payment_account` / `deposit_account` ke akun tersebut (OVERRIDE teks acquirer/gateway di struk).
3. PENGELUARAN / BEBAN, BAYAR HUTANG, PINJAMAN & INVESTASI / ARISAN:
   - Jika pengguna mencatat INVESTASI / REKSADANA / SAHAM / RDN / ARISAN / IURAN ARISAN / TABUNGAN BERJANGKA / DEPOSITO / EMAS / CRYPTO (misal: "bayar arisan dari bank jago 500rb", "setor iuran arisan", "investasi reksadana dari bank jago", "beli saham di stockbit", "top up bibit 200k", "transfer ke rdn", "nabung emas"), panggil `record_expense` dan WAJIB set `expense_account` ke "Investasi & Tabungan Berjangka" (JANGAN panggil record_transfer ke kas fisik, dan JANGAN masukkan ke beban tak terduga)!
   - Jika pengguna mencatat PEMBELIAN ASET TETAP / GADGET / KENDARAAN (misal: "beli laptop 10jt", "beli hp baru", "beli motor"), panggil `record_expense` dan set `expense_account` ke "Peralatan Elektronik & Gadget" atau "Kendaraan".
   - Jika pengguna mencatat PEMBAYARAN HUTANG / CICILAN / PAYLATER (misal: "bayar hutang ke tante lany 300k", "bayar pinjaman 500rb", "bayar cicilan motor", "bayar paylater", "lunasin utang"), panggil `record_expense` dan WAJIB set `expense_account` ke nama akun kewajiban/hutang dari daftar di atas (misal: "Hutang Pribadi / Pinjaman", "Paylater (Shopee / GoPay)", "Hutang Kartu Kredit", "Cicilan Kendaraan / Rumah")!
   - Jika pengguna MEMINJAMKAN UANG ke orang lain / TALANGAN (misal: "pinjamkan uang ke budi 200rb", "talangin makan teman 50k", "kasbon"), panggil `record_expense` dan WAJIB set `expense_account` ke "Piutang Pribadi / Pinjaman Teman"!
   - Jika pengeluaran biaya konsumsi/belanja/operasional biasa (makan, bensin, belanja, langganan), pilih `expense_account` dari daftar akun beban di atas.
   - Set `payment_account` ke akun rekening/dompet sumber dana. Jika tidak disebutkan, default ke Kas Tunai.
   - Tentukan `amount` dalam angka murni (misal: 25000).
4. Jika pengguna mencatat pemasukan (misal: "gaji masuk 15jt ke bca", "dapat bonus 500k", "hasil jualan 2jt"), panggil `record_income`.
5. Jika pengguna memindahkan uang antar rekening/dompet internal kas (misal: "transfer dari BCA ke Gopay 200k", "tarik tunai 500rb dari mandiri ke tunai", "pindah dana bca ke jago"), panggil `record_transfer`. (Catatan: Investasi/RDN/Saham BUKAN transfer kas, melainkan Investasi di poin 3).
6. Jika pengguna menanyakan saldo rekening, dompet, e-wallet, atau total saldo kas (misal: "saldo shopee saya berapa", "berapa saldo bca saya?", "cek saldo kas", "saldo saya berapa?", "uang saya sisa berapa?", "sisa saldo di jago"), panggil `query_account_balance`.
   - Set `account_name` ke nama rekening/dompet yang ditanyakan jika spesifik (misal: "ShopeePay", "BCA", "Bank Jago", "GoPay", "DANA", "Kas Tunai"). Jika menanyakan semua saldo/total kas, kosongkan `account_name`.
7. Jika pengguna menanyakan ringkasan keuangan/laporan pemasukan & pengeluaran (misal: "keuangan saya 1 minggu", "berapa pengeluaran bulan ini?", "ringkasan kas hari ini", "pengeluaran makan minggu lalu"), panggil `query_financial_summary`.
8. BATASAN & KEAMANAN (GUARDRAILS):
   - Kamu adalah asisten khusus PENCATATAN KEUANGAN PRIBADI.
   - Jika pengguna mengirim pesan di luar topik keuangan (misalnya meminta resep makanan, coding/programming, dongeng/cerita, opini politik, gosip, atau pertanyaan non-keuangan lainnya), ATAU jika pesan berupa teks panjang yang tidak memuat transaksi keuangan, JANGAN panggil tool transaksi apapun.
   - Berikan respon ramah yang menegaskan bahwa Anda hanya melayani pencatatan keuangan (pemasukan, pengeluaran, transfer, dan laporan).
PROMPT;
    }

    /**
     * Define OpenAPI Tools schema for LLM function calling.
     */
    public function getToolsSchema(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_expense',
                    'description' => 'Mencatat transaksi pengeluaran atau beban (misal: beli makan, belanja, bayar tagihan, bensin).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal pengeluaran dalam angka murni tanpa simbol (contoh: 25000 untuk 25k).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan transaksi secara jelas (contoh: Beli Nasi Padang, Bensin Pertalite).',
                            ],
                            'expense_account' => [
                                'type' => 'string',
                                'description' => 'Nama kategori atau akun beban yang paling sesuai (contoh: Makanan & Minuman (Harian), Bahan Bakar & Bensin, Belanja Kebutuhan Dapur).',
                            ],
                            'payment_account' => [
                                'type' => 'string',
                                'description' => 'Nama atau kata kunci rekening/dompet sumber pembayaran jika disebutkan (contoh: BCA, Mandiri, Gopay, Kas Tunai). Kosongkan jika tidak disebutkan.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD. Gunakan tanggal hari ini jika tidak disebutkan tanggal masa lalu.',
                            ],
                        ],
                        'required' => ['amount', 'description', 'expense_account'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_income',
                    'description' => 'Mencatat transaksi pemasukan atau pendapatan (misal: gaji, bonus, fee freelance, penjualan).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal pemasukan dalam angka murni (contoh: 5000000).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan sumber pemasukan (contoh: Gaji Bulan Agustus, Fee Desain).',
                            ],
                            'income_account' => [
                                'type' => 'string',
                                'description' => 'Nama kategori akun pendapatan yang sesuai (contoh: Gaji Pokok & Upah, Pendapatan Proyek & Freelance).',
                            ],
                            'deposit_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet tujuan masuknya uang (contoh: BCA, Mandiri, Kas Tunai). Kosongkan jika tidak disebutkan.',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD.',
                            ],
                        ],
                        'required' => ['amount', 'description', 'income_account'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'record_transfer',
                    'description' => 'Mencatat transfer atau pemindahan dana antar rekening/dompet internal (misal: tarik tunai dari ATM, topup GoPay dari BCA).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'amount' => [
                                'type' => 'number',
                                'description' => 'Nominal transfer dalam angka murni.',
                            ],
                            'from_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet sumber pengirim (contoh: Bank BCA, Bank Mandiri).',
                            ],
                            'to_account' => [
                                'type' => 'string',
                                'description' => 'Nama rekening/dompet tujuan penerima (contoh: GoPay, Kas Tunai).',
                            ],
                            'description' => [
                                'type' => 'string',
                                'description' => 'Keterangan transfer (contoh: Topup Gopay dari BCA, Tarik Tunai ATM).',
                            ],
                            'date' => [
                                'type' => 'string',
                                'description' => 'Tanggal transaksi dalam format YYYY-MM-DD.',
                            ],
                        ],
                        'required' => ['amount', 'from_account', 'to_account', 'description'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_account_balance',
                    'description' => 'Menanyakan saldo kas, rekening bank, e-wallet tertentu atau total seluruh saldo kas/dompet yang tercatat di buku (contoh: "saldo shopee saya berapa", "cek saldo bca", "saldo saya berapa", "posisi kas hari ini").',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'account_name' => [
                                'type' => 'string',
                                'description' => 'Nama atau kata kunci rekening/dompet yang ditanyakan (contoh: ShopeePay, Bank BCA, Bank Jago, Gopay, Kas Tunai). Kosongkan jika menanyakan total seluruh kas/dompet.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'query_financial_summary',
                    'description' => 'Menanyakan ringkasan keuangan, laporan laba rugi, total pengeluaran, atau posisi kas untuk periode tertentu.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'period' => [
                                'type' => 'string',
                                'enum' => ['today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'],
                                'description' => 'Periode waktu yang ditanyakan.',
                            ],
                            'account_category' => [
                                'type' => 'string',
                                'description' => 'Kategori pengeluaran spesifik jika ditanyakan (misal: makan, bensin, listrik). Kosongkan jika menanyakan total keseluruhan.',
                            ],
                        ],
                        'required' => ['period'],
                    ],
                ],
            ],
        ];
    }
}
