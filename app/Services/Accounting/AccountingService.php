<?php

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\TelegramMessageStatus;
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

        return DB::transaction(function () use ($entryData, $items) {
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
        ?string $receiptImage = null
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

        return DB::transaction(function () use ($entry) {
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

        // 4. Fallback: Search Expense accounts
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
}
