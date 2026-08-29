<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\AccountBalanceWidget;
use App\Filament\Widgets\IncomeExpenseChartWidget;
use App\Filament\Widgets\PinnedWalletsWidget;
use App\Filament\Widgets\RecentJournalEntriesWidget;
use App\Filament\Widgets\WalletBreakdownWidget;
use App\Filament\Widgets\WalletOnboardingWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Profil & Keamanan')
                    ->icon('heroicon-m-user-circle'),
                'passkey' => MenuItem::make()
                    ->label('Aktifkan Biometrik (Passkey)')
                    ->icon('heroicon-m-finger-print')
                    ->url(fn (): string => filament()->getProfileUrl()),
            ])
            ->brandName('TM Accountant')
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                'Transaksi Cepat',
                'Laporan Keuangan',
                'Buku Besar & Akun',
                'Audit & Riwayat',
                'Panduan & Bantuan',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                PinnedWalletsWidget::class,
                WalletOnboardingWidget::class,
                AccountBalanceWidget::class,
                IncomeExpenseChartWidget::class,
                WalletBreakdownWidget::class,
                RecentJournalEntriesWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js'])")
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
