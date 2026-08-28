<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <x-filament::section>
            <!-- Header Laporan -->
            <div class="mb-8 border-b border-gray-200 pb-6 text-center dark:border-white/10">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">LAPORAN NERACA</h2>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Buku Keuangan Pribadi</p>
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <span>📅</span>
                    <span>Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}</span>
                </div>
            </div>

            <!-- Konten Neraca Berpasangan (Aktiva vs Passiva) -->
            <div class="grid grid-cols-1 gap-8 font-sans text-sm lg:grid-cols-2">
                <!-- SISI KIRI: ASET (AKTIVA) -->
                <div class="space-y-6">
                    <div class="border-b-2 border-emerald-500 pb-2">
                        <h3 class="text-base font-extrabold uppercase tracking-wider text-gray-950 dark:text-white">
                            Aset (Aktiva)
                        </h3>
                    </div>

                    @foreach($report['asset_groups'] as $group)
                        @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                        <div>
                            <h4 class="border-b border-gray-200 pb-1 text-xs font-bold uppercase text-gray-800 dark:border-white/10 dark:text-gray-200">
                                {{ $group['name'] }}
                            </h4>
                            <table class="mt-1 w-full">
                                <tbody>
                                    @foreach($group['accounts'] as $acc)
                                        <tr class="border-b border-gray-100 dark:border-white/5">
                                            <td class="py-2 pl-3 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                            <td class="py-2 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                        <td class="py-1.5 pl-3">Subtotal {{ $group['name'] }}</td>
                                        <td class="py-1.5 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endforeach

                    <!-- TOTAL ASET -->
                    <div class="border-t-2 border-gray-900 pt-4 dark:border-gray-600">
                        <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 font-bold text-emerald-950 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <span class="text-xs font-extrabold uppercase tracking-wide md:text-sm">TOTAL ASET</span>
                            <span class="font-mono text-lg font-black text-emerald-800 dark:text-emerald-300 md:text-xl">Rp {{ number_format($report['total_assets'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- SISI KANAN: KEWAJIBAN & EKUITAS (PASSIVA) -->
                <div class="space-y-6">
                    <!-- 1. KEWAJIBAN -->
                    <div>
                        <div class="border-b-2 border-amber-500 pb-2">
                            <h3 class="text-base font-extrabold uppercase tracking-wider text-gray-950 dark:text-white">
                                Kewajiban / Hutang (Liabilities)
                            </h3>
                        </div>

                        <div class="mt-4 space-y-4">
                            @php $hasLiab = false; @endphp
                            @foreach($report['liability_groups'] as $group)
                                @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                                @php $hasLiab = true; @endphp
                                <div>
                                    <h4 class="border-b border-gray-200 pb-1 text-xs font-bold uppercase text-gray-800 dark:border-white/10 dark:text-gray-200">
                                        {{ $group['name'] }}
                                    </h4>
                                    <table class="mt-1 w-full">
                                        <tbody>
                                            @foreach($group['accounts'] as $acc)
                                                <tr class="border-b border-gray-100 dark:border-white/5">
                                                    <td class="py-2 pl-3 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                                    <td class="py-2 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                                <td class="py-1.5 pl-3">Subtotal {{ $group['name'] }}</td>
                                                <td class="py-1.5 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            @endforeach

                            @if(!$hasLiab)
                                <p class="pl-3 text-xs italic text-gray-400 dark:text-gray-500">Tidak ada kewajiban / hutang aktif.</p>
                            @endif

                            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50/80 px-3.5 py-2 text-xs font-bold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                                <span>Total Kewajiban</span>
                                <span class="font-mono text-sm font-bold text-gray-950 dark:text-white">Rp {{ number_format($report['total_liabilities'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. EKUITAS -->
                    <div>
                        <div class="border-b-2 border-blue-500 pb-2">
                            <h3 class="text-base font-extrabold uppercase tracking-wider text-gray-950 dark:text-white">
                                Ekuitas & Modal (Equity)
                            </h3>
                        </div>

                        <table class="mt-4 w-full">
                            <tbody>
                                @foreach($report['equity_accounts'] as $acc)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="py-2 pl-3 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                        <td class="py-2 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2 pl-3 text-gray-700 dark:text-gray-300">Laba Ditahan (Akumulasi Periode Lalu)</td>
                                    <td class="py-2 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($report['retained_earnings'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="border-b border-gray-100 bg-emerald-50/60 dark:border-white/5 dark:bg-emerald-950/30">
                                    <td class="py-2 pl-3 font-semibold text-emerald-900 dark:text-emerald-300">Laba Periode Berjalan (Surplus/Defisit)</td>
                                    <td class="py-2 text-right font-mono font-bold text-emerald-800 dark:text-emerald-300">Rp {{ number_format($report['current_period_net_profit'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-bold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                    <td class="py-2 pl-3">Total Ekuitas</td>
                                    <td class="py-2 text-right font-mono text-sm font-bold">Rp {{ number_format($report['total_equity'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- TOTAL PASSIVA -->
                    <div class="border-t-2 border-gray-900 pt-4 dark:border-gray-600">
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/90 px-4 py-3 font-bold text-gray-950 shadow-xs dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <span class="text-xs font-extrabold uppercase tracking-wide md:text-sm">TOTAL KEWAJIBAN & EKUITAS</span>
                            <span class="font-mono text-lg font-black text-gray-950 dark:text-white md:text-xl">Rp {{ number_format($report['total_liabilities_and_equity'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Status Verification Badge -->
            <div class="mt-8 flex justify-end border-t border-gray-200 pt-4 dark:border-white/10">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <span>✓</span> Neraca Seimbang (Aktiva = Passiva)
                    </div>
                @else
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3.5 py-1.5 text-xs font-semibold text-rose-800 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                        <span>⚠</span> Neraca Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_assets'] - $report['total_liabilities_and_equity']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
