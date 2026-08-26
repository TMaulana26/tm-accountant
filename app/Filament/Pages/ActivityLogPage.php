<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActivityLogStatsWidget;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.activity-log-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & Riwayat';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $title = 'Log Aktivitas Sistem (Audit Trail)';

    protected static ?int $navigationSort = 1;

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityLogStatsWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with(['causer'])->latest('id'))
            ->heading('Catatan Aktivitas Sistem')
            ->description('Audit trail riwayat aktivitas transaksi, akun dompet, dan autentikasi.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i:s')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'transaksi_jurnal' => '🧾 Transaksi',
                        'dompet_rekening' => '👛 Dompet',
                        'pengguna_autentikasi' => '👤 Pengguna',
                        'telegram_bot' => '🤖 Telegram',
                        default => $state ?: 'System',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'transaksi_jurnal' => 'success',
                        'dompet_rekening' => 'info',
                        'pengguna_autentikasi' => 'primary',
                        'telegram_bot' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'undo' => 'Undo',
                        'adjustment' => 'Adjusted',
                        default => $state ?: 'Info',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'undo', 'adjustment' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Deskripsi Aktivitas')
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('causer.name')
                    ->label('Pelaku')
                    ->default('🤖 Sistem / Bot')
                    ->icon(fn (Activity $record) => $record->causer ? 'heroicon-m-user' : null),
            ])
            ->actions([
                Action::make('view_details')
                    ->label('Lihat Data')
                    ->icon('heroicon-m-eye')
                    ->modalHeading('📦 Rincian Data Properti Log')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->form([
                        Textarea::make('payload')
                            ->label('JSON Payload / Perubahan Data')
                            ->rows(12)
                            ->disabled(),
                    ])
                    ->mountUsing(function ($form, Activity $record) {
                        $data = ($record->properties && $record->properties->isNotEmpty())
                            ? $record->properties->toArray()
                            : ($record->attribute_changes ? (is_array($record->attribute_changes) ? $record->attribute_changes : json_decode($record->attribute_changes, true)) : []);

                        $form->fill([
                            'payload' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                    }),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Kategori Log')
                    ->options([
                        'transaksi_jurnal' => '🧾 Transaksi Jurnal',
                        'dompet_rekening' => '👛 Dompet & Rekening',
                        'pengguna_autentikasi' => '👤 Pengguna & Auth',
                        'telegram_bot' => '🤖 Telegram Bot',
                    ]),

                SelectFilter::make('event')
                    ->label('Tipe Aksi')
                    ->options([
                        'created' => 'Created (Dibuat)',
                        'updated' => 'Updated (Diubah)',
                        'deleted' => 'Deleted (Dihapus)',
                        'undo' => 'Undo (Dibatalkan)',
                        'adjustment' => 'Adjusted (Penyesuaian)',
                    ]),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultPaginationPageOption(25);
    }
}
