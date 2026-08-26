<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return 'Masuk ke TM Accountant';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Selamat Datang Kembali 👋';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Sistem Pembukuan Keuangan Pribadi Double-Entry';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.auth.user-badge'),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getDefaultAdminEmail(): string
    {
        return User::first()?->email ?? 'admin@example.com';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->default(fn () => $this->getDefaultAdminEmail())
            ->hidden()
            ->dehydrated();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi (Password)')
            ->password()
            ->revealable()
            ->autocomplete('current-password')
            ->required()
            ->autofocus();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction()
                ->label('Masuk dengan Password'),
            Action::make('biometricLogin')
                ->label('Masuk dengan Biometrik (Passkey)')
                ->icon('heroicon-m-finger-print')
                ->color('gray')
                ->extraAttributes([
                    'id' => 'btn-biometric-login',
                    'onclick' => 'window.loginWithPasskey()',
                    'type' => 'button',
                ]),
        ];
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => $data['email'] ?? $this->getDefaultAdminEmail(),
            'password' => $data['password'],
        ];
    }
}
