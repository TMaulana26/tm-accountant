<?php

namespace App\Events;

use App\Models\JournalEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionReverted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $journalEntryId;

    public string $entryNumber;

    public string $description;

    public function __construct(JournalEntry $journal)
    {
        $this->journalEntryId = $journal->id;
        $this->entryNumber = $journal->entry_number;
        $this->description = $journal->description;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('accounting'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransactionReverted';
    }
}
