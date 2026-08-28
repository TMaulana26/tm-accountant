<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <x-filament::section>
            <!-- Header Laporan -->
            <div class="mb-8 border-b border-gray-200 pb-6 text-center dark:border-white/10">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">LAPORAN LABA RUGI</h2>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Buku Keuangan Pribadi</p>
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <span>📅</span>
                    <span>Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}</span>
                </div>
            </div>

            <!-- Konten Laporan Keuangan -->
            <div class="space-y-8 font-sans text-sm">
                <!-- 1. PENDAPATAN OPERASIONAL -->
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        Pendapatan (Revenues)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @forelse($report['operating_revenues'] as $acc)
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/80 dark:border-white/5 dark:hover:bg-white/5">
                                    <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-3 pl-4 text-xs italic text-gray-400 dark:text-gray-500">Tidak ada transaksi pendapatan pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Total Pendapatan Utama</td>
                                <td class="py-2.5 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format($report['total_operating_revenue'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. HPP & LABA KOTOR -->
                @if($report['total_cogs'] > 0)
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        Harga Pokok Penjualan (COGS)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @foreach($report['cogs'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Total Harga Pokok Penjualan</td>
                                <td class="py-2.5 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format($report['total_cogs'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- LABA KOTOR BANNER -->
                <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/80 px-5 py-3.5 font-bold text-emerald-950 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                    <span class="text-xs font-extrabold uppercase tracking-wide md:text-sm">LABA KOTOR (GROSS PROFIT)</span>
                    <span class="font-mono text-base font-black text-emerald-800 dark:text-emerald-300">Rp {{ number_format($report['gross_profit'], 2, ',', '.') }}</span>
                </div>

                <!-- 3. BEBAN OPERASIONAL -->
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        Beban Operasional & Kebutuhan Pokok (Operating Expenses)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @forelse($report['operating_expenses'] as $acc)
                                <tr class="border-b border-gray-100 transition-colors hover:bg-gray-50/80 dark:border-white/5 dark:hover:bg-white/5">
                                    <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">[{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-semibold text-gray-950 dark:text-gray-100">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-3 pl-4 text-xs italic text-gray-400 dark:text-gray-500">Tidak ada transaksi beban operasional pada periode ini.</td>
                                </tr>
                            @endforelse
                            <tr class="border-t border-gray-200 bg-gray-50/80 font-bold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Total Beban Operasional</td>
                                <td class="py-2.5 text-right font-mono font-bold text-gray-950 dark:text-white">Rp {{ number_format($report['total_operating_expenses'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 4. PENDAPATAN & BEBAN LAINNYA -->
                @if($report['total_other_revenue'] > 0 || $report['total_other_expenses'] > 0)
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        Pendapatan & Beban Lain-lain
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            @foreach($report['other_revenues'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(+) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @foreach($report['other_expenses'] as $acc)
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(-) [{{ $acc->code }}] {{ $acc->name }}</td>
                                    <td class="py-2.5 text-right font-mono font-semibold text-rose-700 dark:text-rose-400">Rp {{ number_format($acc->period_balance, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                <!-- 5. LABA BERSIH (NET PROFIT) -->
                <div class="border-t-2 border-gray-900 pt-4 dark:border-gray-600">
                    <div class="flex items-center justify-between rounded-xl p-5 shadow-xs md:p-6 {{ $report['net_profit'] >= 0 ? 'border border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'border border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-200' }}">
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
        </x-filament::section>
    </div>
</x-filament-panels::page>
