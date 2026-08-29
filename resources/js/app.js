import './echo';
import './passkeys';

document.addEventListener('DOMContentLoaded', () => {
    if (window.Echo) {
        window.Echo.channel('accounting')
            .listen('.TransactionRecorded', (e) => {
                if (e.source === 'telegram' && window.FilamentNotification) {
                    new FilamentNotification()
                        .title('🤖 Transaksi Baru dari Telegram')
                        .body(`${e.description} (Rp ${new Intl.NumberFormat('id-ID').format(e.amount)})`)
                        .icon('heroicon-o-check-circle')
                        .iconColor('success')
                        .send();
                }
            })
            .listen('.TransactionReverted', (e) => {
                if (window.FilamentNotification) {
                    new FilamentNotification()
                        .title('↩️ Transaksi Dibatalkan (Undo)')
                        .body(`Jurnal ${e.entryNumber} (${e.description}) telah dibatalkan.`)
                        .icon('heroicon-o-arrow-uturn-left')
                        .iconColor('warning')
                        .send();
                }
            });
    }
});
