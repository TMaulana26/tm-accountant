<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-gray-200 dark:border-gray-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">LAPORAN LABA RUGI</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Buku Keuangan Pribadi</p>
                <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-1">
                    Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Laporan Keuangan Mekari Style -->
            <div class="space-y-8 font-sans text-sm">
                <!-- 1. PENDAPATAN OPERASIONAL -->
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        Pendapatan (Revenues)
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            @forelse($report['operating_revenues'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/50 hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-2 text-gray-400 pl-4 italic text-xs">Tidak ada transaksi pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="font-bold border-t border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Total Pendapatan Utama</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['total_operating_revenue'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. HPP & LABA KOTOR -->
                @if($report['total_cogs'] > 0)
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        Harga Pokok Penjualan (COGS)
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            @foreach($report['cogs'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                    <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-bold border-t border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Total Harga Pokok Penjualan</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['total_cogs'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif

                <div class="flex justify-between items-center py-3 px-4 bg-primary-50 dark:bg-primary-950/40 rounded-lg font-bold text-primary-900 dark:text-primary-100">
                    <span>LABA KOTOR (GROSS PROFIT)</span>
                    <span class="font-mono text-base">Rp {{ number_format($report['gross_profit'], 2, ',', '.') }}</span>
                </div>

                <!-- 3. BEBAN OPERASIONAL -->
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        Beban Operasional & Kebutuhan Pokok (Operating Expenses)
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            @forelse($report['operating_expenses'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/50 hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-2 text-gray-400 pl-4 italic text-xs">Tidak ada transaksi beban operasional pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="font-bold border-t border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Total Beban Operasional</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['total_operating_expenses'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. PENDAPATAN & BEBAN LAINNYA -->
                @if($report['total_other_revenue'] > 0 || $report['total_other_expenses'] > 0)
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        Pendapatan & Beban Lain-lain
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            @foreach($report['other_revenues'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                    <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(+) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right text-success-600 font-mono">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @foreach($report['other_expenses'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                    <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(-) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right text-danger-600 font-mono">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- 5. LABA BERSIH (NET PROFIT) -->
                <div class="pt-4 border-t-2 border-gray-900 dark:border-white">
                    <div class="flex justify-between items-center py-4 px-6 {{ $report['net_profit'] >= 0 ? 'bg-success-50 dark:bg-success-950/40 text-success-950 dark:text-success-100' : 'bg-danger-50 dark:bg-danger-950/40 text-danger-950 dark:text-danger-100' }} rounded-xl">
                        <div>
                            <span class="text-lg font-extrabold uppercase">
                                {{ $report['net_profit'] >= 0 ? 'LABA BERSIH (NET PROFIT)' : 'RUGI BERSIH (NET LOSS)' }}
                            </span>
                            <p class="text-xs opacity-75 mt-0.5">Surplus/Defisit keuangan yang masuk ke Laba Ditahan</p>
                        </div>
                        <span class="text-2xl font-black font-mono">
                            Rp {{ number_format($report['net_profit'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
