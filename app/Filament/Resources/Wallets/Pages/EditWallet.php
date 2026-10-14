<?php

namespace App\Filament\Resources\Wallets\Pages;

use App\Filament\Resources\Wallets\WalletResource;
use App\Models\Account;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWallet extends EditRecord
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Account $record): bool => ! $record->canBeDeleted()),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_default) {
            $this->record->markAsDefault();
        }
    }
}
