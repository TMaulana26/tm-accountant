<?php

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Enums\AccountCategory;
use App\Enums\JournalSource;
use App\Events\TransactionRecorded;
use App\Events\WalletBalanceUpdated;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Models\Account;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntry extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = $data['source'] ?? JournalSource::Web;
        $data['created_by'] = $data['created_by'] ?? auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $entry = $this->record->load('items.account');

        event(new TransactionRecorded($entry));

        $accountIds = $entry->items->pluck('account_id')->unique();
        $affectedWallets = Account::whereIn('id', $accountIds)
            ->where('category', AccountCategory::CashAndBank)
            ->get();

        foreach ($affectedWallets as $wallet) {
            event(new WalletBalanceUpdated($wallet));
        }
    }
}
