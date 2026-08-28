<?php

namespace App\Services\Telegram;

use App\Enums\AccountCategory;
use App\Enums\JournalSource;
use App\Enums\TelegramMessageStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceiptImageService;
use App\Services\Ai\AiServiceManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected string $botToken;

    protected array $allowedUserIds;

    public function __construct(
        protected AccountingService $accountingService,
        protected FinancialReportService $financialReportService,
        protected AiServiceManager $aiManager,
        protected ReceiptImageService $receiptImageService,
    ) {
        $this->botToken = (string) config('telegram.bot_token', '');
        $this->allowedUserIds = (array) config('telegram.allowed_user_ids', []);
    }

    /**
     * Check if a Telegram user ID is whitelisted.
     */
    public function isUserAllowed(string|int $userId): bool
    {
        if (empty($this->allowedUserIds)) {
            return true; // If no whitelist configured, allow (or open)
        }

        return in_array((string) $userId, array_map('strval', $this->allowedUserIds), true);
    }

    /**
     * Handle incoming Telegram Update (Webhook or Long Polling).
     */
    public function handleUpdate(array $update): void
    {
        // 1. Handle Callback Query (e.g. Undo button click)
        if (! empty($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (! $message) {
            return;
        }

        // 2. Handle Photo or Image Document
        if (! empty($message['photo']) || (! empty($message['document']['mime_type']) && str_starts_with($message['document']['mime_type'], 'image/'))) {
            $this->handleImageMessage($message);

            return;
        }

        // 3. Handle incoming Text Message
        if (! empty($message['text'])) {
            $this->handleTextMessage($message);
        }
    }

    /**
     * Process incoming text message.
     */
    protected function handleTextMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? ($message['chat_id'] ?? ''));
        $fromId = (string) ($message['from']['id'] ?? ($message['from_id'] ?? ''));
        $username = $message['from']['username'] ?? ($message['username'] ?? null);
        $text = trim($message['text'] ?? '');
        $messageId = $message['message_id'] ?? null;

        if (empty($chatId) || empty($text)) {
            return;
        }

        // Authorization check
        if (! $this->isUserAllowed($fromId)) {
            $this->sendMessage(
                $chatId,
                "⛔ <b>Akses Ditolak</b>\nAkun Telegram Anda (ID: <code>{$fromId}</code>) tidak terdaftar untuk mengakses buku keuangan ini."
            );

            return;
        }

        // Command: /start or /help
        if (in_array(strtolower($text), ['/start', '/help', 'help', 'bantuan'])) {
            $this->sendHelpMessage($chatId);

            return;
        }

        // Check if user has set up at least one wallet
        if (! $this->hasConfiguredWallets()) {
            $this->sendWalletsNotConfiguredMessage($chatId);

            return;
        }

        // Command: /saldo
        if (in_array(strtolower($text), ['/saldo', 'saldo', 'kas'])) {
            $this->sendBalanceSummary($chatId);

            return;
        }

        // Command: /default or /dompet
        if (in_array(strtolower($text), ['/default', '/dompet', 'dompet', 'default'])) {
            $this->sendDefaultWalletPicker($chatId);

            return;
        }

        // Guardrail: Length check (> 300 characters without numbers)
        if (mb_strlen($text) > 300 && ! preg_match('/\d+/', $text)) {
            $this->sendOutOfTopicGuidance($chatId);

            return;
        }

        // Create initial log
        $telegramLog = TelegramMessage::create([
            'telegram_message_id' => $messageId,
            'chat_id' => $chatId,
            'from_id' => $fromId,
            'from_username' => $username,
            'raw_text' => $text,
            'status' => TelegramMessageStatus::Processed,
        ]);

        try {
            // Process through AI Provider Manager (Ollama, DeepSeek, OpenAI, etc.)
            $aiResult = $this->aiManager->processMessage($text);

            $intent = $aiResult['intent'];
            $params = $aiResult['parameters'];

            $telegramLog->update([
                'intent' => $intent,
                'raw_ai_payload' => $aiResult,
            ]);

            switch ($intent) {
                case 'record_expense':
                    $this->executeRecordExpense($chatId, $params, $telegramLog);
                    break;

                case 'record_income':
                    $this->executeRecordIncome($chatId, $params, $telegramLog);
                    break;

                case 'record_transfer':
                    $this->executeRecordTransfer($chatId, $params, $telegramLog);
                    break;

                case 'query_financial_summary':
                    $this->executeQueryFinancialSummary($chatId, $params, $telegramLog);
                    break;

                case 'query_account_balance':
                    $this->executeQueryAccountBalance($chatId, $params, $telegramLog);
                    break;

                default:
                    $reply = $aiResult['reply_text'] ?? null;
                    if (! empty($reply)) {
                        $this->sendMessage($chatId, $reply);
                        $telegramLog->update(['ai_response' => $reply]);
                    } else {
                        $this->sendOutOfTopicGuidance($chatId);
                        $telegramLog->update(['ai_response' => 'Out of topic fallback']);
                    }
                    break;
            }
        } catch (Exception $e) {
            Log::error('Telegram Bot processing error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $errorMsg = '⚠️ <b>Terjadi Kesalahan:</b> '.htmlspecialchars($e->getMessage());
            $this->sendMessage($chatId, $errorMsg);
            $telegramLog->update([
                'status' => TelegramMessageStatus::Failed,
                'ai_response' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process incoming image message (receipt / transfer screenshot).
     */
    protected function handleImageMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? ($message['chat_id'] ?? ''));
        $fromId = (string) ($message['from']['id'] ?? ($message['from_id'] ?? ''));
        $username = $message['from']['username'] ?? ($message['username'] ?? null);
        $caption = trim($message['caption'] ?? '');
        $messageId = $message['message_id'] ?? null;

        if (empty($chatId)) {
            return;
        }

        // Authorization check
        if (! $this->isUserAllowed($fromId)) {
            $this->sendMessage(
                $chatId,
                "⛔ <b>Akses Ditolak</b>\nAkun Telegram Anda (ID: <code>{$fromId}</code>) tidak terdaftar untuk mengakses buku keuangan ini."
            );

            return;
        }

        // Check if user has set up at least one wallet
        if (! $this->hasConfiguredWallets()) {
            $this->sendWalletsNotConfiguredMessage($chatId);

            return;
        }

        // Determine file_id
        $fileId = null;
        $mimeType = 'image/jpeg';

        if (! empty($message['photo']) && is_array($message['photo'])) {
            // Photos array has sizes from smallest to largest; get highest resolution
            $photo = end($message['photo']);
            $fileId = $photo['file_id'] ?? null;
        } elseif (! empty($message['document'])) {
            $fileId = $message['document']['file_id'] ?? null;
            $mimeType = $message['document']['mime_type'] ?? 'image/jpeg';
        }

        if (empty($fileId)) {
            $this->sendMessage($chatId, '⚠️ Gambar tidak ditemukan atau format tidak didukung.');

            return;
        }

        // Send processing status
        $this->sendMessage($chatId, '🔍 <i>Sedang membaca struk/screenshot dengan Vision OCR & memproses pembukuan...</i>');

        // Create initial log
        $telegramLog = TelegramMessage::create([
            'telegram_message_id' => $messageId,
            'chat_id' => $chatId,
            'from_id' => $fromId,
            'from_username' => $username,
            'raw_text' => '[FOTO STRUK / SCREENSHOT]'.($caption ? " Caption: {$caption}" : ''),
            'status' => TelegramMessageStatus::Processed,
        ]);

        try {
            // 1. Download image from Telegram
            $imageBytes = $this->downloadTelegramFile($fileId);

            // Compress and store receipt image locally via PHP GD
            $storedReceiptPath = $this->receiptImageService->compressAndStore($imageBytes);
            if ($storedReceiptPath) {
                $telegramLog->update(['receipt_image' => $storedReceiptPath]);
            }

            // 2. Process receipt image via AiServiceManager Hybrid Vision OCR
            $aiResult = $this->aiManager->processReceiptImage($imageBytes, $mimeType, $caption);
            $ocrText = $aiResult['ocr_text'] ?? '';

            $intent = $aiResult['intent'];
            $params = $aiResult['parameters'];

            $telegramLog->update([
                'intent' => $intent,
                'raw_ai_payload' => [
                    'ocr_text' => $ocrText,
                    'ai_result' => $aiResult,
                ],
            ]);

            switch ($intent) {
                case 'record_expense':
                    $this->executeRecordExpense($chatId, $params, $telegramLog, isOcr: true, ocrText: $ocrText, receiptImage: $storedReceiptPath);
                    break;

                case 'record_income':
                    $this->executeRecordIncome($chatId, $params, $telegramLog, receiptImage: $storedReceiptPath);
                    break;

                case 'record_transfer':
                    $this->executeRecordTransfer($chatId, $params, $telegramLog, receiptImage: $storedReceiptPath);
                    break;

                default:
                    $reply = $aiResult['reply_text'] ?: 'Struk berhasil dibaca, namun tidak ditemukan transaksi yang perlu dicatat.';
                    $this->sendMessage($chatId, $reply);
                    $telegramLog->update(['ai_response' => $reply]);
                    break;
            }
        } catch (Exception $e) {
            Log::error('Telegram Image OCR error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $errorMsg = '⚠️ <b>Gagal Memproses Gambar:</b> '.htmlspecialchars($e->getMessage());
            $this->sendMessage($chatId, $errorMsg);
            $telegramLog->update([
                'status' => TelegramMessageStatus::Failed,
                'ai_response' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Download a file from Telegram by file_id.
     */
    public function downloadTelegramFile(string $fileId): string
    {
        // 1. Get file path
        $getFileUrl = "https://api.telegram.org/bot{$this->botToken}/getFile";
        $response = Http::timeout(20)->post($getFileUrl, ['file_id' => $fileId]);

        if (! $response->successful() || empty($response->json('result.file_path'))) {
            throw new Exception('Gagal mendapatkan file dari Telegram: '.$response->body());
        }

        $filePath = $response->json('result.file_path');

        // 2. Download raw file content
        $downloadUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
        $fileResponse = Http::timeout(30)->get($downloadUrl);

        if (! $fileResponse->successful()) {
            throw new Exception('Gagal mengunduh konten file dari server Telegram.');
        }

        return $fileResponse->body();
    }

    /**
     * Handle Expense recording.
     */
    protected function executeRecordExpense(
        string $chatId,
        array $params,
        TelegramMessage $log,
        bool $isOcr = false,
        ?string $ocrText = null,
        ?string $receiptImage = null,
    ): void {
        $amount = (float) ($params['amount'] ?? 0);
        $description = trim($params['description'] ?? ($isOcr ? 'Pembelian sesuai Struk/Nota' : 'Pengeluaran'));
        $expenseAccountName = $params['expense_account'] ?? 'Makanan & Minuman (Harian)';
        $paymentAccountKeyword = $params['payment_account'] ?? null;
        $date = ! empty($params['date']) ? Carbon::parse($params['date']) : now();

        $expenseAccount = $this->accountingService->findOrCreateExpenseAccount($expenseAccountName);
        $paymentAccount = $this->accountingService->findPaymentAccount($paymentAccountKeyword);

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'expense',
            amount: $amount,
            sourceAccount: $paymentAccount,
            destinationAccount: $expenseAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage
        );

        $log->update([
            'journal_entry_id' => $journal->id,
            'status' => TelegramMessageStatus::Processed,
        ]);

        $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');
        $formattedDate = $date->translatedFormat('d M Y');

        $remainingBalance = $paymentAccount->fresh()->balance;

        $headerTitle = $isOcr ? '🧾 <b>NOTA / STRUK BERHASIL DIPROSES</b>' : '✅ <b>PENGELUARAN BERHASIL DICATAT</b>';

        $text = "{$headerTitle}\n\n"
            ."📝 <b>Keterangan:</b> {$description}\n"
            ."💰 <b>Nominal:</b> <code>{$formattedAmount}</code>\n"
            ."📁 <b>Akun Beban:</b> [{$expenseAccount->code}] {$expenseAccount->name}\n"
            ."💳 <b>Sumber Dana:</b> [{$paymentAccount->code}] {$paymentAccount->name}\n"
            ."📅 <b>Tanggal:</b> {$formattedDate}\n"
            ."🔖 <b>No. Jurnal:</b> <code>{$journal->entry_number}</code>";

        if ($remainingBalance <= 0) {
            $formattedRemaining = ($remainingBalance < 0 ? '-' : '').'Rp '.number_format(abs($remainingBalance), 0, ',', '.');
            $statusLabel = $remainingBalance < 0 ? '(Defisit / Minus)' : '(Habis / Rp 0)';
            $text .= "\n\n⚠️ <b>PERINGATAN SALDO:</b>\n"
                ."Sisa saldo <b>{$paymentAccount->name}</b> Anda kini <b>{$formattedRemaining}</b> {$statusLabel}.\n"
                .'<i>💡 Tips: Anda dapat melakukan transfer antar dompet atau penyesuaian saldo di web admin.</i>';
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '↩️ Batalkan / Undo', 'callback_data' => "undo_journal_{$journal->id}"],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
        $log->update(['ai_response' => $text]);
    }

    /**
     * Handle Income recording.
     */
    protected function executeRecordIncome(string $chatId, array $params, TelegramMessage $log, ?string $receiptImage = null): void
    {
        $amount = (float) ($params['amount'] ?? 0);
        $description = trim($params['description'] ?? 'Pemasukan');
        $incomeAccountName = $params['income_account'] ?? 'Pendapatan Lainnya';
        $depositAccountKeyword = $params['deposit_account'] ?? null;
        $date = ! empty($params['date']) ? Carbon::parse($params['date']) : now();

        $incomeAccount = $this->accountingService->findOrCreateIncomeAccount($incomeAccountName);
        $depositAccount = $this->accountingService->findPaymentAccount($depositAccountKeyword);

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'income',
            amount: $amount,
            sourceAccount: $incomeAccount,
            destinationAccount: $depositAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage
        );

        $log->update([
            'journal_entry_id' => $journal->id,
            'status' => TelegramMessageStatus::Processed,
        ]);

        $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');
        $formattedDate = $date->translatedFormat('d M Y');

        $text = "🎉 <b>PEMASUKAN BERHASIL DICATAT</b>\n\n"
            ."📝 <b>Keterangan:</b> {$description}\n"
            ."💰 <b>Nominal:</b> <code>{$formattedAmount}</code>\n"
            ."📁 <b>Akun Pendapatan:</b> [{$incomeAccount->code}] {$incomeAccount->name}\n"
            ."🏦 <b>Masuk ke:</b> [{$depositAccount->code}] {$depositAccount->name}\n"
            ."📅 <b>Tanggal:</b> {$formattedDate}\n"
            ."🔖 <b>No. Jurnal:</b> <code>{$journal->entry_number}</code>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '↩️ Batalkan / Undo', 'callback_data' => "undo_journal_{$journal->id}"],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
        $log->update(['ai_response' => $text]);
    }

    /**
     * Handle Transfer recording.
     */
    protected function executeRecordTransfer(string $chatId, array $params, TelegramMessage $log, ?string $receiptImage = null): void
    {
        $amount = (float) ($params['amount'] ?? 0);
        $description = trim($params['description'] ?? 'Transfer Saldo');
        $fromAccountKeyword = $params['from_account'] ?? null;
        $toAccountKeyword = $params['to_account'] ?? null;
        $date = ! empty($params['date']) ? Carbon::parse($params['date']) : now();

        $fromAccount = $this->accountingService->findPaymentAccount($fromAccountKeyword);
        $toAccount = $this->accountingService->findPaymentAccount($toAccountKeyword);

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'transfer',
            amount: $amount,
            sourceAccount: $fromAccount,
            destinationAccount: $toAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage
        );

        $log->update([
            'journal_entry_id' => $journal->id,
            'status' => TelegramMessageStatus::Processed,
        ]);

        $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');
        $formattedDate = $date->translatedFormat('d M Y');

        $text = "🔄 <b>TRANSFER DANA BERHASIL DICATAT</b>\n\n"
            ."📝 <b>Keterangan:</b> {$description}\n"
            ."💰 <b>Nominal:</b> <code>{$formattedAmount}</code>\n"
            ."📤 <b>Dari:</b> [{$fromAccount->code}] {$fromAccount->name}\n"
            ."📥 <b>Ke:</b> [{$toAccount->code}] {$toAccount->name}\n"
            ."📅 <b>Tanggal:</b> {$formattedDate}\n"
            ."🔖 <b>No. Jurnal:</b> <code>{$journal->entry_number}</code>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '↩️ Batalkan / Undo', 'callback_data' => "undo_journal_{$journal->id}"],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
        $log->update(['ai_response' => $text]);
    }

    /**
     * Handle Financial Summary query.
     */
    protected function executeQueryFinancialSummary(string $chatId, array $params, TelegramMessage $log): void
    {
        $period = $params['period'] ?? 'this_month';

        $now = now()->setTimezone('Asia/Jakarta');
        switch ($period) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = 'Hari Ini ('.$now->translatedFormat('d M Y').')';
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $periodLabel = 'Kemarin ('.$start->translatedFormat('d M Y').')';
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $periodLabel = 'Minggu Ini ('.$start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y').')';
                break;
            case 'last_week':
                $start = $now->copy()->subWeek()->startOfWeek();
                $end = $now->copy()->subWeek()->endOfWeek();
                $periodLabel = 'Minggu Lalu ('.$start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y').')';
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $periodLabel = 'Bulan Lalu ('.$start->translatedFormat('F Y').')';
                break;
            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $periodLabel = 'Tahun Ini ('.$now->format('Y').')';
                break;
            case 'this_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $periodLabel = 'Bulan Ini ('.$now->translatedFormat('F Y').')';
                break;
        }

        if (! empty($params['start_date']) && ! empty($params['end_date'])) {
            $start = Carbon::parse($params['start_date'])->startOfDay();
            $end = Carbon::parse($params['end_date'])->endOfDay();
            $periodLabel = $start->translatedFormat('d M Y').' s/d '.$end->translatedFormat('d M Y');
        }

        $incomeStatement = $this->financialReportService->getIncomeStatement($start, $end);

        $totalRevenue = $incomeStatement['total_operating_revenue'] + $incomeStatement['total_other_revenue'];
        $totalExpense = $incomeStatement['total_operating_expenses'] + $incomeStatement['total_other_expenses'];
        $netProfit = $incomeStatement['net_profit'];

        $text = "📊 <b>RINGKASAN KEUANGAN</b>\n"
            ."🗓️ <i>Periode: {$periodLabel}</i>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            .'🟢 <b>Total Pemasukan:</b> Rp '.number_format($totalRevenue, 0, ',', '.')."\n"
            .'🔴 <b>Total Pengeluaran:</b> Rp '.number_format($totalExpense, 0, ',', '.')."\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .($netProfit >= 0 ? '✨ <b>Surplus (Laba Bersih):</b> ' : '⚠️ <b>Defisit:</b> ')
            .'<b>Rp '.number_format($netProfit, 0, ',', '.')."</b>\n\n";

        if ($incomeStatement['operating_expenses']->isNotEmpty()) {
            $text .= "📌 <b>Rincian Pengeluaran Terbesar:</b>\n";
            $topExpenses = $incomeStatement['operating_expenses']->sortByDesc('period_balance')->take(5);
            foreach ($topExpenses as $exp) {
                $text .= " • {$exp->name}: Rp ".number_format($exp->period_balance, 0, ',', '.')."\n";
            }
        }

        $this->sendMessage($chatId, $text);
        $log->update(['ai_response' => $text]);
    }

    /**
     * Handle Account Balance query (single account or all wallets).
     */
    protected function executeQueryAccountBalance(string $chatId, array $params, TelegramMessage $log): void
    {
        $accountKeyword = trim($params['account_name'] ?? '');

        if (! empty($accountKeyword)) {
            // Find specific cash/bank account by name or code
            $account = Account::where('category', AccountCategory::CashAndBank)
                ->where(function ($q) use ($accountKeyword) {
                    $q->where('name', 'like', "%{$accountKeyword}%")
                        ->orWhere('code', 'like', "%{$accountKeyword}%");
                })
                ->where('is_active', true)
                ->first();

            if ($account) {
                $formattedBal = 'Rp '.number_format($account->balance, 0, ',', '.');
                $text = "💳 <b>INFORMASI SALDO AKUN</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
                    ."🏦 <b>Nama Akun:</b> [{$account->code}] {$account->name}\n"
                    ."💰 <b>Saldo Terkini:</b> <code>{$formattedBal}</code>\n\n"
                    .'<i>💡 Saldo di atas dihitung berdasarkan seluruh catatan transaksi yang telah dibukukan.</i>';

                $this->sendMessage($chatId, $text);
                $log->update(['ai_response' => $text]);

                return;
            }
        }

        // Fallback or general balance summary if not specific or not found
        $this->sendBalanceSummary($chatId);
        $log->update(['ai_response' => 'Summary of all cash and bank accounts sent']);
    }

    /**
     * Handle callback query (e.g. Undo action).
     */
    protected function handleCallbackQuery(array $callbackQuery): void
    {
        $id = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';
        $fromId = (string) ($callbackQuery['from']['id'] ?? '');
        $message = $callbackQuery['message'] ?? [];
        $chatId = (string) ($message['chat']['id'] ?? '');
        $messageId = $message['message_id'] ?? null;

        if (! $this->isUserAllowed($fromId)) {
            $this->answerCallbackQuery($id, '⛔ Akses Ditolak.');

            return;
        }

        if (str_starts_with($data, 'undo_journal_')) {
            $journalId = (int) str_replace('undo_journal_', '', $data);
            $journal = JournalEntry::find($journalId);

            if (! $journal) {
                $this->answerCallbackQuery($id, 'Transaksi sudah tidak ditemukan atau sudah dihapus.');
                if ($chatId && $messageId) {
                    $this->editMessageText($chatId, $messageId, '❌ <i>Transaksi ini sudah dibatalkan sebelumnya.</i>');
                }

                return;
            }

            $entryNumber = $journal->entry_number;
            $this->accountingService->revertJournalEntry($journal);

            $this->answerCallbackQuery($id, "Transaksi {$entryNumber} berhasil dibatalkan!");

            if ($chatId && $messageId) {
                $originalText = $message['text'] ?? '';
                $updatedText = $originalText."\n\n❌ <b>[DIBATALKAN]</b> Transaksi <code>{$entryNumber}</code> telah dihapus dari sistem.";
                $this->editMessageText($chatId, $messageId, $updatedText);
            }

            return;
        }

        if (str_starts_with($data, 'set_default_wallet_')) {
            $walletId = (int) str_replace('set_default_wallet_', '', $data);
            $wallet = Account::find($walletId);

            if ($wallet) {
                $wallet->markAsDefault();
                $this->answerCallbackQuery($id, "⭐ Dompet utama diubah ke: {$wallet->name}");

                if ($chatId && $messageId) {
                    $text = "⭐ <b>DOMPET UTAMA BERHASIL DIUBAH</b>\n\n"
                        ."Sekarang transaksi tanpa nama bank otomatis dipotong dari:\n"
                        ."👉 <b>{$wallet->name}</b> (Saldo: Rp ".number_format($wallet->balance, 0, ',', '.').')';
                    $this->editMessageText($chatId, $messageId, $text);
                }
            } else {
                $this->answerCallbackQuery($id, 'Dompet tidak ditemukan.');
            }

            return;
        }
    }

    /**
     * Send Default Wallet picker with inline keyboard.
     */
    protected function sendDefaultWalletPicker(string $chatId): void
    {
        $wallets = Account::wallets()->where('is_active', true)->orderBy('code')->get();

        if ($wallets->isEmpty()) {
            $this->sendMessage($chatId, '⚠️ Belum ada dompet/rekening aktif yang terdaftar.');

            return;
        }

        $defaultWallet = $wallets->firstWhere('is_default', true) ?? $wallets->first();

        $text = "👛 <b>PENGATURAN DOMPET UTAMA (DEFAULT)</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Dompet utama saat ini:\n"
            ."⭐ <b>{$defaultWallet->name}</b> (Rp ".number_format($defaultWallet->balance, 0, ',', '.').")\n\n"
            .'<i>Pilih dompet di bawah jika ingin mengganti dompet utama untuk transaksi cepat tanpa nama bank:</i>';

        $buttons = [];
        foreach ($wallets as $w) {
            $prefix = $w->is_default ? '⭐ ' : '• ';
            $buttons[] = [
                ['text' => $prefix.$w->name, 'callback_data' => "set_default_wallet_{$w->id}"],
            ];
        }

        $this->sendMessage($chatId, $text, ['inline_keyboard' => $buttons]);
    }

    /**
     * Check if the user has configured at least one active wallet in the system.
     */
    public function hasConfiguredWallets(): bool
    {
        return Account::wallets()->where('is_active', true)->exists();
    }

    /**
     * Send guide message when wallets have not been configured yet.
     */
    protected function sendWalletsNotConfiguredMessage(string $chatId): void
    {
        $webUrl = config('app.url', 'http://localhost').'/admin';

        $text = "👛 <b>DOMPET & REKENING BELUM DIATUR!</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Hai! Anda belum mengatur daftar dompet (rekening bank/e-wallet) dan saldo awal di sistem keuangan ini.\n\n"
            ."Agar bot dapat mencatat mutasi uang Anda dengan akurat, silakan buka panel web untuk menyelesaikan <b>Setup Wizard Dompet (3 Langkah Cepat)</b> terlebih dahulu.\n\n"
            ."👉 <b>Akses Panel Web:</b>\n"
            ."<code>{$webUrl}</code>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Buka Panel Admin Web', 'url' => $webUrl],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * Send Saldo Kas & Bank summary.
     */
    protected function sendBalanceSummary(string $chatId): void
    {
        $cashAccounts = Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        if ($cashAccounts->isEmpty()) {
            $this->sendWalletsNotConfiguredMessage($chatId);

            return;
        }

        $totalCash = 0.0;
        $text = "💳 <b>SALDO KAS & BANK TERKINI</b>\n━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($cashAccounts as $acc) {
            $bal = $acc->balance;
            $totalCash += $bal;
            $text .= "• <b>{$acc->name}:</b> Rp ".number_format($bal, 0, ',', '.')."\n";
        }

        $text .= "━━━━━━━━━━━━━━━━━━━━\n"
            .'💵 <b>Total Likuiditas:</b> Rp '.number_format($totalCash, 0, ',', '.');

        if ($totalCash <= 0) {
            $text .= "\n\n💡 <i>Catatan: Total saldo kas Anda saat ini Rp 0. Anda bisa mengatur saldo awal atau transfer di panel web.</i>";
        }

        $this->sendMessage($chatId, $text);
    }

    /**
     * Send Help message.
     */
    protected function sendHelpMessage(string $chatId): void
    {
        $help = <<<'HELP'
👋 <b>Halo! Saya Asisten Akuntan Pribadi Anda.</b>

Anda dapat mencatat transaksi keuangan secara instan hanya dengan mengirimkan pesan natural seperti:

🛒 <b>Catat Pengeluaran:</b>
• <i>"beli telur 1 kg 25k"</i>
• <i>"bensin motor 50rb bayar pake bca"</i>
• <i>"makan siang nasi padang 32000"</i>
• <i>"beli kopi 25rb pake gopay"</i>

💵 <b>Catat Pemasukan:</b>
• <i>"gaji bulan ini masuk 15jt ke mandiri"</i>
• <i>"terima fee freelance 2.5jt di bca"</i>
• <i>"dapat cashback 50k di gopay"</i>

🔄 <b>Transfer Antar Dompet/Bank:</b>
• <i>"topup gopay dari bca 200rb"</i>
• <i>"tarik tunai 500k dari mandiri"</i>

📈 <b>Cek Kondisi Keuangan:</b>
• <i>"keuangan saya 1 minggu"</i>
• <i>"laporan pengeluaran bulan ini"</i>
• <i>"saldo"</i> / <i>/saldo</i>
• <i>/default</i> (Ganti dompet default)

Setiap pencatatan transaksi otomatis dilengkapi tombol <b>Undo / Batal</b> jika ada kesalahan.
HELP;

        $this->sendMessage($chatId, $help);
    }

    /**
     * Send Out of Topic guidance message.
     */
    protected function sendOutOfTopicGuidance(string $chatId): void
    {
        $text = "🤖 <b>ASISTEN PENCATATAN KEUANGAN PRIBADI</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Maaf, saya hanya diprogram khusus untuk mencatat transaksi dan menyajikan laporan keuangan Anda.\n\n"
            ."Mohon kirimkan pesan terkait mutasi atau ringkasan keuangan Anda.\n\n"
            ."👉 <b>Contoh yang didukung:</b>\n"
            ."• <i>\"beli bensin 50rb pake bca\"</i> (Pengeluaran)\n"
            ."• <i>\"gaji masuk 15jt ke mandiri\"</i> (Pemasukan)\n"
            ."• <i>\"transfer bca ke gopay 100rb\"</i> (Transfer Saldo)\n"
            ."• <i>/saldo</i> (Cek Saldo Kas & Bank)\n"
            .'• <i>"keuangan saya 1 minggu"</i> (Laporan Ringkas)';

        $this->sendMessage($chatId, $text);
    }

    /**
     * Convert markdown formatting from AI into valid Telegram HTML.
     */
    public function formatMarkdownToTelegramHtml(string $text): string
    {
        // 1. Convert code blocks: ```lang ... ``` or ``` ... ```
        $text = preg_replace('/```(?:[a-zA-Z0-9_\-]+)?\n?(.*?)```/s', '<pre>$1</pre>', $text);

        // 2. Convert inline code: `code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // 3. Convert bold: **text** or __text__
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<b>$1</b>', $text);
        $text = preg_replace('/__(.*?)__/s', '<b>$1</b>', $text);

        // 4. Convert markdown links: [text](url)
        $text = preg_replace('/\[(.*?)\]\(((?:https?:\/\/)[^\)]+)\)/', '<a href="$2">$1</a>', $text);

        // 5. Convert bullet points: - or * at start of line to bullet character
        $text = preg_replace('/^[\*\-]\s+/m', '• ', $text);

        return $text;
    }

    /**
     * Send HTTP POST to Telegram sendMessage API.
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        if (empty($this->botToken)) {
            Log::warning('TELEGRAM_BOT_TOKEN belum disetting. Pesan Telegram diabaikan.');

            return [];
        }

        $formattedText = ($parseMode === 'HTML') ? $this->formatMarkdownToTelegramHtml($text) : $text;

        $payload = [
            'chat_id' => $chatId,
            'text' => $formattedText,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);
        $result = $response->json() ?? [];

        // Fallback: If Telegram failed due to unparseable HTML tags, retry as plain text
        if (! ($result['ok'] ?? false) && $parseMode === 'HTML' && str_contains($result['description'] ?? '', "can't parse entities")) {
            unset($payload['parse_mode']);
            $payload['text'] = strip_tags($formattedText);
            $retryResponse = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", $payload);
            $result = $retryResponse->json() ?? [];
        }

        return $result;
    }

    /**
     * Edit existing message text in Telegram.
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        if (empty($this->botToken)) {
            return [];
        }

        $formattedText = ($parseMode === 'HTML') ? $this->formatMarkdownToTelegramHtml($text) : $text;

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $formattedText,
            'parse_mode' => $parseMode,
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/editMessageText", $payload);
        $result = $response->json() ?? [];

        // Fallback: If Telegram failed due to unparseable HTML tags, retry as plain text
        if (! ($result['ok'] ?? false) && $parseMode === 'HTML' && str_contains($result['description'] ?? '', "can't parse entities")) {
            unset($payload['parse_mode']);
            $payload['text'] = strip_tags($formattedText);
            $retryResponse = Http::post("https://api.telegram.org/bot{$this->botToken}/editMessageText", $payload);
            $result = $retryResponse->json() ?? [];
        }

        return $result;
    }

    /**
     * Answer Callback Query.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        if (empty($this->botToken)) {
            return [];
        }

        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text) {
            $payload['text'] = $text;
            $payload['show_alert'] = false;
        }

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/answerCallbackQuery", $payload);

        return $response->json() ?? [];
    }

    /**
     * Fetch updates for Long Polling.
     */
    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 30): array
    {
        if (empty($this->botToken)) {
            throw new Exception('TELEGRAM_BOT_TOKEN belum dikonfigurasi di file .env.');
        }

        $response = Http::timeout($timeout + 5)->get("https://api.telegram.org/bot{$this->botToken}/getUpdates", [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
        ]);

        if (! $response->successful()) {
            throw new Exception('Telegram getUpdates failed: '.$response->body());
        }

        return $response->json('result') ?? [];
    }
}
