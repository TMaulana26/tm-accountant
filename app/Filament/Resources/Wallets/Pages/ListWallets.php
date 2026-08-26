<?php

namespace App\Filament\Resources\Wallets\Pages;

use App\Filament\Resources\Wallets\WalletResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWallets extends ListRecords
{
    protected static string $resource = WalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetWizard')
                ->label('Buka Setup Wizard')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->action(function () {
                    auth()->user()->update(['wallet_setup_completed_at' => null]);
                    Notification::make()
                        ->title('Setup Wizard Diaktifkan')
                        ->body('Banner Onboarding Wizard kini tampil kembali di Dashboard utama.')
                        ->success()
                        ->send();

                    $this->redirect('/admin');
                }),

            CreateAction::make()
                ->label('Tambah Dompet / Rekening'),
        ];
    }
}
