<?php

namespace App\Events;

use App\Models\TelegramMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramMessageLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;

    public ?string $fromUsername;

    public string $rawText;

    public ?string $intent;

    public ?string $aiResponse;

    public ?string $receiptImage;

    public string $status;

    public string $createdAt;

    public function __construct(TelegramMessage $message)
    {
        $this->messageId = $message->id;
        $this->fromUsername = $message->from_username;
        $this->rawText = $message->raw_text;
        $this->intent = $message->intent;
        $this->aiResponse = $message->ai_response;
        $this->receiptImage = $message->receipt_image;
        $this->status = $message->status?->value ?? 'processed';
        $this->createdAt = $message->created_at?->format('H:i') ?? now()->format('H:i');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('accounting'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TelegramMessageLogged';
    }
}
