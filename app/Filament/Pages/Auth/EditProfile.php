<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

class EditProfile extends BaseEditProfile
{
    public static function isSimple(): bool
    {
        return false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Profil Pengguna & Keamanan';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Pengaturan Akun & Keamanan';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola profil, kata sandi, autentikasi biometrik (passkey), dan integrasi bot Telegram Anda.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.auth.profile-header'),

                Section::make('Informasi Akun & Kontak')
                    ->description('Data profil utama pengguna sistem pembukuan')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label('Alamat Email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                        ]),
                    ]),

                Section::make('Ubah Kata Sandi (Password)')
                    ->description('Pastikan menggunakan kata sandi yang kuat untuk mengamankan data keuangan Anda')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),
                    ]),

                View::make('filament.pages.auth.profile-biometrics'),
                View::make('filament.pages.auth.profile-telegram'),
            ]);
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return TextInput::make('password')
            ->label('Kata Sandi Baru')
            ->password()
            ->revealable()
            ->rule(Password::default())
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => bcrypt($state))
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): TextInput
    {
        return TextInput::make('passwordConfirmation')
            ->label('Konfirmasi Kata Sandi Baru')
            ->password()
            ->revealable()
            ->required(fn (string $operation, $get) => filled($get('password')))
            ->dehydrated(false);
    }
}
