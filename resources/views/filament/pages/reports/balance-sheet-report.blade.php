<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-gray-200 dark:border-gray-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">LAPORAN NERACA</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Buku Keuangan Pribadi</p>
                <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-1">
                    Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Neraca Berpasangan (Aktiva vs Passiva) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 font-sans text-sm">
                <!-- SISI KIRI: ASET (AKTIVA) -->
                <div class="space-y-6">
                    <div class="border-b-2 border-primary-600 pb-2">
                        <h3 class="font-extrabold text-base text-gray-900 dark:text-white uppercase tracking-wider">
                            Aset (Aktiva)
                        </h3>
                    </div>

                    @foreach($report['asset_groups'] as $group)
                        @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                        <div>
                            <h4 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase border-b border-gray-200 dark:border-gray-800 pb-1">
                                {{ $group['name'] }}
                            </h4>
                            <table class="w-full mt-1">
                                <tbody>
                                    @foreach($group['accounts'] as $acc)
                                        <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                            <td class="py-1.5 text-gray-600 dark:text-gray-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                            <td class="py-1.5 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="font-semibold text-xs border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/30">
                                        <td class="py-1 pl-3 text-gray-700 dark:text-gray-300">Subtotal {{ $group['name'] }}</td>
                                        <td class="py-1 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @endif
                    @endforeach

                    <!-- TOTAL ASET -->
                    <div class="pt-4 border-t-2 border-gray-900 dark:border-white">
                        <div class="flex justify-between items-center py-3 px-4 bg-primary-50 dark:bg-primary-950/40 rounded-lg font-bold text-primary-950 dark:text-primary-100">
                            <span class="text-base font-extrabold uppercase">TOTAL ASET</span>
                            <span class="text-xl font-black font-mono">Rp {{ number_format($report['total_assets'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <!-- SISI KANAN: KEWAJIBAN & EKUITAS (PASSIVA) -->
                <div class="space-y-6">
                    <!-- 1. KEWAJIBAN -->
                    <div>
                        <div class="border-b-2 border-warning-600 pb-2">
                            <h3 class="font-extrabold text-base text-gray-900 dark:text-white uppercase tracking-wider">
                                Kewajiban / Hutang (Liabilities)
                            </h3>
                        </div>

                        <div class="space-y-4 mt-4">
                            @php $hasLiab = false; @endphp
                            @foreach($report['liability_groups'] as $group)
                                @if($group['accounts']->isNotEmpty() || $group['subtotal'] > 0)
                                @php $hasLiab = true; @endphp
                                <div>
                                    <h4 class="font-bold text-gray-700 dark:text-gray-200 text-xs uppercase border-b border-gray-200 dark:border-gray-800 pb-1">
                                        {{ $group['name'] }}
                                    </h4>
                                    <table class="w-full mt-1">
                                        <tbody>
                                            @foreach($group['accounts'] as $acc)
                                                <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                                    <td class="py-1.5 text-gray-600 dark:text-gray-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                                    <td class="py-1.5 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="font-semibold text-xs border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/30">
                                                <td class="py-1 pl-3 text-gray-700 dark:text-gray-300">Subtotal {{ $group['name'] }}</td>
                                                <td class="py-1 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($group['subtotal'], 2, ',', '.') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            @endforeach

                            @if(!$hasLiab)
                                <p class="text-xs text-gray-400 italic pl-3">Tidak ada kewajiban / hutang aktif.</p>
                            @endif

                            <div class="flex justify-between items-center py-2 px-3 bg-gray-50 dark:bg-gray-800/50 rounded font-bold text-xs">
                                <span>Total Kewajiban</span>
                                <span class="font-mono text-sm">Rp {{ number_format($report['total_liabilities'], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. EKUITAS -->
                    <div>
                        <div class="border-b-2 border-info-600 pb-2">
                            <h3 class="font-extrabold text-base text-gray-900 dark:text-white uppercase tracking-wider">
                                Ekuitas & Modal (Equity)
                            </h3>
                        </div>

                        <table class="w-full mt-4">
                            <tbody>
                                @foreach($report['equity_accounts'] as $acc)
                                    <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                        <td class="py-1.5 text-gray-600 dark:text-gray-300 pl-3">[{{ $acc->code }}] {{ $acc->name }}</td>
                                        <td class="py-1.5 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->balance, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                    <td class="py-1.5 text-gray-600 dark:text-gray-300 pl-3">Laba Ditahan (Akumulasi Periode Lalu)</td>
                                    <td class="py-1.5 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['retained_earnings'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="border-b border-gray-100 dark:border-gray-800/50 bg-primary-50/50 dark:bg-primary-950/20">
                                    <td class="py-1.5 text-primary-900 dark:text-primary-200 font-semibold pl-3">Laba Periode Berjalan (Surplus/Defisit)</td>
                                    <td class="py-1.5 text-right text-primary-900 dark:text-primary-200 font-bold font-mono">Rp {{ number_format($report['current_period_net_profit'], 2, ',', '.') }}</td>
                                </tr>
                                <tr class="font-bold border-t border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-xs">
                                    <td class="py-2 pl-3">Total Ekuitas</td>
                                    <td class="py-2 text-right font-mono text-sm">Rp {{ number_format($report['total_equity'], 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- TOTAL KEWAJIBAN & EKUITAS -->
                    <div class="pt-4 border-t-2 border-gray-900 dark:border-white">
                        <div class="flex justify-between items-center py-3 px-4 bg-primary-50 dark:bg-primary-950/40 rounded-lg font-bold text-primary-950 dark:text-primary-100">
                            <span class="text-base font-extrabold uppercase">TOTAL KEWAJIBAN & EKUITAS</span>
                            <span class="text-xl font-black font-mono">Rp {{ number_format($report['total_liabilities_and_equity'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance Status Verification Badge -->
            <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300">
                        <span>✓</span> Neraca Seimbang (Aset = Kewajiban + Ekuitas)
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-300">
                        <span>⚠</span> Neraca Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_assets'] - $report['total_liabilities_and_equity']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
