<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 md:p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">LAPORAN NERACA</h2>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">Buku Keuangan Pribadi</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                    Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Neraca Berpasangan (Aktiva vs Passiva) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 font-sans text-sm">
                <!-- SISI KIRI: ASET (AKTIVA) -->
                <div class="space-y-6">
                    <div class="border-b-2 border-emerald-500 pb-2">
                        <h3 class="font-extrabold text-base text-slate-900 dark:text-white uppercase tracking-wider">
                            Aset (Aktiva)
                        </h3>
                    </div>

                    @foreach($report['asset_groups'] as $group)
                        @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                        <div>
                            <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs uppercase border-b border-slate-200 dark:border-slate-800 pb-1">
                                {{ $group['name'] }}
                            </h4>
                            <table class="w-full mt-1">
                                <tbody>
                                    @foreach($group['accounts'] as $acc)
                                        <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                            <td class="py-1.5 text-slate-600 dark:text-slate-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                            <td class="py-1.5 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-semibold text-xs border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                        <td class="py-1 pl-3 text-slate-700 dark:text-slate-300">Subtotal {{ $group['name'] }}</td>
                                        <td class="py-1 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endforeach

                    <!-- TOTAL ASET -->
                    <div class="pt-4 border-t-2 border-slate-900 dark:border-slate-600">
                        <div class="flex justify-between items-center py-3 px-4 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 rounded-xl font-bold text-emerald-950 dark:text-emerald-100 shadow-xs">
                            <span class="text-base font-extrabold uppercase">TOTAL ASET</span>
                            <span class="text-xl font-black font-mono">Rp {{ number_format($report['total_assets'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- SISI KANAN: KEWAJIBAN & EKUITAS (PASSIVA) -->
                <div class="space-y-6">
                    <!-- 1. KEWAJIBAN -->
                    <div>
                        <div class="border-b-2 border-amber-500 pb-2">
                            <h3 class="font-extrabold text-base text-slate-900 dark:text-white uppercase tracking-wider">
                                Kewajiban / Hutang (Liabilities)
                            </h3>
                        </div>

                        <div class="space-y-4 mt-4">
                            @php $hasLiab = false; @endphp
                            @foreach($report['liability_groups'] as $group)
                                @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                                @php $hasLiab = true; @endphp
                                <div>
                                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs uppercase border-b border-slate-200 dark:border-slate-800 pb-1">
                                        {{ $group['name'] }}
                                    </h4>
                                    <table class="w-full mt-1">
                                        <tbody>
                                            @foreach($group['accounts'] as $acc)
                                                <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                                    <td class="py-1.5 text-slate-600 dark:text-slate-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                                    <td class="py-1.5 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="font-semibold text-xs border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                                <td class="py-1 pl-3 text-slate-700 dark:text-slate-300">Subtotal {{ $group['name'] }}</td>
                                                <td class="py-1 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            @endforeach

                            @if(!$hasLiab)
                                <p class="text-xs text-slate-400 italic pl-3">Tidak ada kewajiban / hutang aktif.</p>
                            @endif

                            <div class="flex justify-between items-center py-2 px-3 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-lg font-bold text-xs text-slate-800 dark:text-slate-200">
                                <span>Total Kewajiban</span>
                                <span class="font-mono text-sm text-slate-900 dark:text-white">Rp {{ number_format($report['total_liabilities'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. EKUITAS -->
                    <div>
                        <div class="border-b-2 border-blue-500 pb-2">
                            <h3 class="font-extrabold text-base text-slate-900 dark:text-white uppercase tracking-wider">
                                Ekuitas & Modal (Equity)
                            </h3>
                        </div>

                        <table class="w-full mt-4">
                            <tbody>
                                @foreach($report['equity_accounts'] as $acc)
                                    <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                        <td class="py-1.5 text-slate-600 dark:text-slate-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                        <td class="py-1.5 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                    <td class="py-1.5 text-slate-600 dark:text-slate-300 pl-3">Laba Ditahan (Akumulasi Periode Lalu)</td>
                                    <td class="py-1.5 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($report['retained_earnings'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="border-b border-slate-100 dark:border-slate-800/60 bg-emerald-50/60 dark:bg-emerald-950/40">
                                    <td class="py-1.5 text-emerald-900 dark:text-emerald-200 font-semibold pl-3">Laba Periode Berjalan (Surplus/Defisit)</td>
                                    <td class="py-1.5 text-right text-emerald-900 dark:text-emerald-200 font-bold font-mono">Rp {{ number_format($report['current_period_net_profit'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="font-bold border-t border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-xs text-slate-900 dark:text-white">
                                    <td class="py-2 pl-3">Total Ekuitas</td>
                                    <td class="py-2 text-right font-mono text-sm">Rp {{ number_format($report['total_equity'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- TOTAL KEWAJIBAN & EKUITAS -->
                    <div class="pt-4 border-t-2 border-slate-900 dark:border-slate-600">
                        <div class="flex justify-between items-center py-3 px-4 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 rounded-xl font-bold text-emerald-950 dark:text-emerald-100 shadow-xs">
                            <span class="text-base font-extrabold uppercase">TOTAL KEWAJIBAN & EKUITAS</span>
                            <span class="text-xl font-black font-mono">Rp {{ number_format($report['total_liabilities_and_equity'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Status Verification Badge -->
            <div class="mt-8 pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        <span>✓</span> Neraca Seimbang (Aset = Kewajiban + Ekuitas)
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                        <span>⚠</span> Neraca Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_assets'] - $report['total_liabilities_and_equity']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
