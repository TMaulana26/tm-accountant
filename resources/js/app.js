import './echo';
import './passkeys';

function setupEchoListeners() {
    if (!window.Echo) return;

    const channel = window.Echo.channel('accounting');

    channel.listen('.TransactionRecorded', (e) => {
        console.log('[Echo] TransactionRecorded received:', e);

        // Toast notification for telegram transactions
        if (e.source === 'telegram' && window.FilamentNotification) {
            new FilamentNotification()
                .title('🤖 Transaksi Baru dari Telegram')
                .body(`${e.description} (Rp ${new Intl.NumberFormat('id-ID').format(e.amount)})`)
                .icon('heroicon-o-check-circle')
                .iconColor('success')
                .send();
        }

        // Trigger Livewire refreshes across widgets & tables
        if (window.Livewire) {
            window.Livewire.dispatch('refresh-transactions');
            window.Livewire.dispatch('refresh-wallets');
            window.Livewire.dispatch('refresh-reports');
            window.Livewire.dispatch('echo:accounting,TransactionRecorded', e);
        }
    });

    channel.listen('.TransactionReverted', (e) => {
        console.log('[Echo] TransactionReverted received:', e);

        if (window.FilamentNotification) {
            new FilamentNotification()
                .title('↩️ Transaksi Dibatalkan (Undo)')
                .body(`Jurnal ${e.entryNumber} (${e.description}) telah dibatalkan.`)
                .icon('heroicon-o-arrow-uturn-left')
                .iconColor('warning')
                .send();
        }

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-transactions');
            window.Livewire.dispatch('refresh-wallets');
            window.Livewire.dispatch('refresh-reports');
            window.Livewire.dispatch('echo:accounting,TransactionReverted', e);
        }
    });

    channel.listen('.WalletBalanceUpdated', (e) => {
        console.log('[Echo] WalletBalanceUpdated received:', e);

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-wallets');
            window.Livewire.dispatch('echo:accounting,WalletBalanceUpdated', e);
        }
    });

    channel.listen('.TelegramMessageLogged', (e) => {
        console.log('[Echo] TelegramMessageLogged received:', e);

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-telegram-messages');
            window.Livewire.dispatch('echo:accounting,TelegramMessageLogged', e);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupEchoListeners);
} else {
    setupEchoListeners();
}
