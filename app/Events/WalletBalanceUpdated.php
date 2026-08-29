<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $accountId;

    public string $accountCode;

    public string $accountName;

    public float $balance;

    public bool $isDefault;

    public bool $isPinned;

    public function __construct(Account $account)
    {
        $this->accountId = $account->id;
        $this->accountCode = $account->code;
        $this->accountName = $account->name;
        $this->balance = (float) $account->balance;
        $this->isDefault = (bool) $account->is_default;
        $this->isPinned = (bool) $account->is_pinned;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('accounting'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WalletBalanceUpdated';
    }
}
