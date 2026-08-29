<x-filament-widgets::widget>
    @php
        $wallets = $this->getPinnedWallets();
    @endphp

    @if($wallets->isNotEmpty())
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">📌</span>
                    <h3 class="text-base font-bold tracking-tight text-gray-950 dark:text-white">
                        Dompet & Rekening Favorit Tersemat
                    </h3>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                        Live WebSockets
                    </span>
                </div>
                <a href="{{ route('filament.admin.resources.wallets.index') }}" class="text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:underline dark:text-emerald-400">
                    Kelola Semua Dompet →
                </a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($wallets as $w)
                    <div class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition-all hover:border-emerald-500/50 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-emerald-500/50">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-xl dark:border-white/10 dark:bg-gray-800">
                                    @if(str_contains(strtolower($w->name), 'jago') || str_contains(strtolower($w->name), 'bca') || str_contains(strtolower($w->name), 'mandiri') || str_contains(strtolower($w->name), 'bni') || str_contains(strtolower($w->name), 'bri') || str_contains(strtolower($w->name), 'bsi') || str_contains(strtolower($w->name), 'cimb'))
                                        🏦
                                    @elseif(str_contains(strtolower($w->name), 'gopay') || str_contains(strtolower($w->name), 'ovo') || str_contains(strtolower($w->name), 'dana') || str_contains(strtolower($w->name), 'shopeepay'))
                                        📱
                                    @else
                                        💵
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-bold text-gray-950 dark:text-white" title="{{ $w->name }}">
                                        {{ $w->name }}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $w->account_number ? $w->account_number : $w->code }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                @if($w->is_default)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300" title="Dompet Utama Default">
                                        ⭐ Utama
                                    </span>
                                @endif
                                <button
                                    type="button"
                                    wire:click="unpinWallet({{ $w->id }})"
                                    class="rounded-lg p-1 text-gray-400 opacity-60 transition hover:bg-gray-100 hover:text-gray-600 group-hover:opacity-100 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                    title="Lepas Pin dari Dashboard"
                                >
                                    <x-filament::icon icon="heroicon-m-bookmark-slash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-white/5 flex items-baseline justify-between">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo Saat Ini</span>
                            <span class="font-mono text-lg font-black tracking-tight {{ $w->balance >= 0 ? 'text-gray-950 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
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
