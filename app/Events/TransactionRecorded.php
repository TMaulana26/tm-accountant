<?php

namespace App\Events;

use App\Models\JournalEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $journalEntryId;

    public string $entryNumber;

    public string $description;

    public float $amount;

    public string $source;

    public string $date;

    public function __construct(JournalEntry $journal)
    {
        $this->journalEntryId = $journal->id;
        $this->entryNumber = $journal->entry_number;
        $this->description = $journal->description;
        $this->amount = (float) $journal->total_debit;
        $this->source = $journal->source?->value ?? 'web';
        $this->date = $journal->date->format('Y-m-d');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('accounting'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransactionRecorded';
    }
}
