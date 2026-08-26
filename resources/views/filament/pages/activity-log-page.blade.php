<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $stats = $this->getStatistics();
        @endphp

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 rounded-xl text-2xl shrink-0">
                        📜
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Aktivitas</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['total'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-success-50 dark:bg-success-950/60 text-success-600 dark:text-success-400 rounded-xl text-2xl shrink-0">
                        🧾
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaksi Jurnal</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['transaksi'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-info-50 dark:bg-info-950/60 text-info-600 dark:text-info-400 rounded-xl text-2xl shrink-0">
                        👛
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dompet & Rekening</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['dompet'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-warning-50 dark:bg-warning-950/60 text-warning-600 dark:text-warning-400 rounded-xl text-2xl shrink-0">
                        🤖
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telegram Bot</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['bot'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- Native Filament Table -->
        {{ $this->table }}
    </div>
</x-filament-panels::page>
