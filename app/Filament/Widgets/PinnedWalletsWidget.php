<?php

namespace App\Filament\Widgets;

use App\Events\WalletBalanceUpdated;
use App\Models\Account;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class PinnedWalletsWidget extends Widget
{
    protected string $view = 'filament.widgets.pinned-wallets-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    #[On('echo:accounting,WalletBalanceUpdated')]
    #[On('echo:accounting,TransactionRecorded')]
    #[On('echo:accounting,TransactionReverted')]
    public function refreshWallets(): void
    {
        // Re-renders pinned wallets widget automatically
    }

    public function unpinWallet(int $accountId): void
    {
        $account = Account::find($accountId);
        if ($account) {
            $account->update(['is_pinned' => false]);
            event(new WalletBalanceUpdated($account));
            Notification::make()
                ->title('Pin Dompet Dilepas')
                ->body("{$account->name} tidak lagi disematkan di Dashboard.")
                ->info()
                ->send();
        }
    }

    public function getPinnedWallets()
    {
        return Account::wallets()
            ->where('is_pinned', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}
