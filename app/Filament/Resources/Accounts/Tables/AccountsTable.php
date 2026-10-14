<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Models\Account;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Akun')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipe Akun')
                    ->badge()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->sortable(),

                TextColumn::make('parent.name')
                    ->label('Induk')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('balance')
                    ->label('Saldo Berjalan')
                    ->state(fn (Account $record): string => 'Rp '.number_format($record->balance, 0, ',', '.'))
                    ->badge()
                    ->color(fn (Account $record): string => $record->balance >= 0 ? 'success' : 'danger')
                    ->alignEnd(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('code')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipe Akun')
                    ->options(AccountType::class),

                SelectFilter::make('category')
                    ->label('Kategori Akun')
                    ->options(AccountCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Account $record): bool => ! $record->canBeDeleted()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            $records->each(function (Account $record): void {
                                if ($record->canBeDeleted()) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }
}
