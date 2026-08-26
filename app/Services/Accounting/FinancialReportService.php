<?php

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\JournalItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialReportService
{
    /**
     * Generate Mekari-style Income Statement (Laporan Laba Rugi).
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     operating_revenues: Collection,
     *     total_operating_revenue: float,
     *     cogs: Collection,
     *     total_cogs: float,
     *     gross_profit: float,
     *     operating_expenses: Collection,
     *     total_operating_expenses: float,
     *     operating_profit: float,
     *     other_revenues: Collection,
     *     total_other_revenue: float,
     *     other_expenses: Collection,
     *     total_other_expenses: float,
     *     net_profit: float
     * }
     */
    public function getIncomeStatement(CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $start = is_string($startDate) ? Carbon::parse($startDate)->startOfDay() : $startDate->copy()->startOfDay();
        $end = is_string($endDate) ? Carbon::parse($endDate)->endOfDay() : $endDate->copy()->endOfDay();

        // 1. Revenues
        $operatingRevenues = $this->getAccountsWithMovement(AccountType::Revenue, AccountCategory::OperatingRevenue, $start, $end);
        $totalOperatingRevenue = (float) $operatingRevenues->sum('period_balance');

        // 2. Cost of Goods Sold (COGS)
        $cogs = $this->getAccountsWithMovement(AccountType::Expense, AccountCategory::CostOfGoodsSold, $start, $end);
        $totalCogs = (float) $cogs->sum('period_balance');

        // Gross Profit
        $grossProfit = $totalOperatingRevenue - $totalCogs;

        // 3. Operating Expenses
        $operatingExpenses = $this->getAccountsWithMovement(AccountType::Expense, AccountCategory::OperatingExpense, $start, $end);
        $totalOperatingExpenses = (float) $operatingExpenses->sum('period_balance');

        // Operating Profit
        $operatingProfit = $grossProfit - $totalOperatingExpenses;

        // 4. Other Revenues & Expenses
        $otherRevenues = $this->getAccountsWithMovement(AccountType::Revenue, AccountCategory::OtherRevenue, $start, $end);
        $totalOtherRevenue = (float) $otherRevenues->sum('period_balance');

        $otherExpenses = $this->getAccountsWithMovement(AccountType::Expense, AccountCategory::OtherExpense, $start, $end);
        $totalOtherExpenses = (float) $otherExpenses->sum('period_balance');

        // Net Profit (Laba Bersih)
        $netProfit = $operatingProfit + $totalOtherRevenue - $totalOtherExpenses;

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'operating_revenues' => $operatingRevenues,
            'total_operating_revenue' => $totalOperatingRevenue,
            'cogs' => $cogs,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'total_operating_expenses' => $totalOperatingExpenses,
            'operating_profit' => $operatingProfit,
            'other_revenues' => $otherRevenues,
            'total_other_revenue' => $totalOtherRevenue,
            'other_expenses' => $otherExpenses,
            'total_other_expenses' => $totalOtherExpenses,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Generate Mekari-style Balance Sheet (Laporan Neraca).
     *
     * @return array{
     *     as_of_date: string,
     *     asset_groups: array,
     *     total_assets: float,
     *     liability_groups: array,
     *     total_liabilities: float,
     *     equity_accounts: Collection,
     *     retained_earnings: float,
     *     current_period_net_profit: float,
     *     total_equity: float,
     *     total_liabilities_and_equity: float,
     *     is_balanced: bool
     * }
     */
    public function getBalanceSheet(CarbonInterface|string|null $asOfDate = null): array
    {
        $asOf = $asOfDate ? (is_string($asOfDate) ? Carbon::parse($asOfDate)->endOfDay() : $asOfDate->copy()->endOfDay()) : now()->endOfDay();

        // 1. Assets
        $cashAndBank = $this->getAccountsWithBalanceAsOf(AccountType::Asset, AccountCategory::CashAndBank, $asOf);
        $receivables = $this->getAccountsWithBalanceAsOf(AccountType::Asset, AccountCategory::AccountsReceivable, $asOf);
        $otherCurrentAssets = $this->getAccountsWithBalanceAsOf(AccountType::Asset, AccountCategory::OtherCurrentAsset, $asOf);
        $fixedAssets = $this->getAccountsWithBalanceAsOf(AccountType::Asset, AccountCategory::FixedAsset, $asOf);

        $cashSum = (float) $cashAndBank->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $recvSum = (float) $receivables->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $otherSum = (float) $otherCurrentAssets->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $fixedSum = (float) $fixedAssets->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));

        $totalAssets = $cashSum + $recvSum + $otherSum + $fixedSum;

        $assetGroups = [
            ['name' => 'Kas & Bank', 'accounts' => $cashAndBank, 'subtotal' => $cashSum],
            ['name' => 'Piutang', 'accounts' => $receivables, 'subtotal' => $recvSum],
            ['name' => 'Aset Lancar Lainnya', 'accounts' => $otherCurrentAssets, 'subtotal' => $otherSum],
            ['name' => 'Aset Tetap', 'accounts' => $fixedAssets, 'subtotal' => $fixedSum],
        ];

        // 2. Liabilities
        $accountsPayable = $this->getAccountsWithBalanceAsOf(AccountType::Liability, AccountCategory::AccountsPayable, $asOf);
        $creditCards = $this->getAccountsWithBalanceAsOf(AccountType::Liability, AccountCategory::CreditCard, $asOf);
        $otherCurrentLiabilities = $this->getAccountsWithBalanceAsOf(AccountType::Liability, AccountCategory::OtherCurrentLiability, $asOf);
        $longTermLiabilities = $this->getAccountsWithBalanceAsOf(AccountType::Liability, AccountCategory::LongTermLiability, $asOf);

        $apSum = (float) $accountsPayable->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $ccSum = (float) $creditCards->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $otherLiabSum = (float) $otherCurrentLiabilities->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $longTermSum = (float) $longTermLiabilities->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));

        $totalLiabilities = $apSum + $ccSum + $otherLiabSum + $longTermSum;

        $liabilityGroups = [
            ['name' => 'Hutang Usaha / Pribadi', 'accounts' => $accountsPayable, 'subtotal' => $apSum],
            ['name' => 'Kartu Kredit / Paylater', 'accounts' => $creditCards, 'subtotal' => $ccSum],
            ['name' => 'Kewajiban Lancar Lainnya', 'accounts' => $otherCurrentLiabilities, 'subtotal' => $otherLiabSum],
            ['name' => 'Kewajiban Jangka Panjang', 'accounts' => $longTermLiabilities, 'subtotal' => $longTermSum],
        ];

        // 3. Equity
        $equityAccounts = $this->getAccountsWithBalanceAsOf(AccountType::Equity, AccountCategory::Equity, $asOf);
        $retainedEarningsAccounts = $this->getAccountsWithBalanceAsOf(AccountType::Equity, AccountCategory::RetainedEarnings, $asOf);

        $equitySum = (float) $equityAccounts->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));
        $retainedSum = (float) $retainedEarningsAccounts->sum(fn ($a) => (float) ($a->as_of_balance ?? $a->balance));

        $rawEquityTotal = $equitySum + $retainedSum;

        // Current period profit/loss (all revenues minus all expenses up to asOf)
        $allRevenues = (float) Account::where('type', AccountType::Revenue->value)->whereNotNull('parent_id')->get()->sum(fn ($acc) => $acc->getBalanceAtDate($asOf));
        $allExpenses = (float) Account::where('type', AccountType::Expense->value)->whereNotNull('parent_id')->get()->sum(fn ($acc) => $acc->getBalanceAtDate($asOf));
        $currentPeriodNetProfit = $allRevenues - $allExpenses;

        $totalEquity = $rawEquityTotal + $currentPeriodNetProfit;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        $isBalanced = round($totalAssets, 2) === round($totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOf->toDateString(),
            'asset_groups' => $assetGroups,
            'total_assets' => $totalAssets,
            'liability_groups' => $liabilityGroups,
            'total_liabilities' => $totalLiabilities,
            'equity_accounts' => $equityAccounts,
            'retained_earnings' => $retainedSum,
            'current_period_net_profit' => $currentPeriodNetProfit,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
        ];
    }

    /**
     * Alias for getCashFlowStatement.
     */
    public function getCashFlow(CarbonInterface|string|null $startDate = null, CarbonInterface|string|null $endDate = null): array
    {
        return $this->getCashFlowStatement($startDate, $endDate);
    }

    /**
     * Generate Direct Method Cash Flow Statement (Laporan Arus Kas).
     *
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     operating_inflows: float,
     *     operating_outflows: float,
     *     net_operating_cashflow: float,
     *     investing_inflows: float,
     *     investing_outflows: float,
     *     net_investing_cashflow: float,
     *     financing_inflows: float,
     *     financing_outflows: float,
     *     net_financing_cashflow: float,
     *     net_change_in_cash: float,
     *     beginning_cash: float,
     *     ending_cash: float
     * }
     */
    public function getCashFlowStatement(CarbonInterface|string|null $startDate = null, CarbonInterface|string|null $endDate = null): array
    {
        $start = $startDate ? (is_string($startDate) ? Carbon::parse($startDate)->startOfDay() : $startDate->copy()->startOfDay()) : now()->startOfMonth();
        $end = $endDate ? (is_string($endDate) ? Carbon::parse($endDate)->endOfDay() : $endDate->copy()->endOfDay()) : now()->endOfMonth();

        $cashAccounts = Account::where('category', AccountCategory::CashAndBank->value)
            ->whereNotNull('parent_id')
            ->pluck('id')
            ->toArray();

        // Beginning & Ending Cash
        $beginningCash = (float) Account::whereIn('id', $cashAccounts)->get()->sum(fn ($acc) => $acc->getBalanceAtDate($start->copy()->subDay()->endOfDay()));
        $endingCash = (float) Account::whereIn('id', $cashAccounts)->get()->sum(fn ($acc) => $acc->getBalanceAtDate($end));

        // Cash Inflows & Outflows by corresponding line items
        // Operating: Inflows from Revenues, Outflows to Operating Expenses & Payables
        $operatingInflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->where('type', AccountType::Revenue->value);
            })
            ->sum('debit');

        $operatingOutflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('credit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->where('type', AccountType::Expense->value);
            })
            ->sum('credit');

        $netOperatingCashflow = $operatingInflows - $operatingOutflows;

        // Investing: Fixed Assets
        $investingInflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->where('category', AccountCategory::FixedAsset->value);
            })
            ->sum('debit');

        $investingOutflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('credit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->where('category', AccountCategory::FixedAsset->value);
            })
            ->sum('credit');

        $netInvestingCashflow = $investingInflows - $investingOutflows;

        // Financing: Capital injections, Loans, Drawings
        $financingInflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('debit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->whereIn('type', [AccountType::Equity->value, AccountType::Liability->value]);
            })
            ->sum('debit');

        $financingOutflows = (float) JournalItem::whereIn('account_id', $cashAccounts)
            ->where('credit', '>', 0)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereHas('journalEntry.items.account', function (Builder $q) {
                $q->whereIn('type', [AccountType::Equity->value, AccountType::Liability->value]);
            })
            ->sum('credit');

        $netFinancingCashflow = $financingInflows - $financingOutflows;
        $netChangeInCash = $netOperatingCashflow + $netInvestingCashflow + $netFinancingCashflow;

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'operating_inflows' => $operatingInflows,
            'operating_outflows' => $operatingOutflows,
            'net_operating_cashflow' => $netOperatingCashflow,
            'investing_inflows' => $investingInflows,
            'investing_outflows' => $investingOutflows,
            'net_investing_cashflow' => $netInvestingCashflow,
            'financing_inflows' => $financingInflows,
            'financing_outflows' => $financingOutflows,
            'net_financing_cashflow' => $netFinancingCashflow,
            'net_change_in_cash' => $netChangeInCash,
            'beginning_cash' => $beginningCash,
            'ending_cash' => $endingCash,
        ];
    }

    /**
     * Generate General Ledger (Buku Besar) for a specific account.
     */
    public function getGeneralLedger(int|Account $account, CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $acc = $account instanceof Account ? $account : Account::findOrFail($account);
        $start = is_string($startDate) ? Carbon::parse($startDate)->startOfDay() : $startDate->copy()->startOfDay();
        $end = is_string($endDate) ? Carbon::parse($endDate)->endOfDay() : $endDate->copy()->endOfDay();

        $beginningBalance = $acc->getBalanceAtDate($start->copy()->subDay()->endOfDay());

        $items = JournalItem::where('account_id', $acc->id)
            ->whereHas('journalEntry', function (Builder $q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            })
            ->with(['journalEntry'])
            ->get()
            ->sortBy(fn ($item) => $item->journalEntry->date->format('Y-m-d').'_'.$item->journalEntry->id);

        $runningBalance = $beginningBalance;
        $transactions = [];

        foreach ($items as $item) {
            $debit = (float) $item->debit;
            $credit = (float) $item->credit;

            if ($acc->type->isDebitNormal()) {
                $runningBalance += ($debit - $credit);
            } else {
                $runningBalance += ($credit - $debit);
            }

            $transactions[] = [
                'date' => $item->journalEntry->date->format('d/m/Y'),
                'entry_number' => $item->journalEntry->entry_number,
                'description' => $item->memo ?: $item->journalEntry->description,
                'source' => $item->journalEntry->source,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'account' => $acc,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'beginning_balance' => $beginningBalance,
            'transactions' => $transactions,
            'ending_balance' => $runningBalance,
        ];
    }

    /**
     * Generate Trial Balance (Neraca Saldo).
     */
    public function getTrialBalance(CarbonInterface|string|null $asOfDate = null): array
    {
        $asOf = $asOfDate ? (is_string($asOfDate) ? Carbon::parse($asOfDate)->endOfDay() : $asOfDate->copy()->endOfDay()) : now()->endOfDay();

        $accounts = Account::where('is_active', true)
            ->whereNotNull('parent_id') // Leaf accounts
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $balance = $account->getBalanceAtDate($asOf);

            if (abs($balance) < 0.001) {
                continue; // Skip zero-balance accounts
            }

            $debit = 0.0;
            $credit = 0.0;

            if ($account->type->isDebitNormal()) {
                if ($balance >= 0) {
                    $debit = $balance;
                } else {
                    $credit = abs($balance);
                }
            } else {
                if ($balance >= 0) {
                    $credit = $balance;
                } else {
                    $debit = abs($balance);
                }
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->getLabel(),
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return [
            'as_of_date' => $asOf->toDateString(),
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }

    /**
     * Helper: Get accounts with movement during date period.
     */
    protected function getAccountsWithMovement(AccountType $type, AccountCategory $category, CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        return Account::where('type', $type->value)
            ->where('category', $category->value)
            ->whereNotNull('parent_id')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($startDate, $endDate) {
                $movement = $account->getMovementBetween($startDate, $endDate);
                $account->period_balance = $movement;

                return $account;
            })
            ->filter(fn ($acc) => abs($acc->period_balance) > 0.001)
            ->values();
    }

    /**
     * Helper: Get accounts with balance as of date.
     */
    protected function getAccountsWithBalanceAsOf(AccountType $type, AccountCategory $category, CarbonInterface $asOfDate): Collection
    {
        return Account::where('type', $type->value)
            ->where('category', $category->value)
            ->whereNotNull('parent_id')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) use ($asOfDate) {
                $bal = $account->getBalanceAtDate($asOfDate);
                $account->as_of_balance = $bal;
                $account->balance = $bal;

                return $account;
            })
            ->filter(fn ($acc) => abs($acc->as_of_balance) > 0.001)
            ->values();
    }
}
