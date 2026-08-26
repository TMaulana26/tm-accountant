<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class UserGuide extends Page
{
    protected string $view = 'filament.pages.user-guide';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Panduan & Bantuan';

    protected static ?string $navigationLabel = 'Panduan Penggunaan';

    protected static ?string $title = 'Panduan Penggunaan TM Accountant & Bot Telegram';

    protected static ?int $navigationSort = 99;
}
