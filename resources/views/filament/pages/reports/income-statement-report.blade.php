<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900 md:p-8">
            <!-- Header Laporan -->
            <div class="mb-6 border-b border-gray-200 pb-6 text-center dark:border-gray-800">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">LAPORAN LABA RUGI</h2>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Buku Keuangan Pribadi</p>
                <p class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Laporan Keuangan -->
            <div class="space-y-8 font-sans text-sm">
                <!-- 1. PENDAPATAN OPERASIONAL -->
                <div>
                    <h3 class="border-b border-gray-200 pb-2 text-xs font-bold uppercase tracking-wider text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        Pendapatan (Revenues)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @forelse($report['operating_revenues'] as $acc)
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/70 dark:border-gray-800/70 dark:hover:bg-gray-800/40">
                                    <td class="py-2 pl-4 text-gray-600 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right font-mono font-medium text-gray-900 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-3 pl-4 text-xs italic text-gray-400 dark:text-gray-500">Tidak ada transaksi pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-800/60 dark:text-white">
                                <td class="py-2.5 pl-4">Total Pendapatan Utama</td>
                                <td class="py-2.5 text-right font-mono">Rp {{ number_format($report['total_operating_revenue'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. HPP & LABA KOTOR -->
                @if($report['total_cogs'] > 0)
                <div>
                    <h3 class="border-b border-gray-200 pb-2 text-xs font-bold uppercase tracking-wider text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        Harga Pokok Penjualan (COGS)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @foreach($report['cogs'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/70">
                                    <td class="py-2 pl-4 text-gray-600 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right font-mono font-medium text-gray-900 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-800/60 dark:text-white">
                                <td class="py-2.5 pl-4">Total Harga Pokok Penjualan</td>
                                <td class="py-2.5 text-right font-mono">Rp {{ number_format($report['total_cogs'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif

                <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 font-bold text-emerald-900 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span class="text-xs font-extrabold uppercase tracking-wide md:text-sm">LABA KOTOR (GROSS PROFIT)</span>
                    <span class="font-mono text-base font-black">Rp {{ number_format($report['gross_profit'], 2, ',', '.') }}</span>
                </div>

                <!-- 3. BEBAN OPERASIONAL -->
                <div>
                    <h3 class="border-b border-gray-200 pb-2 text-xs font-bold uppercase tracking-wider text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        Beban Operasional & Kebutuhan Pokok (Operating Expenses)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @forelse($report['operating_expenses'] as $acc)
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/70 dark:border-gray-800/70 dark:hover:bg-gray-800/40">
                                    <td class="py-2 pl-4 text-gray-600 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right font-mono font-medium text-gray-900 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-3 pl-4 text-xs italic text-gray-400 dark:text-gray-500">Tidak ada transaksi beban operasional pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-800/60 dark:text-white">
                                <td class="py-2.5 pl-4">Total Beban Operasional</td>
                                <td class="py-2.5 text-right font-mono">Rp {{ number_format($report['total_operating_expenses'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. PENDAPATAN & BEBAN LAINNYA -->
                @if($report['total_other_revenue'] > 0 || $report['total_other_expenses'] > 0)
                <div>
                    <h3 class="border-b border-gray-200 pb-2 text-xs font-bold uppercase tracking-wider text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        Pendapatan & Beban Lain-lain
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @foreach($report['other_revenues'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/70">
                                    <td class="py-2 pl-4 text-gray-600 dark:text-gray-300">(+) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right font-mono font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @foreach($report['other_expenses'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-gray-800/70">
                                    <td class="py-2 pl-4 text-gray-600 dark:text-gray-300">(-) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2 text-right font-mono font-medium text-rose-600 dark:text-rose-400">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- 5. LABA BERSIH (NET PROFIT) -->
                <div class="border-t-2 border-gray-900 pt-4 dark:border-gray-600">
                    <div class="flex items-center justify-between rounded-xl p-4 shadow-xs md:p-6 {{ $report['net_profit'] >= 0 ? 'border border-emerald-200 bg-emerald-50/90 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'border border-rose-200 bg-rose-50/90 text-rose-950 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200' }}">
                        <div>
                            <span class="text-base font-extrabold uppercase tracking-wide md:text-lg">
                                {{ $report['net_profit'] >= 0 ? 'LABA BERSIH (NET PROFIT)' : 'RUGI BERSIH (NET LOSS)' }}
                            </span>
                            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">Surplus/Defisit keuangan yang masuk ke Laba Ditahan</p>
                        </div>
                        <span class="font-mono text-xl font-black md:text-2xl {{ $report['net_profit'] >= 0 ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">
                            Rp {{ number_format($report['net_profit'], 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
