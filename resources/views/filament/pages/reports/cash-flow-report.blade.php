<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 md:p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">LAPORAN ARUS KAS</h2>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">Metode Langsung (Direct Cash Flow)</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                    Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Laporan Arus Kas -->
            <div class="space-y-6 font-sans text-sm">
                <!-- 1. ARUS KAS DARI AKTIVITAS OPERASIONAL -->
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white uppercase tracking-wider text-xs border-b border-slate-300 dark:border-slate-700 pb-2">
                        1. Arus Kas dari Aktivitas Operasional
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(+) Penerimaan Kas dari Pendapatan</td>
                                <td class="py-2 text-right text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ number_format($report['operating_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(-) Pembayaran Kas untuk Beban Operasional</td>
                                <td class="py-2 text-right text-rose-600 dark:text-rose-400 font-mono">Rp {{ number_format($report['operating_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                <td class="py-2 pl-4 text-slate-900 dark:text-white">Arus Kas Bersih dari Aktivitas Operasional</td>
                                <td class="py-2 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($report['net_operating_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. ARUS KAS DARI AKTIVITAS INVESTASI -->
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white uppercase tracking-wider text-xs border-b border-slate-300 dark:border-slate-700 pb-2">
                        2. Arus Kas dari Aktivitas Investasi
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(+) Penjualan Aset Tetap / Penarikan Investasi</td>
                                <td class="py-2 text-right text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ number_format($report['investing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(-) Pembelian Aset Tetap / Penempatan Investasi</td>
                                <td class="py-2 text-right text-rose-600 dark:text-rose-400 font-mono">Rp {{ number_format($report['investing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                <td class="py-2 pl-4 text-slate-900 dark:text-white">Arus Kas Bersih dari Aktivitas Investasi</td>
                                <td class="py-2 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($report['net_investing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. ARUS KAS DARI AKTIVITAS PENDANAAN -->
                <div>
                    <h3 class="font-bold text-slate-900 dark:text-white uppercase tracking-wider text-xs border-b border-slate-300 dark:border-slate-700 pb-2">
                        3. Arus Kas dari Aktivitas Pendanaan (Financing)
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(+) Tambahan Modal / Penerimaan Pinjaman</td>
                                <td class="py-2 text-right text-emerald-600 dark:text-emerald-400 font-mono">Rp {{ number_format($report['financing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-slate-100 dark:border-slate-800/60">
                                <td class="py-2 text-slate-600 dark:text-slate-300 pl-4">(-) Penarikan Modal (Prive) / Pembayaran Pinjaman</td>
                                <td class="py-2 text-right text-rose-600 dark:text-rose-400 font-mono">Rp {{ number_format($report['financing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                                <td class="py-2 pl-4 text-slate-900 dark:text-white">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                                <td class="py-2 text-right text-slate-900 dark:text-white font-mono">Rp {{ number_format($report['net_financing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- RINGKASAN SALDO KAS BERJALAN -->
                <div class="pt-4 border-t-2 border-slate-900 dark:border-slate-600 space-y-3">
                    <div class="flex justify-between items-center py-2 px-4 bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 rounded-lg font-semibold text-slate-800 dark:text-slate-200">
                        <span>Kenaikan / (Penurunan) Bersih Kas & Bank</span>
                        <span class="font-mono {{ $report['net_change_in_cash'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            Rp {{ number_format($report['net_change_in_cash'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-2 px-4 text-slate-600 dark:text-slate-300">
                        <span>Saldo Kas & Bank Awal Periode</span>
                        <span class="font-mono text-slate-900 dark:text-white">Rp {{ number_format($report['beginning_cash'], 2, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between items-center py-4 px-6 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 rounded-xl font-bold text-emerald-950 dark:text-emerald-100 shadow-xs">
                        <div>
                            <span class="text-base font-extrabold uppercase">SALDO KAS & BANK AKHIR PERIODE</span>
                            <p class="text-xs opacity-75 mt-0.5">Total likuiditas tersedia di seluruh akun Kas & Bank</p>
                        </div>
                        <span class="text-2xl font-black font-mono">Rp {{ number_format($report['ending_cash'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
