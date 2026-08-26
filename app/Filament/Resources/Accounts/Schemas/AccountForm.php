<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Akun')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Kode Akun')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. 1-10001'),

                            TextInput::make('name')
                                ->label('Nama Akun')
                                ->required()
                                ->placeholder('e.g. Bank BCA / Kas Tunai'),

                            Select::make('type')
                                ->label('Tipe Akun')
                                ->options(AccountType::class)
                                ->required()
                                ->live(),

                            Select::make('category')
                                ->label('Kategori Akun')
                                ->options(AccountCategory::class)
                                ->required(),

                            Select::make('parent_id')
                                ->label('Akun Induk (Header / Parent)')
                                ->relationship('parent', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->columnSpanFull(),

                            Textarea::make('description')
                                ->label('Deskripsi / Keterangan')
                                ->rows(3)
                                ->nullable()
                                ->columnSpanFull(),

                            Toggle::make('is_active')
                                ->label('Status Aktif')
                                ->default(true),
                        ]),
                    ]),
            ]);
    }
}
