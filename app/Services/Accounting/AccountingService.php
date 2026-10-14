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
use App\Models\JournalItem;
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
    public function createJournalEntry(array $entryData, array $items, ?User $creator = null): JournalEntry
    {
        if (count($items) < 2) {
            throw new InvalidArgumentException('Jurnal harus memiliki minimal dua baris item.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $debit = (float) ($item['debit'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Nilai Debit atau Kredit tidak boleh negatif.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('Satu baris jurnal tidak boleh memiliki nilai Debit dan Kredit sekaligus.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                sprintf('Jurnal tidak seimbang! Total Debit (Rp %s) harus sama dengan Total Kredit (Rp %s).', number_format($totalDebit, 2), number_format($totalCredit, 2))
            );
        }

        if ($totalDebit <= 0) {
            throw new InvalidArgumentException('Nilai transaksi jurnal harus lebih besar dari 0.');
        }

        $entry = DB::transaction(function () use ($entryData, $items, $creator) {
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
                'created_by' => $entryData['created_by'] ?? $creator?->id,
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

        if ($sourceAccountId === $destAccountId) {
            throw new InvalidArgumentException('Akun sumber dan akun tujuan tidak boleh sama.');
        }

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
            'created_by' => $creator?->id,
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
        return DB::transaction(function () use ($account, $realBalance, $reason, $date, $creator): ?JournalEntry {
            $lockedAccount = Account::whereKey($account->id)->lockForUpdate()->first() ?? $account;
            $currentBalance = $lockedAccount->balance;
            $diff = $realBalance - $currentBalance;

            if (abs($diff) < 0.01) {
                return null; // No adjustment needed
            }

            activity()
                ->causedBy($creator)
                ->performedOn($lockedAccount)
                ->useLog('dompet_rekening')
                ->event('adjustment')
                ->withProperties([
                    'account_name' => $lockedAccount->name,
                    'previous_balance' => $currentBalance,
                    'new_balance' => $realBalance,
                    'difference' => $diff,
                    'reason' => $reason,
                ])
                ->log("Penyesuaian saldo dompet {$lockedAccount->name} dari Rp ".number_format($currentBalance, 0, ',', '.').' menjadi Rp '.number_format($realBalance, 0, ',', '.'));

            $date = $date ?? now();

            if ($diff > 0) {
                // Surplus: Debit Wallet, Credit Other Revenue (Penyesuaian Saldo)
                $adjustmentAccount = Account::where('code', '4-20001')->first()
                    ?? Account::where('type', AccountType::Revenue)->whereNotNull('parent_id')->first()
                    ?? Account::where('type', AccountType::Revenue)->first()
                    ?? $this->findOrCreateIncomeAccount('Pendapatan Lainnya (Penyesuaian Saldo)');

                return $this->createJournalEntry([
                    'date' => $date,
                    'description' => "Penyesuaian Saldo (Surplus): {$lockedAccount->name} - {$reason}",
                    'source' => JournalSource::Web,
                    'created_by' => $creator?->id,
                ], [
                    [
                        'account_id' => $lockedAccount->id,
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
            }

            // Deficit: Debit Other Expense (Penyesuaian Saldo), Credit Wallet
            $absDiff = abs($diff);
            $adjustmentAccount = Account::where('code', '6-30001')->first()
                ?? Account::where('type', AccountType::Expense)->whereNotNull('parent_id')->first()
                ?? Account::where('type', AccountType::Expense)->first()
                ?? $this->findOrCreateExpenseAccount('Beban Lainnya (Penyesuaian Saldo)');

            return $this->createJournalEntry([
                'date' => $date,
                'description' => "Penyesuaian Saldo (Defisit): {$lockedAccount->name} - {$reason}",
                'source' => JournalSource::Web,
                'created_by' => $creator?->id,
            ], [
                [
                    'account_id' => $adjustmentAccount->id,
                    'debit' => $absDiff,
                    'credit' => 0,
                    'memo' => 'Selisih kurang kas & bank',
                ],
                [
                    'account_id' => $lockedAccount->id,
                    'debit' => 0,
                    'credit' => $absDiff,
                    'memo' => 'Pengurangan saldo penyesuaian',
                ],
            ], $creator);
        });
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
     * Find best matching Cash / Bank account by name, code, keyword, or sentence.
     */
    public function findPaymentAccount(?string $keyword = null, bool $fallbackToDefault = true): ?Account
    {
        if (empty($keyword)) {
            return $fallbackToDefault ? $this->getDefaultPaymentAccount() : null;
        }

        $keyword = trim($keyword);
        $cleanKeyword = preg_replace('/^(pakai|pake|via|dari|ke|dengan|menggunakan|melalui|lewat|bayar\s+pakai|bayar\s+pake)\s+/iu', '', $keyword);
        $cleanKeyword = trim($cleanKeyword);

        $wallets = Account::where('category', AccountCategory::CashAndBank)
            ->whereNotNull('parent_id')
            ->where('is_active', true)
            ->get();

        if ($wallets->isEmpty()) {
            return $fallbackToDefault ? $this->getDefaultPaymentAccount() : null;
        }

        // 1. Exact match on code or name (case-insensitive)
        foreach ($wallets as $wallet) {
            if (strcasecmp($wallet->code, $keyword) === 0 || strcasecmp($wallet->name, $keyword) === 0) {
                return $wallet;
            }
            if (! empty($cleanKeyword) && strcasecmp($wallet->name, $cleanKeyword) === 0) {
                return $wallet;
            }
        }

        // 2. Substring match on clean keyword
        if (! empty($cleanKeyword)) {
            foreach ($wallets as $wallet) {
                if (stripos($wallet->name, $cleanKeyword) !== false || stripos($cleanKeyword, $wallet->name) !== false) {
                    return $wallet;
                }
            }
        }

        // 3. Known Indonesian financial aliases map
        $aliases = [
            'gopay' => ['gopay', 'go-pay', 'go pay'],
            'shopeepay' => ['shopeepay', 'shopee pay', 'shopee'],
            'dana' => ['dana'],
            'ovo' => ['ovo'],
            'jago' => ['jago', 'bank jago'],
            'bca' => ['bca', 'bank bca', 'klikbca', 'blu', 'bca digital'],
            'mandiri' => ['mandiri', 'bank mandiri', 'livin'],
            'bri' => ['bri', 'bank bri', 'brimo'],
            'bni' => ['bni', 'bank bni', 'wondr'],
            'bsi' => ['bsi', 'bank bsi'],
            'seabank' => ['seabank', 'sea bank'],
            'jenius' => ['jenius', 'btpn'],
            'tunai' => ['tunai', 'cash', 'uang tunai', 'kas tunai', 'dompet fisik', 'kontan'],
        ];

        $lowerInput = strtolower($keyword);

        foreach ($wallets as $wallet) {
            $lowerWalletName = strtolower($wallet->name);

            foreach ($aliases as $key => $patterns) {
                $walletMatchesAlias = (stripos($lowerWalletName, $key) !== false);
                if (! $walletMatchesAlias) {
                    foreach ($patterns as $p) {
                        if (stripos($lowerWalletName, $p) !== false) {
                            $walletMatchesAlias = true;
                            break;
                        }
                    }
                }

                if ($walletMatchesAlias) {
                    foreach ($patterns as $p) {
                        if (preg_match('/\b'.preg_quote($p, '/').'\b/iu', $lowerInput)) {
                            return $wallet;
                        }
                    }
                }
            }
        }

        // 4. Token/distinctive word match: check if distinctive word from wallet name is in keyword
        foreach ($wallets as $wallet) {
            $stripped = preg_replace('/\b(e-wallet|bank|kas|dompet|digital|fisik)\b/iu', '', $wallet->name);
            $words = preg_split('/[\s\/\(\)\-]+/', $stripped, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($words as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 3 && preg_match('/\b'.preg_quote($w, '/').'\b/iu', $lowerInput)) {
                    return $wallet;
                }
            }
        }

        return $fallbackToDefault ? $this->getDefaultPaymentAccount() : null;
    }

    /**
     * Detect wallet account mentioned anywhere in a sentence or caption.
     */
    public function detectWalletFromText(?string $text): ?Account
    {
        if (empty($text)) {
            return null;
        }

        return $this->findPaymentAccount($text, fallbackToDefault: false);
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
     * Find or dynamically create a Revenue/Income account (supports AccountsReceivable for loan repayments).
     */
    public function findOrCreateIncomeAccount(string $name): Account
    {
        $name = trim($name);

        // 1. Exact or partial match on Revenue account
        $existing = Account::where('type', AccountType::Revenue)
            ->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                    ->orWhere('code', $name);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Check if intent implies loan repayment / accounts receivable (Piutang / Talangan / Pinjaman)
        $lower = strtolower($name);
        if (str_contains($lower, 'piutang') || str_contains($lower, 'pinjaman teman') || str_contains($lower, 'talangan') || str_contains($lower, 'kasbon')) {
            $receivableAcc = Account::where('type', AccountType::Asset)
                ->where('category', AccountCategory::AccountsReceivable)
                ->where(function ($q) use ($name) {
                    $q->where('name', 'like', "%{$name}%")
                        ->orWhere('code', $name);
                })
                ->first()
                ?? Account::where('type', AccountType::Asset)
                    ->where('category', AccountCategory::AccountsReceivable)
                    ->first();

            if ($receivableAcc) {
                return $receivableAcc;
            }
        }

        // 3. Fallback: Create new Revenue account
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
                case 'all':
                    $startDate = null;
                    $endDate = null;
                    $periodLabel = 'Semua Riwayat (All Time)';
                    break;
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
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        // Filter by wallet (payment/deposit account)
        $walletName = trim($filters['wallet_name'] ?? '');
        $walletAccount = null;
        if (! empty($walletName)) {
            $walletAccount = $this->findPaymentAccount($walletName, fallbackToDefault: false);
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
                $q->whereIn('type', [AccountType::Expense, AccountType::Liability])
                    ->orWhereIn('category', [AccountCategory::AccountsReceivable, AccountCategory::OtherCurrentAsset, AccountCategory::FixedAsset]);
            });
        } elseif ($typeFilter === 'income') {
            $query->whereHas('items.account', function ($q) {
                $q->where('type', AccountType::Revenue)
                    ->orWhere('category', AccountCategory::AccountsReceivable);
            });
        } elseif ($typeFilter === 'transfer') {
            $query->whereDoesntHave('items.account', function ($q) {
                $q->whereNotIn('category', [AccountCategory::CashAndBank]);
            });
        }

        $scopedQuery = clone $query;
        if ($startDate && $endDate) {
            $scopedQuery->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $scopedQuery->where('date', '>=', $startDate);
        } elseif ($endDate) {
            $scopedQuery->where('date', '<=', $endDate);
        }

        $entries = $scopedQuery->get();

        // Auto-fallback: if 0 results in this_month without explicit start_date and user specified keyword/category
        if ($entries->isEmpty() && empty($filters['start_date']) && $period === 'this_month' && (! empty($keyword) || ! empty($category))) {
            $fallbackEntries = $query->get();
            if ($fallbackEntries->isNotEmpty()) {
                $entries = $fallbackEntries;
                $periodLabel = 'Semua Periode (Pencarian Otomatis)';
            }
        }

        $processedTransactions = [];
        $totalExpense = 0.0;
        $totalIncome = 0.0;
        $totalAmount = 0.0;

        foreach ($entries as $entry) {
            $nonCashItem = $entry->items->first(function ($i) {
                return in_array($i->account?->type, [AccountType::Expense, AccountType::Revenue, AccountType::Liability], true)
                    || in_array($i->account?->category, [AccountCategory::AccountsReceivable, AccountCategory::OtherCurrentAsset, AccountCategory::FixedAsset], true);
            });

            $cashItem = $entry->items->first(function ($i) {
                return $i->account?->category === AccountCategory::CashAndBank;
            });

            if ($nonCashItem) {
                // If Revenue, or credit on Receivable/Asset/Liability/Expense, it represents inflow/income
                $isIncome = ($nonCashItem->account?->type === AccountType::Revenue) || ($nonCashItem->credit > 0);

                if ($isIncome) {
                    $type = 'income';
                    $amount = (float) ($nonCashItem->credit > 0 ? $nonCashItem->credit : $nonCashItem->debit);
                    $categoryName = $nonCashItem->account->name;
                    $wallet = $cashItem?->account?->name ?? 'Kas';
                    $totalIncome += $amount;
                    $totalAmount += $amount;
                } else {
                    $type = 'expense';
                    $amount = (float) ($nonCashItem->debit > 0 ? $nonCashItem->debit : $nonCashItem->credit);
                    $categoryName = $nonCashItem->account->name;
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
            'start_date' => $startDate?->toDateString() ?? '',
            'end_date' => $endDate?->toDateString() ?? '',
            'total_count' => count($processedTransactions),
            'total_amount' => $totalAmount,
            'total_expense' => $totalExpense,
            'total_income' => $totalIncome,
            'transactions' => $processedTransactions,
            'wallet_account' => $walletAccount,
            'wallet_balance' => $walletAccount ? (float) $walletAccount->balance : null,
        ];
    }

    /**
     * Extract a normalized contact/person name from a debt or receivable transaction description.
     */
    public function extractContactNameFromDescription(string $description): string
    {
        $clean = trim($description);

        // If there is an explicit name in parentheses like "Teman Pinjam Uang (Rizki Juli Aditia)", prefer it if the outer text is generic
        if (preg_match('/^(?:teman\s+pinjam\s+uang|saudara\s+meminjam\s+uang|pinjaman\s+teman)\s*\(([^)]+)\)/iu', $clean, $m)) {
            return trim($m[1]);
        }

        // Remove common prefix phrases
        $prefixes = [
            '/^(?:pelunasan|pembayaran|bayar|lunasin|lunasi|cicil|cicilan)\s+(?:piutang|hutang|utang|pinjaman)\s+(?:ibu\s+)?(?:ke|dari|oleh)?\s*/iu',
            '/^(?:meminjamkan|pinjamkan|pinjam|pinjaman)\s+uang\s+(?:teman\s+)?(?:ke|dari|untuk)\s+/iu',
            '/^(?:meminjamkan|pinjamkan|pinjaman)\s+(?:ke|dari|untuk)?\s*/iu',
            '/^(?:terima\s+)?(?:bayaran|cicilan|pelunasan)\s+(?:dari|ke)\s+/iu',
            '/^(?:piutang|hutang|utang|kasbon|talangan)\s+(?:ke|dari)?\s*/iu',
            '/^(?:a|kang|teh|mas|mbak|pak|bu|bapak|ibu|om|tante)\s+/iu',
        ];

        foreach ($prefixes as $pattern) {
            $clean = preg_replace($pattern, '', $clean);
        }

        // Run salutation prefix strip once more after stripping transaction prefixes
        $clean = preg_replace('/^(?:a|kang|teh|mas|mbak|pak|bu|bapak|ibu|om|tante)\s+/iu', '', $clean);

        // Strip trailing purpose or payment method clauses ("untuk Modal...", "via Bank...", "pakai...", "di...")
        $clean = preg_replace('/\s+(?:untuk|buat|via|pakai|pake|dari|ke\s+bank|ke\s+dana|ke\s+gopay|ke\s+bca)\s+.*$/iu', '', $clean);
        $clean = preg_replace('/\s*\([^)]*\)\s*$/u', '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B-—:.");

        return $clean !== '' ? $clean : 'Tanpa Nama';
    }

    /**
     * Aggregate open and settled Receivable (Piutang) and Payable (Hutang) balances per person.
     */
    public function getDebtReceivableSummary(?string $personKeyword = null, string $type = 'all'): array
    {
        $items = JournalItem::query()
            ->with(['journalEntry', 'account'])
            ->whereHas('account', function ($q) {
                $q->where('category', AccountCategory::AccountsReceivable)
                    ->orWhere('type', AccountType::Liability);
            })
            ->get()
            ->sortBy(fn (JournalItem $i) => $i->journalEntry?->date?->timestamp ?? 0);

        $receivableGroups = [];
        $payableGroups = [];

        foreach ($items as $item) {
            $entry = $item->journalEntry;
            $account = $item->account;
            if (! $entry || ! $account) {
                continue;
            }

            $rawPerson = $this->extractContactNameFromDescription($entry->description);
            $isReceivable = $account->category === AccountCategory::AccountsReceivable;
            $targetGroups = &$receivableGroups;
            if (! $isReceivable) {
                $targetGroups = &$payableGroups;
            }

            // Match existing canonical key if one is a token prefix of another (e.g. "Bifadli" <-> "Bifadli Muhamad Hasan")
            $matchedKey = null;
            $lowerRaw = mb_strtolower($rawPerson);
            foreach (array_keys($targetGroups) as $existingKey) {
                $lowerExisting = mb_strtolower($existingKey);
                if ($lowerExisting === $lowerRaw
                    || str_starts_with($lowerExisting, $lowerRaw.' ')
                    || str_starts_with($lowerRaw, $lowerExisting.' ')) {
                    $matchedKey = $existingKey;
                    break;
                }
            }

            $key = $matchedKey ?? $rawPerson;
            if (! isset($targetGroups[$key])) {
                $targetGroups[$key] = [
                    'name' => $key,
                    'person' => $key,
                    'given' => 0.0,
                    'total_given' => 0.0,
                    'repaid' => 0.0,
                    'total_repaid' => 0.0,
                    'borrowed' => 0.0,
                    'total_borrowed' => 0.0,
                    'paid' => 0.0,
                    'remaining' => 0.0,
                    'last_date' => $entry->date->translatedFormat('d M Y'),
                    'items' => [],
                ];
            } elseif (mb_strlen($rawPerson) > mb_strlen($targetGroups[$key]['person']) && $rawPerson !== 'Tanpa Nama') {
                $targetGroups[$key]['person'] = $rawPerson;
                $targetGroups[$key]['name'] = $rawPerson;
            }

            $debit = (float) $item->debit;
            $credit = (float) $item->credit;
            $targetGroups[$key]['last_date'] = $entry->date->translatedFormat('d M Y');

            if ($isReceivable) {
                $targetGroups[$key]['given'] += $debit;
                $targetGroups[$key]['total_given'] = $targetGroups[$key]['given'];
                $targetGroups[$key]['repaid'] += $credit;
                $targetGroups[$key]['total_repaid'] = $targetGroups[$key]['repaid'];
                $targetGroups[$key]['remaining'] = round($targetGroups[$key]['given'] - $targetGroups[$key]['repaid'], 2);
            } else {
                // For Liability (Hutang), credit is borrowing, debit is paying back
                $targetGroups[$key]['borrowed'] += $credit;
                $targetGroups[$key]['total_borrowed'] = $targetGroups[$key]['borrowed'];
                $targetGroups[$key]['paid'] += $debit;
                $targetGroups[$key]['total_repaid'] = $targetGroups[$key]['paid'];
                $targetGroups[$key]['remaining'] = round($targetGroups[$key]['borrowed'] - $targetGroups[$key]['paid'], 2);
            }

            $targetGroups[$key]['items'][] = [
                'date' => $entry->date->translatedFormat('d M Y'),
                'entry_number' => $entry->entry_number,
                'description' => $entry->description,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        $filterFn = function (array $group) use ($personKeyword): bool {
            if ($group['remaining'] <= 0) {
                return false;
            }

            if (! empty($personKeyword)) {
                $kw = mb_strtolower(trim($personKeyword));
                $kw = preg_replace('/^(?:a|kang|teh|mas|mbak|pak|bu|om|tante)\s+/iu', '', $kw);
                $personLower = mb_strtolower($group['person']);
                $inItems = collect($group['items'])->contains(fn ($i) => str_contains(mb_strtolower($i['description']), $kw));

                return str_contains($personLower, $kw) || $inItems;
            }

            return true;
        };

        $receivables = ($type === 'debt') ? [] : array_values(array_filter($receivableGroups, $filterFn));
        $payables = ($type === 'receivable') ? [] : array_values(array_filter($payableGroups, $filterFn));

        $totalRec = (float) collect($receivables)->sum(fn ($g) => max(0, $g['remaining']));
        $totalDebt = (float) collect($payables)->sum(fn ($g) => max(0, $g['remaining']));

        return [
            'receivables' => $receivables,
            'debts' => $payables,
            'payables' => $payables,
            'total_receivable' => $totalRec,
            'total_receivable_remaining' => $totalRec,
            'total_debt' => $totalDebt,
            'total_payable_remaining' => $totalDebt,
        ];
    }

    /**
     * Modify an existing simple JournalEntry in-place (wallet, category, amount, or description).
     */
    public function modifyJournalEntryQuick(
        JournalEntry $journal,
        ?Account $newWallet = null,
        ?Account $newCategory = null,
        ?float $newAmount = null,
        ?string $newDescription = null,
    ): JournalEntry {
        $journal->loadMissing('items.account');

        $cashItems = $journal->items->filter(fn ($i) => $i->account?->category === AccountCategory::CashAndBank)->values();
        $nonCashItem = $journal->items->first(fn ($i) => $i->account?->category !== AccountCategory::CashAndBank);

        $currentAmount = (float) $journal->total_debit;
        $amount = ($newAmount !== null && $newAmount > 0) ? $newAmount : $currentAmount;
        $description = ! empty($newDescription) ? $newDescription : $journal->description;

        $itemsPayload = [];

        if ($nonCashItem) {
            $cashItem = $cashItems->first();
            $walletAccount = $newWallet ?? $cashItem?->account ?? $this->getDefaultPaymentAccount();
            $categoryAccount = $newCategory ?? $nonCashItem->account;

            $isCashCredit = ($cashItem && (float) $cashItem->credit > 0);

            if ($isCashCredit) {
                // Outflow / Expense / Lending / Debt payment: Debit Category, Credit Wallet
                $itemsPayload = [
                    [
                        'account_id' => $categoryAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => $description,
                    ],
                    [
                        'account_id' => $walletAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => "Pembayaran dari {$walletAccount->name}",
                    ],
                ];
            } else {
                // Inflow / Income / Receivable repayment: Debit Wallet, Credit Category
                $itemsPayload = [
                    [
                        'account_id' => $walletAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'memo' => "Penerimaan ke {$walletAccount->name}",
                    ],
                    [
                        'account_id' => $categoryAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'memo' => $description,
                    ],
                ];
            }
        } elseif ($newCategory !== null && $cashItems->count() === 2) {
            // Converting a mistakenly recorded Transfer into an Expense (e.g. #502 "TF ke om" -> "Donasi ke om")
            $fromItem = $cashItems->first(fn ($i) => (float) $i->credit > 0) ?? $cashItems->first();
            $walletAccount = $newWallet ?? $fromItem->account;

            $itemsPayload = [
                [
                    'account_id' => $newCategory->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => $description,
                ],
                [
                    'account_id' => $walletAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => "Pembayaran dari {$walletAccount->name}",
                ],
            ];
        } else {
            // Transfer between 2 wallets
            $fromItem = $cashItems->first(fn ($i) => (float) $i->credit > 0) ?? $cashItems->first();
            $toItem = $cashItems->first(fn ($i) => (float) $i->debit > 0) ?? $cashItems->last();
            $fromAccount = $newWallet ?? $fromItem?->account ?? $this->getDefaultPaymentAccount();
            $toAccount = $toItem?->account ?? $this->getDefaultPaymentAccount();

            $itemsPayload = [
                [
                    'account_id' => $toAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => "Transfer masuk dari {$fromAccount->name}",
                ],
                [
                    'account_id' => $fromAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => "Transfer keluar ke {$toAccount->name}",
                ],
            ];
        }

        return $this->updateJournalEntry($journal, [
            'date' => $journal->date,
            'description' => $description,
            'reference_number' => $journal->reference_number,
            'source' => $journal->source,
            'receipt_image' => $journal->receipt_image,
        ], $itemsPayload);
    }

    /**
     * Update an existing journal entry and replace its line items atomically.
     *
     * @param  array<string, mixed>  $entryData
     * @param  array<int, array{account_id: int, debit?: float, credit?: float, memo?: ?string}>  $items
     */
    public function updateJournalEntry(JournalEntry $journal, array $entryData, array $items): JournalEntry
    {
        if (count($items) < 2) {
            throw new InvalidArgumentException('Jurnal harus memiliki minimal dua baris item.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($items as $item) {
            $debit = (float) ($item['debit'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.01 || $totalDebit <= 0) {
            throw new InvalidArgumentException('Total Debit dan Kredit harus seimbang dan lebih dari 0.');
        }

        $previousAccountIds = $journal->items()->pluck('account_id')->all();

        $updated = DB::transaction(function () use ($journal, $entryData, $items) {
            $journal->update([
                'date' => $entryData['date'] ?? $journal->date,
                'description' => $entryData['description'] ?? $journal->description,
                'reference_number' => $entryData['reference_number'] ?? $journal->reference_number,
                'source' => $entryData['source'] ?? $journal->source,
                'receipt_image' => $entryData['receipt_image'] ?? $journal->receipt_image,
            ]);

            $journal->items()->delete();

            foreach ($items as $item) {
                $journal->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => (float) ($item['debit'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $journal->load('items.account');
        });

        $currentAccountIds = collect($items)->pluck('account_id')->all();
        $affectedIds = array_unique(array_merge($previousAccountIds, $currentAccountIds));
        $affectedWallets = Account::whereIn('id', $affectedIds)
            ->where('category', AccountCategory::CashAndBank)
            ->get();

        foreach ($affectedWallets as $wallet) {
            event(new WalletBalanceUpdated($wallet));
        }

        return $updated;
    }
}
