import './echo';
import './passkeys';

function registerEchoListeners() {
    if (!window.Echo) {
        console.warn('[Reverb/Echo] window.Echo is not ready.');
        return;
    }

    if (window.__accountingEchoRegistered) {
        return;
    }
    window.__accountingEchoRegistered = true;

    console.log('[Reverb/Echo] Subscribed to channel: accounting');

    const channel = window.Echo.channel('accounting');

    // 1. Transaction Recorded (New transaction logged via web or telegram)
    channel.listen('.TransactionRecorded', (e) => {
        console.log('[Reverb/Echo] Event TransactionRecorded received:', e);

        if (e.source === 'telegram' && window.FilamentNotification) {
            new FilamentNotification()
                .title('🤖 Transaksi Baru dari Telegram')
                .body(`${e.description} (Rp ${new Intl.NumberFormat('id-ID').format(e.amount)})`)
                .icon('heroicon-o-check-circle')
                .iconColor('success')
                .send();
        }

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-transactions');
            window.Livewire.dispatch('refresh-wallets');
            window.Livewire.dispatch('refresh-reports');
            window.Livewire.dispatch('echo:accounting,TransactionRecorded', e);
        }
    });

    // 2. Transaction Reverted (Undo transaction)
    channel.listen('.TransactionReverted', (e) => {
        console.log('[Reverb/Echo] Event TransactionReverted received:', e);

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

    // 3. Wallet Balance Updated (Direct pin/unpin or balance update)
    channel.listen('.WalletBalanceUpdated', (e) => {
        console.log('[Reverb/Echo] Event WalletBalanceUpdated received:', e);

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-wallets');
            window.Livewire.dispatch('echo:accounting,WalletBalanceUpdated', e);
        }
    });

    // 4. Telegram Message Logged (New chat activity)
    channel.listen('.TelegramMessageLogged', (e) => {
        console.log('[Reverb/Echo] Event TelegramMessageLogged received:', e);

        if (window.Livewire) {
            window.Livewire.dispatch('refresh-telegram-messages');
            window.Livewire.dispatch('echo:accounting,TelegramMessageLogged', e);
        }
    });
}

// Register on all page lifecycles
registerEchoListeners();
document.addEventListener('DOMContentLoaded', registerEchoListeners);
document.addEventListener('livewire:init', registerEchoListeners);
document.addEventListener('livewire:navigated', registerEchoListeners);
