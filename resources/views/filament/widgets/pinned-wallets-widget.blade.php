<x-filament-widgets::widget>
    @php
        $wallets = $this->getPinnedWallets();
    @endphp

    @if($wallets->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">📌</span>
                    <h3 class="text-base font-bold tracking-tight text-gray-900 dark:text-white">
                        Dompet & Rekening Favorit Tersemat
                    </h3>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Live WebSockets
                    </span>
                </div>
                <a href="{{ route('filament.admin.resources.wallets.index') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline dark:text-emerald-400 dark:hover:text-emerald-300">
                    Kelola Semua Dompet →
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($wallets as $w)
                    <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200/90 bg-white p-4.5 shadow-sm transition-all duration-200 hover:border-emerald-500/50 hover:shadow-md dark:border-gray-800 dark:bg-gray-900/90 dark:hover:border-emerald-500/50">
                        <!-- Top Header Section -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <!-- Wallet Icon with subtle badge -->
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100/90 text-2xl shadow-xs ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
                                    @if(str_contains(strtolower($w->name), 'jago') || str_contains(strtolower($w->name), 'bca') || str_contains(strtolower($w->name), 'mandiri') || str_contains(strtolower($w->name), 'bni') || str_contains(strtolower($w->name), 'bri') || str_contains(strtolower($w->name), 'bsi') || str_contains(strtolower($w->name), 'cimb'))
                                        🏦
                                    @elseif(str_contains(strtolower($w->name), 'gopay') || str_contains(strtolower($w->name), 'ovo') || str_contains(strtolower($w->name), 'dana') || str_contains(strtolower($w->name), 'shopeepay'))
                                        📱
                                    @else
                                        💵
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-bold text-gray-900 dark:text-white" title="{{ $w->name }}">
                                        {{ $w->name }}
                                    </h4>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="truncate font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {{ $w->account_number ? $w->account_number : $w->code }}
                                        </span>
                                        @if($w->is_default)
                                            <span class="inline-flex items-center gap-0.5 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-inset ring-amber-500/30" title="Dompet Utama Default">
                                                ⭐ Utama
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Unpin Button with tooltip -->
                            <button
                                type="button"
                                wire:click="unpinWallet({{ $w->id }})"
                                class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-gray-500 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                                title="Lepas Pin dari Dashboard"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Bottom Balance Section -->
                        <div class="mt-4 flex items-baseline justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Saldo Saat Ini
                            </span>
                            <span class="font-mono text-base font-extrabold tracking-tight {{ $w->balance >= 0 ? 'text-gray-950 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                Rp {{ number_format($w->balance, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-gray-800/30">
            <div class="flex items-center gap-3 text-center sm:text-left">
                <span class="text-2xl">📌</span>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">Belum Ada Dompet yang Disematkan</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Sematkan rekening bank atau e-wallet favorit Anda agar saldo riilnya selalu tampil di bagian atas dashboard.</p>
                </div>
            </div>
            <a href="{{ route('filament.admin.resources.wallets.index') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-gray-800 shadow-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                <span>➕</span> Pin Dompet Favorit
            </a>
        </div>
    @endif
</x-filament-widgets::widget>
