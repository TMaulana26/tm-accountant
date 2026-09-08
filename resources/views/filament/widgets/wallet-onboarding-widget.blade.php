<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-violet-700 via-purple-900 to-slate-950 p-6 sm:p-8 text-white shadow-xl border border-violet-500/20">
        <!-- Background Pattern Decor -->
        <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none">
            <x-filament::icon icon="heroicon-o-wallet" class="h-64 w-64 text-violet-300" />
        </div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 ring-1 ring-inset ring-emerald-400/30 backdrop-blur-md">
                    <span>✨</span> Setup Awal Keuangan Anda
                </div>
                <h2 class="text-xl sm:text-2xl font-black tracking-tight">
                    Mulai dengan Mengatur Dompet & Saldo Awal Anda! 👛
                </h2>
                <p class="text-xs sm:text-sm text-violet-100/90 leading-relaxed">
                    Pilih rekening bank dan e-wallet yang Anda gunakan, masukkan saldo riil saat ini, dan tentukan dompet default untuk transaksi Telegram. Sistem otomatis mencatatnya ke <b>Modal Awal</b> agar neraca langsung seimbang!
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                {{ $this->startWizardAction }}
                {{ $this->dismissAction }}
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
