<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JournalSource: string implements HasColor, HasLabel
{
    case Web = 'web';
    case Telegram = 'telegram';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Web Admin',
            self::Telegram => 'Telegram Bot',
            self::System => 'Sistem / Otomatis',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Web => 'info',
            self::Telegram => 'primary',
            self::System => 'gray',
        };
    }
}
