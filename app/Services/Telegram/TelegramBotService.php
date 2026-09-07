<?php

namespace App\Services\Telegram;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\TelegramMessageStatus;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceiptImageService;
use App\Services\Ai\AiServiceManager;
use App\Services\System\EnvironmentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
        protected ?EnvironmentService $environmentService = null,
    ) {
        $this->botToken = (string) config('telegram.bot_token', '');
        $this->allowedUserIds = (array) config('telegram.allowed_user_ids', []);
        $this->environmentService = $environmentService ?? app(EnvironmentService::class);
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

        // Idempotency check: if messageId exists, check if already processed or recorded
        $telegramLog = null;
        if (! empty($messageId) && ! empty($chatId)) {
            $existing = TelegramMessage::where('chat_id', $chatId)
                ->where('telegram_message_id', $messageId)
                ->first();

            if ($existing) {
                // If it already has a journal entry attached or was already successfully answered with ai_response:
                if ($existing->journal_entry_id !== null || (! empty($existing->ai_response) && $existing->status === TelegramMessageStatus::Processed)) {
                    Log::info("Telegram message {$messageId} in chat {$chatId} is already processed with journal #{$existing->journal_entry_id}. Skipping duplicate execution.");

                    return;
                }
                $telegramLog = $existing;
            }
        }

        if (! $telegramLog) {
            $telegramLog = TelegramMessage::create([
                'telegram_message_id' => $messageId,
                'chat_id' => $chatId,
                'from_id' => $fromId,
                'from_username' => $username,
                'raw_text' => $text,
                'status' => TelegramMessageStatus::Processed,
            ]);
        }

        // 0. Active Configuration Wizard State check
        $setupState = Cache::get("tg_setup_state_{$chatId}");
        if ($setupState) {
            $this->handleSetupTextResponse($chatId, $text, $messageId, $setupState, $telegramLog);

            return;
        }

        // Direct command: /set KEY VALUE
        if (str_starts_with(strtolower($text), '/set ')) {
            $this->handleDirectSetCommand($chatId, $text, $messageId, $telegramLog);

            return;
        }

        // Command: /setup or /config or /env or /tmaccountant
        if (in_array(strtolower($text), ['/setup', 'setup', '/config', 'config', '/env', 'env', '/setting', 'setting', '/tmaccountant', 'tmaccountant'])) {
            $this->startSetupWizard($chatId, $telegramLog);

            return;
        }

        // Command: /batal or batal
        if (in_array(strtolower($text), ['/batal', 'batal', '/cancel', 'cancel'])) {
            Cache::forget("tg_setup_state_{$chatId}");
            $this->sendMessage($chatId, '✓ Sesi telah dibatalkan.');

            return;
        }

        // Command: /start or /help
        if (in_array(strtolower($text), ['/start', '/help', 'help', 'bantuan'])) {
            $this->sendHelpMessage($chatId, $telegramLog);

            return;
        }

        // Check if user has set up at least one wallet
        if (! $this->hasConfiguredWallets()) {
            $this->sendWalletsNotConfiguredMessage($chatId, $telegramLog);

            return;
        }

        // Command: /saldo
        if (in_array(strtolower($text), ['/saldo', 'saldo', 'kas'])) {
            $this->sendBalanceSummary($chatId, $telegramLog);

            return;
        }

        // Command: /model or /ai
        if (in_array(strtolower($text), ['/model', 'model', '/ai', 'ai', 'cek model', 'info model', '/provider', 'provider'])) {
            $this->sendModelInfo($chatId, $telegramLog);

            return;
        }

        // Command: /default or /dompet
        if (in_array(strtolower($text), ['/default', '/dompet', 'dompet', 'default'])) {
            $this->sendDefaultWalletPicker($chatId, $telegramLog);

            return;
        }

        // Guardrail: Length check (> 300 characters without numbers)
        if (mb_strlen($text) > 300 && ! preg_match('/\d+/', $text)) {
            $this->sendOutOfTopicGuidance($chatId, $telegramLog);

            return;
        }

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

                case 'query_transactions':
                    $this->executeQueryTransactions($chatId, $params, $text, $telegramLog);
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

        // Idempotency check: if messageId exists, check if already processed
        $telegramLog = null;
        if (! empty($messageId) && ! empty($chatId)) {
            $existing = TelegramMessage::where('chat_id', $chatId)
                ->where('telegram_message_id', $messageId)
                ->first();

            if ($existing) {
                if ($existing->journal_entry_id !== null || (! empty($existing->ai_response) && $existing->status === TelegramMessageStatus::Processed)) {
                    Log::info("Telegram image message {$messageId} in chat {$chatId} is already processed with journal #{$existing->journal_entry_id}. Skipping duplicate execution.");

                    return;
                }
                $telegramLog = $existing;
            }
        }

        // Send processing status
        $this->sendMessage($chatId, '🔍 <i>Sedang membaca struk/screenshot dengan Vision OCR & memproses pembukuan...</i>');

        if (! $telegramLog) {
            $telegramLog = TelegramMessage::create([
                'telegram_message_id' => $messageId,
                'chat_id' => $chatId,
                'from_id' => $fromId,
                'from_username' => $username,
                'raw_text' => '[FOTO STRUK / SCREENSHOT]'.($caption ? " Caption: {$caption}" : ''),
                'status' => TelegramMessageStatus::Processed,
            ]);
        }

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
        $description = $this->cleanDescription(trim($params['description'] ?? ($isOcr ? 'Pembelian sesuai Struk/Nota' : 'Pengeluaran')));
        $expenseAccountName = $params['expense_account'] ?? 'Makanan & Minuman (Harian)';
        $paymentAccountKeyword = $params['payment_account'] ?? null;
        $date = ! empty($params['date']) ? Carbon::parse($params['date']) : now();

        $expenseAccount = $this->accountingService->findOrCreateExpenseAccount($expenseAccountName);
        $paymentAccount = $this->accountingService->findPaymentAccount($paymentAccountKeyword);

        $referenceNumber = (! empty($log->telegram_message_id) && ! empty($log->chat_id))
            ? "TG-{$log->chat_id}-{$log->telegram_message_id}"
            : null;

        // Idempotency: verify log does not already have a journal attached
        if ($log->fresh()->journal_entry_id !== null) {
            Log::info("Telegram message {$log->telegram_message_id} already attached to journal #{$log->journal_entry_id}. Skipping duplicate expense.");

            return;
        }

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'expense',
            amount: $amount,
            sourceAccount: $paymentAccount,
            destinationAccount: $expenseAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage,
            referenceNumber: $referenceNumber
        );

        $log->update([
            'journal_entry_id' => $journal->id,
            'status' => TelegramMessageStatus::Processed,
        ]);

        $formattedAmount = 'Rp '.number_format($amount, 0, ',', '.');
        $formattedDate = $date->translatedFormat('d M Y');

        $remainingBalance = $paymentAccount->fresh()->balance;

        $accountLabel = match ($expenseAccount->type) {
            AccountType::Liability => 'Akun Kewajiban (Hutang)',
            AccountType::Asset => match ($expenseAccount->category) {
                AccountCategory::AccountsReceivable => 'Akun Piutang',
                AccountCategory::OtherCurrentAsset => 'Akun Investasi / Aset Lancar',
                AccountCategory::FixedAsset => 'Akun Aset Tetap',
                default => 'Akun Aset',
            },
            default => 'Akun Beban',
        };

        $headerTitle = $isOcr ? '🧾 <b>NOTA / STRUK BERHASIL DIPROSES</b>' : match ($expenseAccount->type) {
            AccountType::Liability => '✅ <b>PEMBAYARAN HUTANG BERHASIL DICATAT</b>',
            AccountType::Asset => match ($expenseAccount->category) {
                AccountCategory::AccountsReceivable => '✅ <b>PINJAMAN / PIUTANG BERHASIL DICATAT</b>',
                AccountCategory::OtherCurrentAsset => '📈 <b>INVESTASI BERHASIL DICATAT</b>',
                AccountCategory::FixedAsset => '🏢 <b>PEMBELIAN ASET BERHASIL DICATAT</b>',
                default => '✅ <b>TRANSAKSI ASET BERHASIL DICATAT</b>',
            },
            default => '✅ <b>PENGELUARAN BERHASIL DICATAT</b>',
        };

        $text = "{$headerTitle}\n\n"
            ."📝 <b>Keterangan:</b> {$description}\n"
            ."💰 <b>Nominal:</b> <code>{$formattedAmount}</code>\n"
            ."📁 <b>{$accountLabel}:</b> [{$expenseAccount->code}] {$expenseAccount->name}\n"
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
        $description = $this->cleanDescription(trim($params['description'] ?? 'Pemasukan'));
        $incomeAccountName = $params['income_account'] ?? 'Pendapatan Lainnya';
        $depositAccountKeyword = $params['deposit_account'] ?? null;
        $date = ! empty($params['date']) ? Carbon::parse($params['date']) : now();

        $incomeAccount = $this->accountingService->findOrCreateIncomeAccount($incomeAccountName);
        $depositAccount = $this->accountingService->findPaymentAccount($depositAccountKeyword);

        $referenceNumber = (! empty($log->telegram_message_id) && ! empty($log->chat_id))
            ? "TG-{$log->chat_id}-{$log->telegram_message_id}"
            : null;

        // Idempotency: verify log does not already have a journal attached
        if ($log->fresh()->journal_entry_id !== null) {
            Log::info("Telegram message {$log->telegram_message_id} already attached to journal #{$log->journal_entry_id}. Skipping duplicate income.");

            return;
        }

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'income',
            amount: $amount,
            sourceAccount: $incomeAccount,
            destinationAccount: $depositAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage,
            referenceNumber: $referenceNumber
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

        $referenceNumber = (! empty($log->telegram_message_id) && ! empty($log->chat_id))
            ? "TG-{$log->chat_id}-{$log->telegram_message_id}"
            : null;

        // Idempotency: verify log does not already have a journal attached
        if ($log->fresh()->journal_entry_id !== null) {
            Log::info("Telegram message {$log->telegram_message_id} already attached to journal #{$log->journal_entry_id}. Skipping duplicate transfer.");

            return;
        }

        $journal = $this->accountingService->createSimpleTransaction(
            date: $date,
            type: 'transfer',
            amount: $amount,
            sourceAccount: $fromAccount,
            destinationAccount: $toAccount,
            description: $description,
            source: JournalSource::Telegram,
            receiptImage: $receiptImage,
            referenceNumber: $referenceNumber
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
     * Handle Query Transactions (on-demand history, frequency count, and details).
     */
    protected function executeQueryTransactions(string $chatId, array $params, string $rawText, TelegramMessage $log): void
    {
        $result = $this->accountingService->queryTransactions($params);

        $count = $result['total_count'];
        $totalAmount = $result['total_amount'];
        $periodLabel = $result['period_label'];
        $transactions = $result['transactions'];
        $walletAccount = $result['wallet_account'];
        $walletBalance = $result['wallet_balance'];

        // Determine title & emoji based on parameters
        $keyword = strtolower(trim($params['keyword'] ?? ''));
        $category = strtolower(trim($params['account_category'] ?? ''));
        $walletName = trim($params['wallet_name'] ?? '');

        $topic = 'TRANSAKSI';
        $emoji = '📋';

        if (str_contains($keyword, 'donasi') || str_contains($keyword, 'sedekah') || str_contains($keyword, 'zakat') || str_contains($category, 'donasi')) {
            $topic = 'DONASI & SEDEKAH';
            $emoji = '🤲';
        } elseif (str_contains($keyword, 'makan') || str_contains($keyword, 'minum') || str_contains($keyword, 'kopi') || str_contains($category, 'makan') || str_contains($category, 'kafe')) {
            $topic = 'MAKANAN & MINUMAN';
            $emoji = '🍽️';
        } elseif (str_contains($keyword, 'bensin') || str_contains($keyword, 'bbm') || str_contains($keyword, 'transport') || str_contains($category, 'transport')) {
            $topic = 'TRANSPORTASI & BENSIN';
            $emoji = '⛽';
        } elseif (str_contains($keyword, 'pulsa') || str_contains($keyword, 'paket') || str_contains($keyword, 'data') || str_contains($category, 'pulsa')) {
            $topic = 'PULSA & PAKET DATA';
            $emoji = '📱';
        } elseif (str_contains($keyword, 'belanja') || str_contains($keyword, 'groceries') || str_contains($category, 'dapur')) {
            $topic = 'BELANJA KEBUTUHAN';
            $emoji = '🛒';
        } elseif (! empty($walletName)) {
            $topic = 'MUTASI '.strtoupper($walletAccount?->name ?? $walletName);
            $emoji = '💳';
        } elseif (! empty($keyword)) {
            $topic = 'PENCARIAN "'.strtoupper($keyword).'"';
            $emoji = '🔍';
        } elseif (! empty($category)) {
            $topic = strtoupper($category);
            $emoji = '📊';
        }

        if ($count === 0) {
            $filterDesc = ! empty($keyword) ? "kata kunci <i>\"{$keyword}\"</i>" : (! empty($category) ? "kategori <i>\"{$category}\"</i>" : 'kriteria pencarian');
            $text = "{$emoji} <b>DATA {$topic} TIDAK DITEMUKAN</b>\n"
                ."🗓️ <i>Periode: {$periodLabel}</i>\n"
                ."━━━━━━━━━━━━━━━━━━━━\n\n"
                ."Belum ada catatan transaksi untuk {$filterDesc} pada periode ini.\n\n"
                .'<i>💡 Tips: Anda dapat mencatat transaksi terlebih dahulu atau memeriksa kembali tanggal pencarian.</i>';

            $this->sendMessage($chatId, $text);
            $log->update(['ai_response' => $text]);

            return;
        }

        $formattedTotal = 'Rp '.number_format($totalAmount, 0, ',', '.');
        $avgPerTransaction = ($count > 0) ? 'Rp '.number_format($totalAmount / $count, 0, ',', '.') : 'Rp 0';

        $totalLabel = 'Total Nominal';
        if ($result['total_expense'] > 0 && $result['total_income'] == 0) {
            $totalLabel = 'Total Pengeluaran';
        } elseif ($result['total_income'] > 0 && $result['total_expense'] == 0) {
            $totalLabel = 'Total Pemasukan';
        } elseif (! empty($walletName)) {
            $totalLabel = 'Total Mutasi';
        }

        $text = "{$emoji} <b>{$topic}</b>\n"
            ."🗓️ <i>{$periodLabel}</i> • <b>{$count} Transaksi</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."💰 <b>{$totalLabel}:</b> <code>{$formattedTotal}</code>\n";

        if ($count > 1) {
            $text .= "📊 <b>Rata-rata:</b> <code>{$avgPerTransaction}</code> / transaksi\n";
        }

        if ($walletBalance !== null && $walletAccount) {
            $formattedBal = 'Rp '.number_format($walletBalance, 0, ',', '.');
            $cleanWallet = $this->cleanWalletDisplayName($walletAccount->name);
            $text .= "💳 <b>Sisa Saldo {$cleanWallet}:</b> <code>{$formattedBal}</code>\n";
        }

        // Group all transactions by date (no limit)
        $grouped = [];
        foreach ($transactions as $t) {
            $grouped[$t['date']][] = $t;
        }

        $messages = [];
        $currentMessage = $text;

        foreach ($grouped as $date => $items) {
            $section = "\n📅 <b>{$date}</b>\n";
            foreach ($items as $item) {
                $amt = 'Rp '.number_format($item['amount'], 0, ',', '.');
                $walletSuffix = '';

                if (! $walletAccount && ! empty($item['wallet_name'])) {
                    $shortWallet = $this->cleanWalletDisplayName($item['wallet_name']);
                    if (! empty($shortWallet)) {
                        $walletSuffix = " <i>({$shortWallet})</i>";
                    }
                }

                $section .= "• {$item['description']} — <code>{$amt}</code>{$walletSuffix}\n";
            }

            // If adding this date section exceeds ~3800 characters, split into next message
            if (mb_strlen($currentMessage.$section) > 3800) {
                $messages[] = $currentMessage;
                $currentMessage = "{$emoji} <b>{$topic} (Lanjutan)</b>\n━━━━━━━━━━━━━━━━━━━━\n".$section;
            } else {
                $currentMessage .= $section;
            }
        }

        if (! empty($currentMessage)) {
            $messages[] = $currentMessage;
        }

        foreach ($messages as $msg) {
            $this->sendMessage($chatId, $msg);
        }

        $log->update(['ai_response' => implode("\n\n---\n\n", $messages)]);
    }

    /**
     * Clean and simplify wallet account display name.
     */
    protected function cleanWalletDisplayName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        if (str_contains($name, '➡️')) {
            $parts = explode('➡️', $name);

            return $this->cleanWalletDisplayName($parts[0]).' ➡️ '.$this->cleanWalletDisplayName($parts[1]);
        }

        $clean = preg_replace('/^(?:E-Wallet|Debit|Kredit)\s+/i', '', $name);
        $clean = preg_replace('/\s*\([^)]*\)/', '', $clean);
        $clean = preg_replace('/\s*\/.*$/', '', $clean);

        return trim($clean);
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

        if (str_starts_with($data, 'cfg_')) {
            $this->handleConfigCallbackQuery($id, $data, $chatId, $messageId);

            return;
        }
    }

    /**
     * Start the setup / config wizard (requests password if not authenticated).
     */
    public function startSetupWizard(string $chatId, ?TelegramMessage $log = null): void
    {
        // 1. Check if chat is locked out due to failed attempts
        if (Cache::has("tg_auth_lockout_{$chatId}")) {
            $ttl = Cache::get("tg_auth_lockout_{$chatId}_until", 'beberapa menit');
            $msg = "⛔ <b>Akses Konfigurasi Terkunci Sementara</b>\nTerlalu banyak percobaan password salah. Silakan tunggu hingga {$ttl} sebelum mencoba lagi.";
            $this->sendMessage($chatId, $msg);
            $log?->update(['intent' => 'auth_locked', 'ai_response' => $msg]);

            return;
        }

        // 2. Check if chat already has an active authenticated session (15 mins)
        if (Cache::has("tg_auth_session_{$chatId}")) {
            $this->sendConfigMainMenu($chatId);
            $log?->update(['intent' => 'open_config_menu', 'ai_response' => 'Config menu opened']);

            return;
        }

        // 3. Prompt for password
        Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_password', 'attempts' => 0], now()->addMinutes(5));

        $text = "🔐 <b>VERIFIKASI KEAMANAN SISTEM</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Untuk mengakses menu pengaturan sistem & file konfigurasi (<code>.env</code>) seperti <code>php artisan tmaccountant</code>, silakan masukkan <b>kata sandi (password)</b> akun Anda:\n\n"
            ."<i>🔒 Demi keamanan, pesan teks password yang Anda kirim akan <b>otomatis segera dihapus</b> oleh sistem dari chat.</i>\n"
            .'<i>💡 Ketik <code>/batal</code> jika ingin membatalkan.</i>';

        $this->sendMessage($chatId, $text);
        $log?->update(['intent' => 'auth_prompt_setup', 'ai_response' => $text]);
    }

    /**
     * Handle incoming text during active setup state.
     */
    protected function handleSetupTextResponse(string $chatId, string $text, ?int $messageId, array $setupState, ?TelegramMessage $log = null): void
    {
        $step = $setupState['step'] ?? '';

        if (in_array(strtolower($text), ['/batal', 'batal', '/cancel', 'cancel'])) {
            Cache::forget("tg_setup_state_{$chatId}");
            $this->sendMessage($chatId, '✓ Sesi konfigurasi telah dibatalkan.');

            return;
        }

        switch ($step) {
            case 'awaiting_password':
                // IMMEDIATELY delete user message containing password!
                if ($messageId) {
                    $this->deleteMessage($chatId, $messageId);
                }

                $owner = User::where('name', '!=', 'Admin')->latest('updated_at')->first()
                    ?? User::where('email', '!=', 'admin@example.com')->latest('updated_at')->first()
                    ?? User::latest('updated_at')->first()
                    ?? User::first();

                if ($owner && Hash::check($text, $owner->password)) {
                    Cache::forget("tg_setup_state_{$chatId}");
                    Cache::forget("tg_auth_lockout_{$chatId}");
                    Cache::put("tg_auth_session_{$chatId}", true, now()->addMinutes(15));

                    $this->sendMessage($chatId, "🔓 <b>Password Terverifikasi!</b>\nSelamat datang, <b>{$owner->name}</b>. Sesi konfigurasi aktif selama 15 menit.");

                    // If user had a pending direct set action
                    if (($setupState['pending_action'] ?? null) === 'direct_set') {
                        $this->handleDirectSetCommand($chatId, $setupState['pending_payload'] ?? '', null, $log);
                    } else {
                        $this->sendConfigMainMenu($chatId);
                    }
                } else {
                    $attempts = ($setupState['attempts'] ?? 0) + 1;
                    if ($attempts >= 3) {
                        Cache::forget("tg_setup_state_{$chatId}");
                        Cache::put("tg_auth_lockout_{$chatId}", true, now()->addMinutes(5));
                        Cache::put("tg_auth_lockout_{$chatId}_until", now()->addMinutes(5)->setTimezone('Asia/Jakarta')->translatedFormat('H:i').' WIB', now()->addMinutes(5));
                        $this->sendMessage($chatId, '⛔ <b>Password Salah 3 Kali!</b>\nAkses pengaturan dikunci sementara selama 5 menit demi keamanan sistem.');
                    } else {
                        Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_password', 'attempts' => $attempts], now()->addMinutes(5));
                        $sisa = 3 - $attempts;
                        $this->sendMessage($chatId, "❌ <b>Password Salah!</b>\nSisa kesempatan: {$sisa} kali lagi.\nSilakan ketik ulang password atau ketik <code>/batal</code>:");
                    }
                }
                break;

            case 'awaiting_admin_name':
                Cache::forget("tg_setup_state_{$chatId}");
                $newName = trim($text);
                $owner = User::latest('updated_at')->first() ?? User::first();
                if ($owner) {
                    $owner->update(['name' => $newName]);
                }
                $this->environmentService->update(['APP_OWNER_NAME' => $newName]);
                $this->sendMessage($chatId, "✅ <b>Nama Pemilik Berhasil Diperbarui!</b>\nNama panggilan AI sekarang: <b>{$newName}</b>.");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_admin_email':
                Cache::forget("tg_setup_state_{$chatId}");
                $newEmail = trim($text);
                if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $this->sendMessage($chatId, '⚠️ Format email tidak valid. Pembaruan dibatalkan.');
                    $this->sendConfigMainMenu($chatId);

                    return;
                }
                $owner = User::latest('updated_at')->first() ?? User::first();
                if ($owner) {
                    $conflict = User::where('email', $newEmail)->where('id', '!=', $owner->id)->exists();
                    if ($conflict) {
                        $this->sendMessage($chatId, "⚠️ Email <code>{$newEmail}</code> sudah digunakan oleh akun lain. Pembaruan dibatalkan.");
                        $this->sendConfigMainMenu($chatId);

                        return;
                    }
                    $owner->update(['email' => $newEmail]);
                }
                $this->sendMessage($chatId, "✅ <b>Email Login Admin Berhasil Diperbarui!</b>\nEmail baru: <code>{$newEmail}</code>.");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_admin_password':
                if ($messageId) {
                    $this->deleteMessage($chatId, $messageId);
                }
                Cache::forget("tg_setup_state_{$chatId}");
                if (strlen($text) < 8) {
                    $this->sendMessage($chatId, '⚠️ Password baru minimal 8 karakter. Pembaruan dibatalkan.');
                    $this->sendConfigMainMenu($chatId);

                    return;
                }
                $owner = User::latest('updated_at')->first() ?? User::first();
                if ($owner) {
                    $owner->update(['password' => Hash::make($text)]);
                }
                $this->sendMessage($chatId, "✅ <b>Password Baru Berhasil Disimpan!</b>\nSilakan gunakan password baru ini untuk login web maupun verifikasi bot.");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_tg_token':
                if ($messageId) {
                    $this->deleteMessage($chatId, $messageId);
                }
                Cache::forget("tg_setup_state_{$chatId}");
                $token = trim($text);
                $this->environmentService->update(['TELEGRAM_BOT_TOKEN' => $token]);
                $this->sendMessage($chatId, "✅ <b>Telegram Bot Token Berhasil Diperbarui!</b>\nToken baru telah disimpan di file <code>.env</code>.");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_tg_userids':
                Cache::forget("tg_setup_state_{$chatId}");
                $userIds = trim($text);
                $this->environmentService->update(['TELEGRAM_ALLOWED_USER_IDS' => $userIds]);
                $this->sendMessage($chatId, "✅ <b>Whitelisted Telegram User ID Berhasil Diperbarui!</b>\nID: <code>{$userIds}</code>.");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_ai_model':
                Cache::forget("tg_setup_state_{$chatId}");
                $modelName = trim($text);
                $provider = config('ai.default', 'openrouter');
                $key = match ($provider) {
                    'openrouter' => 'OPENROUTER_MODEL',
                    'deepseek' => 'DEEPSEEK_MODEL',
                    'gemini' => 'GEMINI_MODEL',
                    'openai' => 'OPENAI_MODEL',
                    'groq' => 'GROQ_MODEL',
                    'ollama' => 'OLLAMA_MODEL',
                    default => 'CUSTOM_AI_MODEL',
                };
                $this->environmentService->update([
                    $key => $modelName,
                    'AI_MODEL' => $modelName,
                ]);
                $this->sendMessage($chatId, "✅ <b>Model AI Berhasil Diperbarui!</b>\nModel aktif sekarang: <code>{$modelName}</code> (pada provider: <b>{$provider}</b>).");
                $this->sendConfigMainMenu($chatId);
                break;

            case 'awaiting_ai_apikey':
                if ($messageId) {
                    $this->deleteMessage($chatId, $messageId);
                }
                Cache::forget("tg_setup_state_{$chatId}");
                $apiKey = trim($text);
                $provider = config('ai.default', 'openrouter');
                $key = match ($provider) {
                    'openrouter' => 'OPENROUTER_API_KEY',
                    'deepseek' => 'DEEPSEEK_API_KEY',
                    'gemini' => 'GEMINI_API_KEY',
                    'openai' => 'OPENAI_API_KEY',
                    'groq' => 'GROQ_API_KEY',
                    default => 'CUSTOM_AI_API_KEY',
                };
                $this->environmentService->update([
                    $key => $apiKey,
                    'AI_API_KEY' => $apiKey,
                ]);
                $this->sendMessage($chatId, "✅ <b>API Key AI Berhasil Disimpan!</b>\nAPI Key untuk provider <b>{$provider}</b> telah diperbarui di <code>.env</code>.");
                $this->sendConfigMainMenu($chatId);
                break;

            default:
                Cache::forget("tg_setup_state_{$chatId}");
                $this->sendConfigMainMenu($chatId);
                break;
        }
    }

    /**
     * Handle direct /set KEY VALUE command.
     */
    protected function handleDirectSetCommand(string $chatId, string $text, ?int $messageId, ?TelegramMessage $log = null): void
    {
        // 1. Check authentication
        if (! Cache::has("tg_auth_session_{$chatId}")) {
            Cache::put("tg_setup_state_{$chatId}", [
                'step' => 'awaiting_password',
                'pending_action' => 'direct_set',
                'pending_payload' => $text,
                'attempts' => 0,
            ], now()->addMinutes(5));

            $msg = "🔐 <b>VERIFIKASI KEAMANAN</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
                ."Perubahan konfigurasi via command <code>/set</code> membutuhkan otentikasi kata sandi.\n\n"
                ."Silakan masukkan <b>kata sandi akun Anda</b>:\n"
                .'<i>🔒 Pesan password akan otomatis segera dihapus dari chat.</i>';
            $this->sendMessage($chatId, $msg);
            $log?->update(['intent' => 'auth_prompt_direct_set', 'ai_response' => $msg]);

            return;
        }

        // 2. Parse command
        if (! preg_match('/^\/set\s+([A-Za-z0-9_]+)\s+(.+)$/s', $text, $matches)) {
            $this->sendMessage($chatId, "⚠️ Format salah. Gunakan format:\n<code>/set KEY NILAI</code>\n\nContoh:\n<code>/set OPENROUTER_MODEL minimax/minimax-m3:free</code>");

            return;
        }

        $key = strtoupper(trim($matches[1]));
        $val = trim($matches[2]);

        if (! $this->environmentService->isKeyAllowed($key)) {
            $this->sendMessage($chatId, "⛔ Variabel <code>{$key}</code> tidak diizinkan untuk diubah via chat Telegram.");

            return;
        }

        $success = $this->environmentService->update([$key => $val]);

        if ($success) {
            $reply = "✅ <b>Konfigurasi Berhasil Diperbarui!</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
                ."🔑 <b>Variabel:</b> <code>{$key}</code>\n"
                ."📝 <b>Nilai Baru:</b> <code>{$val}</code>\n\n"
                .'<i>File .env dan cache konfigurasi aplikasi telah diperbarui.</i>';
        } else {
            $reply = '❌ Gagal menulis perubahan ke file .env. Pastikan permission file sesuai.';
        }

        $this->sendMessage($chatId, $reply);
        $log?->update(['intent' => 'direct_set_env', 'ai_response' => $reply]);
    }

    /**
     * Send Main Setup / Config menu.
     */
    public function sendConfigMainMenu(string $chatId, ?int $messageId = null): void
    {
        $text = "⚙️ <b>MENU PENGATURAN SISTEM (.ENV)</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Selamat datang di wizard konfigurasi interaktif TM Accountant.\n"
            ."Langkah pengaturan ini sama persis dengan wizard <code>php artisan tmaccountant</code> di terminal.\n\n"
            .'Silakan pilih menu pengaturan di bawah ini:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👤 [1] Setup Akun Admin & Pemilik', 'callback_data' => 'cfg_menu_admin'],
                ],
                [
                    ['text' => '📱 [2] Setup Integrasi Bot Telegram', 'callback_data' => 'cfg_menu_telegram'],
                ],
                [
                    ['text' => '🤖 [3] Setup AI Provider & Vision OCR', 'callback_data' => 'cfg_menu_ai'],
                ],
                [
                    ['text' => '📋 [4] Lihat Ringkasan .env Aktif', 'callback_data' => 'cfg_menu_summary'],
                ],
                [
                    ['text' => '🚪 Selesai & Kunci Sesi', 'callback_data' => 'cfg_menu_exit'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send Admin Account Setup submenu (Step 1).
     */
    public function sendAdminSetupMenu(string $chatId, ?int $messageId = null): void
    {
        $owner = User::where('name', '!=', 'Admin')->latest('updated_at')->first()
            ?? User::latest('updated_at')->first()
            ?? User::first();

        $name = $owner?->name ?? 'Admin';
        $email = $owner?->email ?? '-';
        $gender = $this->aiManager->getOwnerGender();
        $salutation = $this->aiManager->getOwnerSalutation();
        $genderLabel = $gender === 'perempuan' ? 'Perempuan (Teteh / Teh)' : 'Laki-laki (Akang / Kang)';

        $text = "👤 <b>[1/3] PENGATURAN AKUN ADMIN & PEMILIK</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."• <b>Nama Saat Ini:</b> {$name}\n"
            ."• <b>Email Login:</b> <code>{$email}</code>\n"
            ."• <b>Sapaan AI:</b> {$genderLabel} (<i>{$salutation}</i>)\n\n"
            .'Pilih data yang ingin Anda ubah:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📝 Ubah Nama Pemilik', 'callback_data' => 'cfg_admin_name'],
                ],
                [
                    ['text' => "⚧ Ubah Panggilan AI (Akang / Teteh) [{$salutation}]", 'callback_data' => 'cfg_admin_gender'],
                ],
                [
                    ['text' => '📧 Ubah Email Login Admin', 'callback_data' => 'cfg_admin_email'],
                ],
                [
                    ['text' => '🔑 Ubah Password Admin', 'callback_data' => 'cfg_admin_password'],
                ],
                [
                    ['text' => '🔙 Kembali ke Menu Utama', 'callback_data' => 'cfg_menu_main'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send Gender / Salutation selection menu (Akang / Teteh).
     */
    public function sendAdminGenderMenu(string $chatId, ?int $messageId = null): void
    {
        $currentGender = $this->aiManager->getOwnerGender();
        $ownerName = $this->aiManager->getOwnerName();
        $maleCheck = $currentGender === 'laki-laki' ? ' ✅' : '';
        $femaleCheck = $currentGender === 'perempuan' ? ' ✅' : '';

        $text = "⚧ <b>PILIH JENIS KELAMIN & PANGGILAN AI</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Pilih jenis kelamin Anda agar asisten AI memanggil Anda dengan sebutan yang tepat (<b>Akang</b> atau <b>Teteh</b>):\n\n"
            ."• <b>Laki-laki</b>: Dipanggil <i>Akang</i> / <i>Kang</i> (contoh: <i>Kang {$ownerName}</i>)\n"
            ."• <b>Perempuan</b>: Dipanggil <i>Teteh</i> / <i>Teh</i> (contoh: <i>Teh {$ownerName}</i>)\n";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "👨 Laki-laki (Akang / Kang){$maleCheck}", 'callback_data' => 'cfg_set_gender_male'],
                ],
                [
                    ['text' => "👩 Perempuan (Teteh / Teh){$femaleCheck}", 'callback_data' => 'cfg_set_gender_female'],
                ],
                [
                    ['text' => '🔙 Kembali ke Menu Admin', 'callback_data' => 'cfg_menu_admin'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send Telegram Bot Integration submenu (Step 2).
     */
    public function sendTelegramSetupMenu(string $chatId, ?int $messageId = null): void
    {
        $currentIds = implode(', ', (array) config('telegram.allowed_user_ids', []));
        $maskedToken = $this->environmentService->maskSecret(config('telegram.bot_token'));

        $text = "📱 <b>[2/3] INTEGRASI BOT TELEGRAM</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."• <b>Bot Token:</b> <code>{$maskedToken}</code>\n"
            ."• <b>Whitelisted User IDs:</b> <code>{$currentIds}</code>\n\n"
            .'Pilih opsi konfigurasi bot:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '👥 Ubah Whitelisted User ID(s)', 'callback_data' => 'cfg_tg_userids'],
                ],
                [
                    ['text' => '🔑 Ubah Bot Token', 'callback_data' => 'cfg_tg_token'],
                ],
                [
                    ['text' => '🔙 Kembali ke Menu Utama', 'callback_data' => 'cfg_menu_main'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send AI Provider & Vision OCR submenu (Step 3).
     */
    public function sendAiSetupMenu(string $chatId, ?int $messageId = null): void
    {
        $details = $this->aiManager->getActiveModelDetails();

        $text = "🤖 <b>[3/3] AI PROVIDER & VISION OCR SETUP</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."• <b>Provider Aktif:</b> {$details['provider_label']} (<code>{$details['provider']}</code>)\n"
            ."• <b>Model Aktif:</b> <code>{$details['model']}</code>\n"
            ."• <b>Vision OCR Struk:</b> {$details['ocr_label']}\n\n"
            .'Pilih bagian yang ingin Anda konfigurasikan:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '1️⃣ Pilih AI Engine Provider', 'callback_data' => 'cfg_ai_provider_list'],
                ],
                [
                    ['text' => '2️⃣ Ganti Model AI Aktif', 'callback_data' => 'cfg_ai_model_input'],
                ],
                [
                    ['text' => '3️⃣ Masukkan API Key Provider', 'callback_data' => 'cfg_ai_apikey_input'],
                ],
                [
                    ['text' => '4️⃣ Pilih Strategi Vision OCR Struk', 'callback_data' => 'cfg_ai_ocr_list'],
                ],
                [
                    ['text' => '🔙 Kembali ke Menu Utama', 'callback_data' => 'cfg_menu_main'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send AI Provider Selection menu.
     */
    public function sendAiProviderListMenu(string $chatId, ?int $messageId = null): void
    {
        $activeProvider = config('ai.default', 'openrouter');

        $text = "🧠 <b>PILIH AI ENGINE PROVIDER</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            .'Provider yang saat ini aktif: <b>'.strtoupper($activeProvider)."</b>\n\n"
            .'Pilih salah satu provider AI di bawah ini:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => ($activeProvider === 'openrouter' ? '✅ ' : '').'OpenRouter (Multi-Model / Minimax / Claude)', 'callback_data' => 'cfg_set_prov_openrouter'],
                ],
                [
                    ['text' => ($activeProvider === 'deepseek' ? '✅ ' : '').'DeepSeek (DeepSeek Chat Cloud)', 'callback_data' => 'cfg_set_prov_deepseek'],
                ],
                [
                    ['text' => ($activeProvider === 'gemini' ? '✅ ' : '').'Google Gemini (Native 3.7 Flash)', 'callback_data' => 'cfg_set_prov_gemini'],
                ],
                [
                    ['text' => ($activeProvider === 'openai' ? '✅ ' : '').'OpenAI (GPT-4o Mini)', 'callback_data' => 'cfg_set_prov_openai'],
                ],
                [
                    ['text' => ($activeProvider === 'groq' ? '✅ ' : '').'Groq Cloud (Fast LPU / Llama 3.3)', 'callback_data' => 'cfg_set_prov_groq'],
                ],
                [
                    ['text' => ($activeProvider === 'ollama' ? '✅ ' : '').'Ollama (Offline Local LLM)', 'callback_data' => 'cfg_set_prov_ollama'],
                ],
                [
                    ['text' => ($activeProvider === 'custom' ? '✅ ' : '').'Custom (OpenAI-compatible URL & Key)', 'callback_data' => 'cfg_set_prov_custom'],
                ],
                [
                    ['text' => '🔙 Kembali', 'callback_data' => 'cfg_menu_ai'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send Vision OCR Strategy menu.
     */
    public function sendAiOcrListMenu(string $chatId, ?int $messageId = null): void
    {
        $currentOcr = config('ai.ocr_mode', 'gemini');

        $text = "👁️ <b>PILIH STRATEGI VISION OCR STRUK</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            .'Strategi saat ini: <b>'.strtoupper($currentOcr)."</b>\n\n"
            .'Pilih strategi pembacaan foto struk/nota belanja:';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => ($currentOcr === 'gemini' ? '✅ ' : '').'🌟 Gemini Free Tier (Rekomendasi Hemat & Akurat)', 'callback_data' => 'cfg_set_ocr_gemini'],
                ],
                [
                    ['text' => ($currentOcr === 'auto' ? '✅ ' : '').'🔄 Gunakan Model Utama (Multimodal Vision)', 'callback_data' => 'cfg_set_ocr_auto'],
                ],
                [
                    ['text' => ($currentOcr === 'disabled' ? '✅ ' : '').'🚫 Nonaktifkan Pembacaan Struk (Teks Saja)', 'callback_data' => 'cfg_set_ocr_disabled'],
                ],
                [
                    ['text' => '🔙 Kembali', 'callback_data' => 'cfg_menu_ai'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Send Configuration Summary menu (.env review).
     */
    public function sendConfigSummaryMenu(string $chatId, ?int $messageId = null): void
    {
        $details = $this->aiManager->getActiveModelDetails();
        $owner = User::where('name', '!=', 'Admin')->latest('updated_at')->first() ?? User::first();
        $salutation = $this->aiManager->getOwnerSalutation();
        $genderLabel = $this->aiManager->getOwnerGender() === 'perempuan' ? 'Perempuan' : 'Laki-laki';
        $maskedKey = $this->environmentService->maskSecret(env('OPENROUTER_API_KEY', env('AI_API_KEY')));
        $maskedToken = $this->environmentService->maskSecret(config('telegram.bot_token'));
        $whitelisted = implode(', ', (array) config('telegram.allowed_user_ids', []));

        $text = "📋 <b>RINGKASAN KONFIGURASI SISTEM (.ENV)</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."👤 <b>Pemilik/Admin:</b> {$owner?->name} (<code>{$owner?->email}</code>) — Panggilan: <b>{$salutation}</b> ({$genderLabel})\n"
            ."🤖 <b>AI Provider:</b> {$details['provider_label']} (<code>{$details['provider']}</code>)\n"
            ."🧠 <b>AI Model:</b> <code>{$details['model']}</code>\n"
            ."🔑 <b>API Key:</b> <code>{$maskedKey}</code>\n"
            ."👁️ <b>Vision OCR:</b> {$details['ocr_label']}\n"
            ."📱 <b>Bot Token:</b> <code>{$maskedToken}</code>\n"
            ."👥 <b>Whitelisted IDs:</b> <code>{$whitelisted}</code>\n\n"
            .'<i>💡 Seluruh konfigurasi di atas tersimpan di file <code>.env</code> server.</i>';

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Muat Ulang', 'callback_data' => 'cfg_menu_summary'],
                    ['text' => '🔙 Menu Utama', 'callback_data' => 'cfg_menu_main'],
                ],
            ],
        ];

        if ($messageId) {
            $this->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }

    /**
     * Handle configuration callback queries.
     */
    protected function handleConfigCallbackQuery(string $callbackId, string $data, string $chatId, ?int $messageId): void
    {
        // Require active authentication
        if (! Cache::has("tg_auth_session_{$chatId}")) {
            $this->answerCallbackQuery($callbackId, 'Sesi telah kedaluwarsa. Silakan ketik /setup kembali.');
            $this->startSetupWizard($chatId);

            return;
        }

        // Refresh 15 min expiration on activity
        Cache::put("tg_auth_session_{$chatId}", true, now()->addMinutes(15));

        switch ($data) {
            case 'cfg_menu_main':
                $this->answerCallbackQuery($callbackId);
                $this->sendConfigMainMenu($chatId, $messageId);
                break;

            case 'cfg_menu_admin':
                $this->answerCallbackQuery($callbackId);
                $this->sendAdminSetupMenu($chatId, $messageId);
                break;

            case 'cfg_admin_gender':
                $this->answerCallbackQuery($callbackId);
                $this->sendAdminGenderMenu($chatId, $messageId);
                break;

            case 'cfg_set_gender_male':
                $this->environmentService->update(['APP_OWNER_GENDER' => 'laki-laki']);
                $this->answerCallbackQuery($callbackId, '✓ Sapaan AI diubah ke: Akang');
                $this->sendAdminSetupMenu($chatId, $messageId);
                break;

            case 'cfg_set_gender_female':
                $this->environmentService->update(['APP_OWNER_GENDER' => 'perempuan']);
                $this->answerCallbackQuery($callbackId, '✓ Sapaan AI diubah ke: Teteh');
                $this->sendAdminSetupMenu($chatId, $messageId);
                break;

            case 'cfg_admin_name':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_admin_name'], now()->addMinutes(5));
                $this->sendMessage($chatId, "👤 <b>UBAH NAMA PEMILIK / SAPAAN AI</b>\n\nSilakan ketik nama panggilan Anda yang baru (misal: <code>Kang Tama</code>):\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_admin_email':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_admin_email'], now()->addMinutes(5));
                $this->sendMessage($chatId, "📧 <b>UBAH EMAIL LOGIN ADMIN</b>\n\nSilakan ketik alamat email baru Anda untuk login di panel web:\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_admin_password':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_admin_password'], now()->addMinutes(5));
                $this->sendMessage($chatId, "🔑 <b>UBAH KATA SANDI (PASSWORD) ADMIN</b>\n\nSilakan ketik kata sandi baru (minimal 8 karakter):\n<i>🔒 Pesan akan otomatis segera dihapus dari chat demi keamanan.</i>\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_menu_telegram':
                $this->answerCallbackQuery($callbackId);
                $this->sendTelegramSetupMenu($chatId, $messageId);
                break;

            case 'cfg_tg_token':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_tg_token'], now()->addMinutes(5));
                $this->sendMessage($chatId, "🔑 <b>UBAH TELEGRAM BOT TOKEN</b>\n\nSilakan ketik token bot baru yang Anda dapatkan dari @BotFather:\n<i>🔒 Pesan akan otomatis segera dihapus dari chat demi keamanan.</i>\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_tg_userids':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_tg_userids'], now()->addMinutes(5));
                $this->sendMessage($chatId, "👥 <b>UBAH WHITELISTED USER ID(S)</b>\n\nSilakan ketik User ID Telegram yang diizinkan (dari @userinfobot). Pisahkan dengan tanda koma jika lebih dari satu (misal: <code>123456789, 987654321</code>):\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_menu_ai':
                $this->answerCallbackQuery($callbackId);
                $this->sendAiSetupMenu($chatId, $messageId);
                break;

            case 'cfg_ai_provider_list':
                $this->answerCallbackQuery($callbackId);
                $this->sendAiProviderListMenu($chatId, $messageId);
                break;

            case 'cfg_ai_model_input':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_ai_model'], now()->addMinutes(5));
                $this->sendMessage($chatId, "🧠 <b>GANTI MODEL AI AKTIF</b>\n\nSilakan ketik nama model yang ingin digunakan.\n\n👉 <b>Contoh:</b>\n• OpenRouter: <code>minimax/minimax-m3:free</code>\n• OpenRouter: <code>anthropic/claude-3.5-sonnet</code>\n• DeepSeek: <code>deepseek-chat</code>\n• Gemini: <code>gemini-3.7-flash</code>\n• OpenAI: <code>gpt-4o-mini</code>\n• Groq: <code>llama-3.3-70b-versatile</code>\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_ai_apikey_input':
                $this->answerCallbackQuery($callbackId);
                Cache::put("tg_setup_state_{$chatId}", ['step' => 'awaiting_ai_apikey'], now()->addMinutes(5));
                $this->sendMessage($chatId, "🔑 <b>MASUKKAN API KEY PROVIDER</b>\n\nSilakan ketik API Key untuk provider AI yang aktif:\n<i>🔒 Pesan API Key akan otomatis segera dihapus dari chat demi keamanan.</i>\n\n<i>Ketik /batal untuk membatalkan.</i>");
                break;

            case 'cfg_ai_ocr_list':
                $this->answerCallbackQuery($callbackId);
                $this->sendAiOcrListMenu($chatId, $messageId);
                break;

            case 'cfg_menu_summary':
                $this->answerCallbackQuery($callbackId);
                $this->sendConfigSummaryMenu($chatId, $messageId);
                break;

            case 'cfg_menu_exit':
                Cache::forget("tg_auth_session_{$chatId}");
                Cache::forget("tg_setup_state_{$chatId}");
                $this->answerCallbackQuery($callbackId, 'Sesi konfigurasi telah ditutup.');
                if ($messageId) {
                    $this->editMessageText($chatId, $messageId, "🔒 <b>SESI PENGATURAN DITUTUP</b>\n━━━━━━━━━━━━━━━━━━━━\n\nSemua perubahan telah disimpan ke file <code>.env</code> dan aktif di aplikasi.\n\n<i>Ketik /setup kapan saja jika ingin mengubah konfigurasi kembali.</i>");
                }
                break;

            default:
                if (str_starts_with($data, 'cfg_set_prov_')) {
                    $provider = str_replace('cfg_set_prov_', '', $data);
                    $updates = ['AI_PROVIDER' => $provider];

                    // Set default recommended models if not yet set
                    if ($provider === 'openrouter' && empty(env('OPENROUTER_MODEL'))) {
                        $updates['OPENROUTER_MODEL'] = 'minimax/minimax-m3:free';
                    } elseif ($provider === 'deepseek' && empty(env('DEEPSEEK_MODEL'))) {
                        $updates['DEEPSEEK_MODEL'] = 'deepseek-chat';
                    } elseif ($provider === 'gemini' && empty(env('GEMINI_MODEL'))) {
                        $updates['GEMINI_MODEL'] = 'gemini-3.7-flash';
                    }

                    $this->environmentService->update($updates);
                    $this->answerCallbackQuery($callbackId, "✓ Provider AI diubah ke: {$provider}");
                    $this->sendAiSetupMenu($chatId, $messageId);
                } elseif (str_starts_with($data, 'cfg_set_ocr_')) {
                    $ocr = str_replace('cfg_set_ocr_', '', $data);
                    $this->environmentService->update(['AI_OCR_MODE' => $ocr]);
                    $this->answerCallbackQuery($callbackId, "✓ Mode OCR diubah ke: {$ocr}");
                    $this->sendAiSetupMenu($chatId, $messageId);
                }
                break;
        }
    }

    /**
     * Send Default Wallet picker with inline keyboard.
     */
    protected function sendDefaultWalletPicker(string $chatId, ?TelegramMessage $log = null): void
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
        $log?->update(['intent' => 'set_default_wallet', 'ai_response' => $text]);
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
    protected function sendWalletsNotConfiguredMessage(string $chatId, ?TelegramMessage $log = null): void
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
        $log?->update(['intent' => 'wallets_not_configured', 'ai_response' => $text]);
    }

    /**
     * Send Saldo Kas & Bank summary.
     */
    protected function sendBalanceSummary(string $chatId, ?TelegramMessage $log = null): void
    {
        $cashAccounts = Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        if ($cashAccounts->isEmpty()) {
            $this->sendWalletsNotConfiguredMessage($chatId, $log);

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
        $log?->update(['intent' => 'query_account_balance', 'ai_response' => $text]);
    }

    /**
     * Send active AI model and provider information.
     */
    protected function sendModelInfo(string $chatId, ?TelegramMessage $log = null): void
    {
        $details = $this->aiManager->getActiveModelDetails();

        $text = "🤖 <b>INFORMASI ENGINE & MODEL AI</b>\n"
            ."━━━━━━━━━━━━━━━━━━━━\n\n"
            ."🧠 <b>Model Aktif:</b>\n<code>{$details['model']}</code>\n\n"
            ."🏢 <b>AI Provider:</b>\n<b>{$details['provider_label']}</b> (<code>{$details['provider']}</code>)\n\n"
            ."🌐 <b>API Endpoint:</b>\n<code>{$details['base_url']}</code>\n\n"
            ."👁️ <b>Vision OCR Struk:</b>\n<b>{$details['ocr_label']}</b>\n\n"
            ."⏱️ <b>Timeout Request:</b>\n<code>{$details['timeout']} detik</code>\n\n"
            ."━━━━━━━━━━━━━━━━━━━━\n"
            .'💡 <i>Tips: Anda dapat mengganti provider atau model AI kapan saja melalui file <code>.env</code> atau perintah <code>php artisan tm-accountant</code> di terminal.</i>';

        $this->sendMessage($chatId, $text);
        $log?->update(['intent' => 'query_ai_model', 'ai_response' => $text]);
    }

    /**
     * Send Help message.
     */
    protected function sendHelpMessage(string $chatId, ?TelegramMessage $log = null): void
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

📈 <b>Cek Kondisi & Info Sistem:</b>
• <i>"keuangan saya 1 minggu"</i>
• <i>"laporan pengeluaran bulan ini"</i>
• <i>/saldo</i> (Cek Saldo Kas & Bank)
• <i>/default</i> (Ganti dompet default)
• <i>/model</i> (Cek model & provider AI aktif)
• <i>/setup</i> (Wizard konfigurasi sistem .env)
• <i>/set KEY NILAI</i> (Ubah konfigurasi .env langsung)

📋 <b>Riwayat & Analisis Transaksi:</b>
• <i>"saya donasi berapa kali bulan ini dan habis berapa?"</i>
• <i>"beli makan dan minum dari tanggal 1 sampai sekarang apa saja?"</i>
• <i>"cek mutasi bca minggu ini"</i>

Setiap pencatatan transaksi otomatis dilengkapi tombol <b>Undo / Batal</b> jika ada kesalahan.
HELP;

        $this->sendMessage($chatId, $help);
        $log?->update(['intent' => 'help', 'ai_response' => $help]);
    }

    /**
     * Send Out of Topic guidance message.
     */
    protected function sendOutOfTopicGuidance(string $chatId, ?TelegramMessage $log = null): void
    {
        $text = "🤖 <b>ASISTEN PENCATATAN KEUANGAN PRIBADI</b>\n━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Maaf, saya hanya diprogram khusus untuk mencatat transaksi dan menyajikan laporan keuangan Anda.\n\n"
            ."Mohon kirimkan pesan terkait mutasi atau ringkasan keuangan Anda.\n\n"
            ."👉 <b>Contoh yang didukung:</b>\n"
            ."• <i>\"beli bensin 50rb pake bca\"</i> (Pengeluaran)\n"
            ."• <i>\"gaji masuk 15jt ke mandiri\"</i> (Pemasukan)\n"
            ."• <i>\"transfer bca ke gopay 100rb\"</i> (Transfer Saldo)\n"
            ."• <i>\"saya donasi berapa kali bulan ini\"</i> (Riwayat & Frekuensi)\n"
            ."• <i>/saldo</i> (Cek Saldo Kas & Bank)\n"
            .'• <i>"keuangan saya 1 minggu"</i> (Laporan Ringkas)';

        $this->sendMessage($chatId, $text);
        $log?->update(['intent' => 'general_chat', 'ai_response' => $text]);
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
     * Delete a message in Telegram.
     */
    public function deleteMessage(string|int $chatId, int $messageId): array
    {
        if (empty($this->botToken)) {
            return [];
        }

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        $response = Http::post("https://api.telegram.org/bot{$this->botToken}/deleteMessage", $payload);

        return $response->json() ?? [];
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

    /**
     * Clean redundant payment account phrases from description text.
     */
    public function cleanDescription(string $description): string
    {
        $cleaned = trim($description);
        $pattern = '/\s+(?:dari|pakai|pake|via|lewat|menggunakan|dg)\s+(?:kartu\s+debit|kartu\s+kredit|kartu|rekening|bank\s+[a-z0-9]+|bca|mandiri|bni|bri|jago|gopay|ovo|dana|shopeepay|shopee\s+pay|cash|tunai|dompet)\b.*$/iu';
        $cleaned = preg_replace($pattern, '', $cleaned);

        return trim($cleaned) ?: $description;
    }
}
