<?php

namespace App\Filament\Resources\Wallets\Pages;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Filament\Resources\Wallets\WalletResource;
use App\Models\Account;
use App\Services\Accounting\AccountingService;
use Filament\Resources\Pages\CreateRecord;

class CreateWallet extends CreateRecord
{
    protected static string $resource = WalletResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parent = Account::where('code', '1-10000')->first();

        $data['type'] = AccountType::Asset;
        $data['category'] = AccountCategory::CashAndBank;
        $data['parent_id'] = $parent?->id;
        $data['is_system'] = false;

        return $data;
    }

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        $initialBalance = (float) ($data['initial_balance'] ?? 0);

        if ($initialBalance > 0) {
            app(AccountingService::class)->setInitialBalance(
                account: $this->record,
                amount: $initialBalance,
                date: now(),
                creator: auth()->user()
            );
        }

        if (! empty($data['is_default'])) {
            $this->record->markAsDefault();
        }
    }
}
