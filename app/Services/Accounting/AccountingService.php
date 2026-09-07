<?php

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\TelegramMessageStatus;
use App\Events\TransactionRecorded;
use App\Events\TransactionReverted;
use App\Events\WalletBalanceUpdated;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\TelegramMessage;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    /**
     * Create a balanced multi-line journal entry.
     *
     * @param  array{date?: CarbonInterface|string, description: string, source?: JournalSource|string, reference_number?: ?string, created_by?: ?int}  $entryData
     * @param  array<int, array{account_id: int, debit?: float, credit?: float, memo?: ?string}>  $items
     */
    public function createJournalEntry(array $entryData, array $items): JournalEntry
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Jurnal harus memiliki minimal dua baris item.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $totalDebit += (float) ($item['debit'] ?? 0);
            $totalCredit += (float) ($item['credit'] ?? 0);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                sprintf('Jurnal tidak seimbang! Total Debit (Rp %s) harus sama dengan Total Kredit (Rp %s).', number_format($totalDebit, 2), number_format($totalCredit, 2))
            );
        }

        if ($totalDebit <= 0) {
            throw new InvalidArgumentException('Nilai transaksi jurnal harus lebih besar dari 0.');
        }

        $entry = DB::transaction(function () use ($entryData, $items) {
            // Idempotency check: if reference_number is provided, lock and verify whether it was already recorded
            if (! empty($entryData['reference_number'])) {
                $source = $entryData['source'] ?? JournalSource::Web;
                $sourceValue = $source instanceof JournalSource ? $source->value : $source;

                $existing = JournalEntry::where('reference_number', $entryData['reference_number'])
                    ->where('source', $sourceValue)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing->load('items.account');
                }
            }

            $entry = JournalEntry::create([
                'date' => isset($entryData['date']) ? Carbon::parse($entryData['date']) : now(),
                'description' => $entryData['description'],
                'source' => $entryData['source'] ?? JournalSource::Web,
                'reference_number' => $entryData['reference_number'] ?? null,
                'created_by' => $entryData['created_by'] ?? null,
                'receipt_image' => $entryData['receipt_image'] ?? null,
            ]);

            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => (float) ($item['debit'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $entry->load('items.account');
        });

        // Broadcast real-time events via Laravel Reverb only if newly created
        if ($entry->wasRecentlyCreated) {
            event(new TransactionRecorded($entry));

            $accountIds = collect($items)->pluck('account_id')->unique();
            $affectedWallets = Account::whereIn('id', $accountIds)->where('category', AccountCategory::CashAndBank)->get();
            foreach ($affectedWallets as $wallet) {
                event(new WalletBalanceUpdated($wallet));
            }
        }

        return $entry;
    }

    /**
     * Create a simple transaction (Expense, Income, or Transfer).
     */
    public function createSimpleTransaction(
        CarbonInterface|string $date,
        string $type,
        float $amount,
        int|Account $sourceAccount,
        int|Account $destinationAccount,
        string $description,
        JournalSource|string $source = JournalSource::Web,
        ?int $createdBy = null,
        ?string $receiptImage = null,
        ?string $referenceNumber = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal transaksi harus lebih dari 0.');
        }

        $sourceAccountId = $sourceAccount instanceof Account ? $sourceAccount->id : $sourceAccount;
        $destAccountId = $destinationAccount instanceof Account ? $destinationAccount->id : $destinationAccount;

        $type = strtolower($type);

        if ($type === 'expense') {
            // Expense: Debit Expense Account, Credit Cash/Bank Account
            $items = [
                ['account_id' => $destAccountId, 'debit' => $amount, 'credit' => 0, 'memo' => $description],
                ['account_id' => $sourceAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => $description],
            ];
        } elseif ($type === 'income') {
            // Income: Debit Cash/Bank Account, Credit Revenue Account
            $items = [
                ['account_id' => $destAccountId, 'debit' => $amount, 'credit' => 0, 'memo' => $description],
                ['account_id' => $sourceAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => $description],
            ];
        } elseif ($type === 'transfer') {
            // Transfer: Debit To Bank/Cash, Credit From Bank/Cash
            $items = [
                ['account_id' => $destAccountId, 'debit' => $amount, 'credit' => 0, 'memo' => "Transfer ke akun tujuan: {$description}"],
                ['account_id' => $sourceAccountId, 'debit' => 0, 'credit' => $amount, 'memo' => "Transfer dari akun sumber: {$description}"],
            ];
        } else {
            throw new InvalidArgumentException("Tipe transaksi tidak valid: {$type}. Gunakan expense, income, atau transfer.");
        }

        return $this->createJournalEntry([
            'date' => $date,
            'description' => $description,
            'source' => $source,
            'reference_number' => $referenceNumber,
            'created_by' => $createdBy,
            'receipt_image' => $receiptImage,
        ], $items);
    }

    /**
     * Revert / Undo a Journal Entry safely.
     */
    public function revertJournalEntry(int|JournalEntry $journalEntry): bool
    {
        $entry = $journalEntry instanceof JournalEntry ? $journalEntry : JournalEntry::find($journalEntry);

        if (! $entry) {
            return false;
        }

        $affectedAccountIds = $entry->items()->pluck('account_id')->unique();

        $result = DB::transaction(function () use ($entry) {
            $entryNumber = $entry->entry_number;
            $description = $entry->description;

            activity()
                ->performedOn($entry)
                ->useLog('transaksi_jurnal')
                ->event('undo')
                ->withProperties([
                    'entry_number' => $entryNumber,
                    'description' => $description,
                ])
                ->log("Membatalkan / Undo transaksi jurnal {$entryNumber} ({$description})");

            // Update linked telegram message status if any
            TelegramMessage::where('journal_entry_id', $entry->id)
                ->update(['status' => TelegramMessageStatus::Reverted]);

            $entry->items()->delete();

            return $entry->delete();
        });

        if ($result) {
            event(new TransactionReverted($entry));

            $affectedWallets = Account::whereIn('id', $affectedAccountIds)->where('category', AccountCategory::CashAndBank)->get();
            foreach ($affectedWallets as $wallet) {
                event(new WalletBalanceUpdated($wallet));
            }
        }

        return (bool) $result;
    }

    /**
     * Find default cash or bank account.
     */
    public function getDefaultPaymentAccount(): Account
    {
        // 1. Try account marked as default
        $default = Account::where('category', AccountCategory::CashAndBank)
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->first();

        if ($default) {
            return $default;
        }

        // 2. Fallback to Kas Tunai (1-10001) or first active wallet
        return Account::where('category', AccountCategory::CashAndBank)
            ->where('code', '1-10001')
            ->first() ?? Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->first() ?? Account::where('category', AccountCategory::CashAndBank)->firstOrFail();
    }

    /**
     * Set initial balance for a wallet account by posting to Modal Awal (Equity).
     */
    public function setInitialBalance(Account $account, float $amount, ?Carbon $date = null, ?User $creator = null): ?JournalEntry
    {
        if ($amount <= 0) {
            return null;
        }

        $date = $date ?? now()->startOfMonth();

        $equityAccount = Account::where('code', '3-10001')->first()
            ?? Account::where('type', AccountType::Equity)->whereNotNull('parent_id')->first()
            ?? Account::where('type', AccountType::Equity)->firstOrFail();

        return $this->createJournalEntry([
            'date' => $date,
            'description' => "Saldo Awal: {$account->name}",
            'source' => JournalSource::System,
            'notes' => 'Pencatatan saldo awal pembukuan dompet',
        ], [
            [
                'account_id' => $account->id,
                'debit' => $amount,
                'credit' => 0,
                'memo' => "Saldo awal {$account->name}",
            ],
            [
                'account_id' => $equityAccount->id,
                'debit' => 0,
                'credit' => $amount,
                'memo' => 'Modal awal / Saldo awal ekuitas',
            ],
        ], $creator);
    }

    /**
     * Adjust wallet balance to match physical/actual bank balance.
     */
    public function adjustBalance(Account $account, float $realBalance, string $reason = 'Penyesuaian Saldo (Opname)', ?Carbon $date = null, ?User $creator = null): ?JournalEntry
    {
        $currentBalance = $account->balance;
        $diff = $realBalance - $currentBalance;

        if (abs($diff) < 0.01) {
            return null; // No adjustment needed
        }

        activity()
            ->causedBy($creator)
            ->performedOn($account)
            ->useLog('dompet_rekening')
            ->event('adjustment')
            ->withProperties([
                'account_name' => $account->name,
                'previous_balance' => $currentBalance,
                'new_balance' => $realBalance,
                'difference' => $diff,
                'reason' => $reason,
            ])
            ->log("Penyesuaian saldo dompet {$account->name} dari Rp ".number_format($currentBalance, 0, ',', '.').' menjadi Rp '.number_format($realBalance, 0, ',', '.'));

        $date = $date ?? now();

        if ($diff > 0) {
            // Surplus: Debit Wallet, Credit Other Revenue (Penyesuaian Saldo)
            $adjustmentAccount = Account::where('code', '4-20001')->first()
                ?? Account::where('type', AccountType::Revenue)->whereNotNull('parent_id')->first()
                ?? Account::where('type', AccountType::Revenue)->firstOrFail();

            return $this->createJournalEntry([
                'date' => $date,
                'description' => "Penyesuaian Saldo (Surplus): {$account->name} - {$reason}",
                'source' => JournalSource::Web,
                'notes' => 'Koreksi saldo dari Rp '.number_format($currentBalance, 0, ',', '.').' menjadi Rp '.number_format($realBalance, 0, ',', '.'),
            ], [
                [
                    'account_id' => $account->id,
                    'debit' => $diff,
                    'credit' => 0,
                    'memo' => 'Penambahan saldo penyesuaian',
                ],
                [
                    'account_id' => $adjustmentAccount->id,
                    'debit' => 0,
                    'credit' => $diff,
                    'memo' => 'Selisih lebih kas & bank',
                ],
            ], $creator);
        } else {
            // Deficit: Debit Other Expense (Penyesuaian Saldo), Credit Wallet
            $absDiff = abs($diff);
            $adjustmentAccount = Account::where('code', '6-30001')->first()
                ?? Account::where('type', AccountType::Expense)->whereNotNull('parent_id')->first()
                ?? Account::where('type', AccountType::Expense)->firstOrFail();

            return $this->createJournalEntry([
                'date' => $date,
                'description' => "Penyesuaian Saldo (Defisit): {$account->name} - {$reason}",
                'source' => JournalSource::Web,
                'notes' => 'Koreksi saldo dari Rp '.number_format($currentBalance, 0, ',', '.').' menjadi Rp '.number_format($realBalance, 0, ',', '.'),
            ], [
                [
                    'account_id' => $adjustmentAccount->id,
                    'debit' => $absDiff,
                    'credit' => 0,
                    'memo' => 'Selisih kurang kas & bank',
                ],
                [
                    'account_id' => $account->id,
                    'debit' => 0,
                    'credit' => $absDiff,
                    'memo' => 'Pengurangan saldo penyesuaian',
                ],
            ], $creator);
        }
    }

    /**
     * Complete Dynamic Onboarding Wallet Setup in batch.
     *
     * @param  array<string, array{name: string, type?: string, initial_balance?: float, account_number?: ?string, account_holder?: ?string}>  $walletsData
     */
    public function completeDynamicWalletOnboarding(User $user, array $walletsData, ?string $defaultWalletKey = null): void
    {
        DB::transaction(function () use ($user, $walletsData, $defaultWalletKey) {
            $parent = Account::where('code', '1-10000')->first()
                ?? Account::where('category', AccountCategory::CashAndBank)->whereNull('parent_id')->first();

            // Find current max code
            $lastCode = Account::where('category', AccountCategory::CashAndBank)
                ->whereNotNull('parent_id')
                ->orderByDesc('code')
                ->value('code');

            $nextIndex = 10001;
            if ($lastCode && preg_match('/^1-(\d+)$/', $lastCode, $m)) {
                $nextIndex = max($nextIndex, (int) $m[1] + 1);
            }

            $createdAccounts = [];

            foreach ($walletsData as $key => $data) {
                $name = trim($data['name'] ?? '');
                if (empty($name)) {
                    continue;
                }

                $account = Account::where('category', AccountCategory::CashAndBank)
                    ->where('name', $name)
                    ->first();

                if (! $account) {
                    $code = '1-'.str_pad((string) $nextIndex++, 5, '0', STR_PAD_LEFT);
                    $account = Account::create([
                        'code' => $code,
                        'name' => $name,
                        'account_number' => $data['account_number'] ?? null,
                        'account_holder' => $data['account_holder'] ?? null,
                        'type' => AccountType::Asset,
                        'category' => AccountCategory::CashAndBank,
                        'parent_id' => $parent?->id,
                        'is_system' => false,
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                } else {
                    $account->update([
                        'account_number' => $data['account_number'] ?? $account->account_number,
                        'account_holder' => $data['account_holder'] ?? $account->account_holder,
                        'is_active' => true,
                    ]);
                }

                $createdAccounts[$key] = $account;

                // Post initial balance
                $initialBalance = (float) ($data['initial_balance'] ?? 0);
                if ($initialBalance > 0 && $account->balance == 0) {
                    $this->setInitialBalance($account, $initialBalance, now(), $user);
                }
            }

            // Set default wallet
            if ($defaultWalletKey && isset($createdAccounts[$defaultWalletKey])) {
                $createdAccounts[$defaultWalletKey]->markAsDefault();
            } elseif (! empty($createdAccounts)) {
                reset($createdAccounts)->markAsDefault();
            }

            // Mark user onboarding completed
            $user->update(['wallet_setup_completed_at' => now()]);
        });
    }

    /**
     * Complete Onboarding Wallet Setup in batch.
     */
    public function completeWalletOnboarding(User $user, array $selectedAccountIds, array $walletDetails, ?int $defaultAccountId = null): void
    {
        DB::transaction(function () use ($user, $selectedAccountIds, $walletDetails, $defaultAccountId) {
            // 1. Activate selected wallets, deactivate unselected
            Account::where('category', AccountCategory::CashAndBank)
                ->whereNotNull('parent_id')
                ->each(function (Account $acc) use ($selectedAccountIds) {
                    $isActive = in_array($acc->id, $selectedAccountIds);
                    $acc->update(['is_active' => $isActive]);
                });

            // 2. Update wallet details and post initial balances
            foreach ($selectedAccountIds as $accountId) {
                $acc = Account::find($accountId);
                if (! $acc) {
                    continue;
                }

                $details = $walletDetails[$accountId] ?? [];
                $acc->update([
                    'account_number' => $details['account_number'] ?? $acc->account_number,
                    'account_holder' => $details['account_holder'] ?? $acc->account_holder,
                ]);

                $initialBalance = (float) ($details['initial_balance'] ?? 0);
                if ($initialBalance > 0 && $acc->balance == 0) {
                    $this->setInitialBalance($acc, $initialBalance, now(), $user);
                }
            }

            // 3. Set default wallet
            if ($defaultAccountId && in_array($defaultAccountId, $selectedAccountIds)) {
                $defaultAcc = Account::find($defaultAccountId);
                $defaultAcc?->markAsDefault();
            }

            // 4. Mark user wizard as completed
            $user->update(['wallet_setup_completed_at' => now()]);
        });
    }

    /**
     * Find best matching Cash / Bank account by name or keyword.
     */
    public function findPaymentAccount(?string $keyword = null): Account
    {
        if (empty($keyword)) {
            return $this->getDefaultPaymentAccount();
        }

        $keyword = trim($keyword);

        $account = Account::where('category', AccountCategory::CashAndBank)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%");
            })
            ->first();

        return $account ?? $this->getDefaultPaymentAccount();
    }

    /**
     * Find or dynamically create an Expense account.
     */
    public function findOrCreateExpenseAccount(string $name): Account
    {
        $name = trim($name);

        // 1. Try exact/partial code or name match across Expense, Liability, and Receivable accounts
        $existing = Account::whereIn('type', [AccountType::Expense, AccountType::Liability, AccountType::Asset])
            ->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                    ->orWhere('code', $name);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Check if intent implies paying debt / liability (e.g. "Hutang", "Pinjaman", "Cicilan", "Paylater", "Kartu Kredit")
        $lower = strtolower($name);
        if (str_contains($lower, 'hutang') || str_contains($lower, 'pinjaman') || str_contains($lower, 'utang') || str_contains($lower, 'cicilan') || str_contains($lower, 'paylater')) {
            $liabilityAcc = Account::where('type', AccountType::Liability)
                ->where(function ($q) use ($name) {
                    $q->where('name', 'like', "%{$name}%")
                        ->orWhere('name', 'like', '%Hutang Pribadi%')
                        ->orWhere('name', 'like', '%Kewajiban%');
                })
                ->first();

            if ($liabilityAcc) {
                return $liabilityAcc;
            }
        }

        // 3. Check if intent implies giving loan / accounts receivable (e.g. "Piutang", "Talangan", "Kasbon", "Pinjamkan")
        if (str_contains($lower, 'piutang') || str_contains($lower, 'talang') || str_contains($lower, 'kasbon') || str_contains($lower, 'pinjamkan')) {
            $receivableAcc = Account::where('category', AccountCategory::AccountsReceivable)
                ->first();

            if ($receivableAcc) {
                return $receivableAcc;
            }
        }

        // 4. Check if intent implies investment / savings / arisan (e.g. "Investasi", "Reksadana", "Saham", "Stockbit", "Bibit", "Arisan", "Deposito", "Emas", "Crypto")
        if (str_contains($lower, 'investasi') || str_contains($lower, 'reksadana') || str_contains($lower, 'reksa dana') || str_contains($lower, 'saham') || str_contains($lower, 'stockbit') || str_contains($lower, 'bibit') || str_contains($lower, 'rdn') || str_contains($lower, 'deposito') || str_contains($lower, 'emas') || str_contains($lower, 'crypto') || str_contains($lower, 'kripto') || str_contains($lower, 'arisan')) {
            $investmentAcc = Account::where('code', '1-10201')->first()
                ?? Account::where('category', AccountCategory::OtherCurrentAsset)->first();

            if ($investmentAcc) {
                return $investmentAcc;
            }
        }

        // 5. Check if intent implies fixed asset purchase (e.g. "Gadget", "Elektronik", "Laptop", "HP", "Kendaraan", "Motor", "Mobil")
        if (str_contains($lower, 'elektronik') || str_contains($lower, 'gadget') || str_contains($lower, 'laptop') || str_contains($lower, 'handphone') || str_contains($lower, 'komputer')) {
            $fixedAssetAcc = Account::where('code', '1-20001')->first();
            if ($fixedAssetAcc) {
                return $fixedAssetAcc;
            }
        }

        if (str_contains($lower, 'kendaraan') || str_contains($lower, 'motor') || str_contains($lower, 'mobil')) {
            $vehicleAcc = Account::where('code', '1-20002')->first();
            if ($vehicleAcc) {
                return $vehicleAcc;
            }
        }

        // 6. Fallback: Search Expense accounts
        $expenseAcc = Account::where('type', AccountType::Expense)
            ->where('name', 'like', "%{$name}%")
            ->first();

        if ($expenseAcc) {
            return $expenseAcc;
        }

        // Parent fallback: Beban Kebutuhan Pokok (6-10000) or Beban Lain-lain (6-30000)
        $parent = Account::where('code', '6-10000')->first() ?? Account::where('type', AccountType::Expense)->first();

        // Generate next code
        $lastCode = Account::where('type', AccountType::Expense)
            ->where('parent_id', $parent?->id)
            ->orderByDesc('code')
            ->value('code');

        $nextCode = '6-10099';
        if ($lastCode && preg_match('/^6-(\d+)$/', $lastCode, $m)) {
            $nextCode = '6-'.((int) $m[1] + 1);
        }

        return Account::create([
            'code' => $nextCode,
            'name' => $name,
            'type' => AccountType::Expense,
            'category' => AccountCategory::OperatingExpense,
            'parent_id' => $parent?->id,
            'is_system' => false,
            'is_active' => true,
            'description' => 'Akun beban dibuat otomatis dari pencatatan chatbot',
        ]);
    }

    /**
     * Find or dynamically create a Revenue/Income account.
     */
    public function findOrCreateIncomeAccount(string $name): Account
    {
        $name = trim($name);

        $existing = Account::where('type', AccountType::Revenue)
            ->where('name', 'like', "%{$name}%")
            ->first();

        if ($existing) {
            return $existing;
        }

        $parent = Account::where('code', '4-10000')->first() ?? Account::where('type', AccountType::Revenue)->first();

        $lastCode = Account::where('type', AccountType::Revenue)
            ->where('parent_id', $parent?->id)
            ->orderByDesc('code')
            ->value('code');

        $nextCode = '4-10099';
        if ($lastCode && preg_match('/^4-(\d+)$/', $lastCode, $m)) {
            $nextCode = '4-'.((int) $m[1] + 1);
        }

        return Account::create([
            'code' => $nextCode,
            'name' => $name,
            'type' => AccountType::Revenue,
            'category' => AccountCategory::OperatingRevenue,
            'parent_id' => $parent?->id,
            'is_system' => false,
            'is_active' => true,
            'description' => 'Akun pendapatan dibuat otomatis dari pencatatan chatbot',
        ]);
    }

    /**
     * Query detailed transactions matching criteria with totals and formatting.
     *
     * @param  array{
     *     keyword?: ?string,
     *     account_category?: ?string,
     *     wallet_name?: ?string,
     *     period?: ?string,
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     transaction_type?: ?string,
     *     limit?: ?int
     * }  $filters
     * @return array{
     *     period_label: string,
     *     start_date: string,
     *     end_date: string,
     *     total_count: int,
     *     total_amount: float,
     *     total_expense: float,
     *     total_income: float,
     *     transactions: array<int, array{
     *         id: int,
     *         entry_number: string,
     *         date: string,
     *         raw_date: Carbon,
     *         description: string,
     *         amount: float,
     *         type: string,
     *         wallet_name: string,
     *         category_name: string
     *     }>,
     *     wallet_account: ?Account,
     *     wallet_balance: ?float
     * }
     */
    public function queryTransactions(array $filters): array
    {
        $now = now()->setTimezone('Asia/Jakarta');
        $period = $filters['period'] ?? 'this_month';
        $startDate = null;
        $endDate = null;

        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $periodLabel = $startDate->translatedFormat('d M Y').' s/d '.$endDate->translatedFormat('d M Y');
        } elseif (! empty($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = $now->copy()->endOfDay();
            $periodLabel = $startDate->translatedFormat('d M Y').' s/d '.$endDate->translatedFormat('d M Y');
        } else {
            switch ($period) {
                case 'today':
                    $startDate = $now->copy()->startOfDay();
                    $endDate = $now->copy()->endOfDay();
                    $periodLabel = 'Hari Ini ('.$now->translatedFormat('d M Y').')';
                    break;
                case 'yesterday':
                    $startDate = $now->copy()->subDay()->startOfDay();
                    $endDate = $now->copy()->subDay()->endOfDay();
                    $periodLabel = 'Kemarin ('.$startDate->translatedFormat('d M Y').')';
                    break;
                case 'this_week':
                    $startDate = $now->copy()->startOfWeek();
                    $endDate = $now->copy()->endOfWeek();
                    $periodLabel = 'Minggu Ini ('.$startDate->translatedFormat('d M').' - '.$endDate->translatedFormat('d M Y').')';
                    break;
                case 'last_week':
                    $startDate = $now->copy()->subWeek()->startOfWeek();
                    $endDate = $now->copy()->subWeek()->endOfWeek();
                    $periodLabel = 'Minggu Lalu ('.$startDate->translatedFormat('d M').' - '.$endDate->translatedFormat('d M Y').')';
                    break;
                case 'last_month':
                    $startDate = $now->copy()->subMonth()->startOfMonth();
                    $endDate = $now->copy()->subMonth()->endOfMonth();
                    $periodLabel = 'Bulan Lalu ('.$startDate->translatedFormat('F Y').')';
                    break;
                case 'this_year':
                    $startDate = $now->copy()->startOfYear();
                    $endDate = $now->copy()->endOfYear();
                    $periodLabel = 'Tahun Ini ('.$now->format('Y').')';
                    break;
                case 'this_month':
                default:
                    $startDate = $now->copy()->startOfMonth();
                    $endDate = $now->copy()->endOfMonth();
                    $periodLabel = 'Bulan Ini ('.$now->translatedFormat('F Y').')';
                    break;
            }
        }

        $query = JournalEntry::with(['items.account'])
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        // Filter by wallet (payment/deposit account)
        $walletName = trim($filters['wallet_name'] ?? '');
        $walletAccount = null;
        if (! empty($walletName)) {
            $walletAccount = $this->findPaymentAccount($walletName);
            if ($walletAccount) {
                $query->whereHas('items', function ($q) use ($walletAccount) {
                    $q->where('account_id', $walletAccount->id);
                });
            }
        }

        // Filter by account category and keyword
        $category = trim($filters['account_category'] ?? '');
        $keyword = trim($filters['keyword'] ?? '');

        // If keyword represents a general category (e.g. "makan", "makanan", "makan dan minum"), treat it as category
        $isKeywordCategoryLike = false;
        if (! empty($keyword) && (
            str_contains(strtolower($keyword), 'makan') ||
            str_contains(strtolower($keyword), 'minum') ||
            str_contains(strtolower($keyword), 'donasi') ||
            str_contains(strtolower($keyword), 'sedekah') ||
            str_contains(strtolower($keyword), 'bensin') ||
            str_contains(strtolower($keyword), 'pulsa')
        )) {
            $isKeywordCategoryLike = true;
            if (empty($category)) {
                $category = $keyword;
                $keyword = '';
            }
        }

        // Apply account category filter
        if (! empty($category)) {
            $catLower = strtolower($category);
            $targetAccountIds = [];

            if (str_contains($catLower, 'makan') || str_contains($catLower, 'minum') || str_contains($catLower, 'kuliner') || str_contains($catLower, 'jajan') || str_contains($catLower, 'kopi') || str_contains($catLower, 'kafe')) {
                // Include both Makanan & Minuman and Kafe, Resto & Nongkrong
                $targetAccountIds = Account::where('type', AccountType::Expense)
                    ->where(function ($q) {
                        $q->where('name', 'like', '%Makan%')
                            ->orWhere('name', 'like', '%Minum%')
                            ->orWhere('name', 'like', '%Kafe%')
                            ->orWhere('name', 'like', '%Resto%');
                    })
                    ->pluck('id')
                    ->toArray();
            } elseif (str_contains($catLower, 'donasi') || str_contains($catLower, 'sedekah') || str_contains($catLower, 'zakat') || str_contains($catLower, 'infaq')) {
                $targetAccountIds = Account::where('name', 'like', '%Donasi%')
                    ->orWhere('name', 'like', '%Zakat%')
                    ->orWhere('name', 'like', '%Sedekah%')
                    ->pluck('id')
                    ->toArray();
            } elseif (str_contains($catLower, 'bensin') || str_contains($catLower, 'transport') || str_contains($catLower, 'bbm')) {
                $targetAccountIds = Account::where('name', 'like', '%Transport%')->pluck('id')->toArray();
            } elseif (str_contains($catLower, 'pulsa') || str_contains($catLower, 'paket data') || str_contains($catLower, 'kuota') || str_contains($catLower, 'internet')) {
                $targetAccountIds = Account::where('name', 'like', '%Pulsa%')->orWhere('name', 'like', '%Internet%')->pluck('id')->toArray();
            } elseif (str_contains($catLower, 'dapur') || str_contains($catLower, 'sembako') || str_contains($catLower, 'groceries') || str_contains($catLower, 'belanja')) {
                $targetAccountIds = Account::where('name', 'like', '%Belanja%')->orWhere('name', 'like', '%Dapur%')->pluck('id')->toArray();
            } elseif (str_contains($catLower, 'hiburan') || str_contains($catLower, 'bioskop') || str_contains($catLower, 'game')) {
                $targetAccountIds = Account::where('name', 'like', '%Hiburan%')->pluck('id')->toArray();
            }

            $normalizedCat = str_ireplace(' dan ', ' & ', $category);

            $query->where(function ($q) use ($category, $normalizedCat, $targetAccountIds) {
                if (! empty($targetAccountIds)) {
                    $q->whereHas('items', function ($iq) use ($targetAccountIds) {
                        $iq->whereIn('account_id', $targetAccountIds);
                    });
                } else {
                    $q->where('description', 'like', "%{$category}%")
                        ->orWhere('description', 'like', "%{$normalizedCat}%")
                        ->orWhereHas('items.account', function ($aq) use ($category, $normalizedCat) {
                            $aq->where('name', 'like', "%{$category}%")
                                ->orWhere('name', 'like', "%{$normalizedCat}%");
                        });
                }
            });
        }

        // Apply item-specific keyword filter
        if (! empty($keyword) && ! $isKeywordCategoryLike) {
            $normalizedKeyword = str_ireplace(' dan ', ' & ', $keyword);
            $query->where(function ($q) use ($keyword, $normalizedKeyword) {
                $q->where('description', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$normalizedKeyword}%")
                    ->orWhereHas('items.account', function ($aq) use ($keyword, $normalizedKeyword) {
                        $aq->where('name', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$normalizedKeyword}%");
                    });
            });
        }

        // Filter by transaction type
        $typeFilter = strtolower(trim($filters['transaction_type'] ?? 'all'));
        if ($typeFilter === 'expense') {
            $query->whereHas('items.account', function ($q) {
                $q->whereIn('type', [AccountType::Expense, AccountType::Liability]);
            });
        } elseif ($typeFilter === 'income') {
            $query->whereHas('items.account', function ($q) {
                $q->where('type', AccountType::Revenue);
            });
        } elseif ($typeFilter === 'transfer') {
            $query->whereDoesntHave('items.account', function ($q) {
                $q->whereNotIn('category', [AccountCategory::CashAndBank]);
            });
        }

        $entries = $query->get();

        $processedTransactions = [];
        $totalExpense = 0.0;
        $totalIncome = 0.0;
        $totalAmount = 0.0;

        foreach ($entries as $entry) {
            $expenseOrRevenueItem = $entry->items->first(function ($i) {
                return in_array($i->account?->type, [AccountType::Expense, AccountType::Revenue, AccountType::Liability], true);
            });

            $cashItem = $entry->items->first(function ($i) {
                return $i->account?->category === AccountCategory::CashAndBank;
            });

            if ($expenseOrRevenueItem) {
                if ($expenseOrRevenueItem->account?->type === AccountType::Revenue) {
                    $type = 'income';
                    $amount = (float) $expenseOrRevenueItem->credit;
                    $categoryName = $expenseOrRevenueItem->account->name;
                    $wallet = $cashItem?->account?->name ?? 'Kas';
                    $totalIncome += $amount;
                    $totalAmount += $amount;
                } else {
                    $type = 'expense';
                    $amount = (float) $expenseOrRevenueItem->debit;
                    $categoryName = $expenseOrRevenueItem->account->name;
                    $wallet = $cashItem?->account?->name ?? 'Kas';
                    $totalExpense += $amount;
                    $totalAmount += $amount;
                }
            } else {
                // Transfer between Cash & Bank accounts
                $fromItem = $entry->items->first(fn ($i) => $i->credit > 0 && $i->account?->category === AccountCategory::CashAndBank);
                $toItem = $entry->items->first(fn ($i) => $i->debit > 0 && $i->account?->category === AccountCategory::CashAndBank);

                $type = 'transfer';
                $amount = (float) $entry->total_debit;
                $categoryName = 'Transfer Saldo';
                $fromName = $fromItem?->account?->name ?? 'Kas';
                $toName = $toItem?->account?->name ?? 'Kas';
                $wallet = "{$fromName} ➡️ {$toName}";
                $totalAmount += $amount;
            }

            $processedTransactions[] = [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'date' => $entry->date->translatedFormat('d M Y'),
                'raw_date' => $entry->date,
                'description' => $entry->description,
                'amount' => $amount,
                'type' => $type,
                'wallet_name' => $wallet,
                'category_name' => $categoryName,
            ];
        }

        return [
            'period_label' => $periodLabel,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'total_count' => count($processedTransactions),
            'total_amount' => $totalAmount,
            'total_expense' => $totalExpense,
            'total_income' => $totalIncome,
            'transactions' => $processedTransactions,
            'wallet_account' => $walletAccount,
            'wallet_balance' => $walletAccount ? (float) $walletAccount->balance : null,
        ];
    }
}
