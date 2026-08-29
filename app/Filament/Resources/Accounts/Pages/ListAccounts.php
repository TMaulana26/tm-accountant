<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    #[On('refresh-wallets')]
    #[On('refresh-transactions')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    #[On('echo:accounting,WalletBalanceUpdated')]
    public function refreshAccountsTable(): void
    {
        // Re-renders accounts table automatically
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
