<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <x-filament::section>
            <!-- Header Laporan -->
            <div class="mb-8 border-b border-gray-200 pb-6 text-center dark:border-white/10">
                <h2 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">LAPORAN ARUS KAS</h2>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Metode Langsung (Direct Cash Flow)</p>
                <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                    <span>📅</span>
                    <span>Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}</span>
                </div>
            </div>

            <!-- Konten Laporan Arus Kas -->
            <div class="space-y-6 font-sans text-sm">
                <!-- 1. ARUS KAS DARI AKTIVITAS OPERASIONAL -->
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        1. Arus Kas dari Aktivitas Operasional
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(+) Penerimaan Kas dari Pendapatan</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($report['operating_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(-) Pembayaran Kas untuk Beban Operasional</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-rose-700 dark:text-rose-400">Rp {{ number_format($report['operating_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Arus Kas Bersih dari Aktivitas Operasional</td>
                                <td class="py-2.5 text-right font-mono font-bold">Rp {{ number_format($report['net_operating_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. ARUS KAS DARI AKTIVITAS INVESTASI -->
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        2. Arus Kas dari Aktivitas Investasi
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(+) Penjualan Aset Tetap / Penarikan Investasi</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($report['investing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(-) Pembelian Aset Tetap / Penempatan Investasi</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-rose-700 dark:text-rose-400">Rp {{ number_format($report['investing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Arus Kas Bersih dari Aktivitas Investasi</td>
                                <td class="py-2.5 text-right font-mono font-bold">Rp {{ number_format($report['net_investing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. ARUS KAS DARI AKTIVITAS PENDANAAN -->
                <div>
                    <h3 class="border-b-2 border-gray-300 pb-1.5 text-xs font-bold uppercase tracking-wider text-gray-950 dark:border-gray-700 dark:text-white">
                        3. Arus Kas dari Aktivitas Pendanaan (Financing)
                    </h3>
                    <table class="mt-2 w-full">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(+) Tambahan Modal / Penerimaan Pinjaman</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($report['financing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2.5 pl-4 text-gray-700 dark:text-gray-300">(-) Penarikan Modal (Prive) / Pembayaran Pinjaman</td>
                                <td class="py-2.5 text-right font-mono font-semibold text-rose-700 dark:text-rose-400">Rp {{ number_format($report['financing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-t border-gray-200 bg-gray-50/80 text-xs font-semibold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <td class="py-2.5 pl-4">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                                <td class="py-2.5 text-right font-mono font-bold">Rp {{ number_format($report['net_financing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- RINGKASAN SALDO KAS BERJALAN -->
                <div class="space-y-3 border-t-2 border-gray-900 pt-4 dark:border-gray-600">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50/80 px-4 py-2.5 text-xs font-semibold text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 md:text-sm">
                        <span>Kenaikan / (Penurunan) Bersih Kas & Bank</span>
                        <span class="font-mono font-bold {{ $report['net_change_in_cash'] >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                            Rp {{ number_format($report['net_change_in_cash'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between px-4 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 md:text-sm">
                        <span>Saldo Kas & Bank Awal Periode</span>
                        <span class="font-mono font-semibold text-gray-950 dark:text-white">Rp {{ number_format($report['beginning_cash'], 2, ',', '.') }}</span>
                    </div>

                    <div class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/80 px-5 py-3.5 font-bold text-emerald-950 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 md:px-6 md:py-4">
                        <div>
                            <span class="text-xs font-extrabold uppercase tracking-wide md:text-base">SALDO KAS & BANK AKHIR PERIODE</span>
                            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">Total likuiditas tersedia di seluruh akun Kas & Bank</p>
                        </div>
                        <span class="font-mono text-xl font-black text-emerald-800 dark:text-emerald-300 md:text-2xl">Rp {{ number_format($report['ending_cash'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
