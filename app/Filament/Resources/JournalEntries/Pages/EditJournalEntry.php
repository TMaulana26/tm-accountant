<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Enums\AccountCategory;
use App\Events\WalletBalanceUpdated;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJournalEntry extends EditRecord
{
    protected static string $resource = JournalEntryResource::class;

    /**
     * @var array<int, int>
     */
    protected array $previousAccountIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (JournalEntry $record): bool => app(AccountingService::class)->revertJournalEntry($record)),
        ];
    }

    protected function beforeSave(): void
    {
        $this->previousAccountIds = $this->record->items()->pluck('account_id')->all();
    }

    protected function afterSave(): void
    {
        $currentAccountIds = $this->record->items()->pluck('account_id')->all();
        $affectedAccountIds = array_unique(array_merge($this->previousAccountIds, $currentAccountIds));

        $affectedWallets = Account::whereIn('id', $affectedAccountIds)
            ->where('category', AccountCategory::CashAndBank)
            ->get();

        foreach ($affectedWallets as $wallet) {
            event(new WalletBalanceUpdated($wallet));
        }
    }
}
