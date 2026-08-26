<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TelegramMessageStatus: string implements HasColor, HasLabel
{
    case Processed = 'processed';
    case Reverted = 'reverted';
    case Failed = 'failed';
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Processed => 'Berhasil Dicatat',
            self::Reverted => 'Dibatalkan (Reverted)',
            self::Failed => 'Gagal Diproses',
            self::Ignored => 'Diabaikan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Processed => 'success',
            self::Reverted => 'warning',
            self::Failed => 'danger',
            self::Ignored => 'gray',
        };
    }
}
