<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-gray-200 dark:border-gray-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">LAPORAN ARUS KAS</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Metode Langsung (Direct Cash Flow)</p>
                <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-1">
                    Periode: {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d F Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Konten Laporan Arus Kas -->
            <div class="space-y-6 font-sans text-sm">
                <!-- 1. ARUS KAS DARI AKTIVITAS OPERASIONAL -->
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        1. Arus Kas dari Aktivitas Operasional
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(+) Penerimaan Kas dari Pendapatan</td>
                                <td class="py-2 text-right text-success-600 font-mono">Rp {{ number_format($report['operating_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(-) Pembayaran Kas untuk Beban Operasional</td>
                                <td class="py-2 text-right text-danger-600 font-mono">Rp {{ number_format($report['operating_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/30">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Arus Kas Bersih dari Aktivitas Operasional</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['net_operating_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 2. ARUS KAS DARI AKTIVITAS INVESTASI -->
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        2. Arus Kas dari Aktivitas Investasi
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(+) Penjualan Aset Tetap / Penarikan Investasi</td>
                                <td class="py-2 text-right text-success-600 font-mono">Rp {{ number_format($report['investing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(-) Pembelian Aset Tetap / Penempatan Investasi</td>
                                <td class="py-2 text-right text-danger-600 font-mono">Rp {{ number_format($report['investing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/30">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Arus Kas Bersih dari Aktivitas Investasi</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['net_investing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 3. ARUS KAS DARI AKTIVITAS PENDANAAN -->
                <div>
                    <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-xs border-b border-gray-300 dark:border-gray-700 pb-2">
                        3. Arus Kas dari Aktivitas Pendanaan (Financing)
                    </h3>
                    <table class="w-full mt-2">
                        <tbody>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(+) Tambahan Modal / Penerimaan Pinjaman</td>
                                <td class="py-2 text-right text-success-600 font-mono">Rp {{ number_format($report['financing_inflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="border-b border-gray-100 dark:border-gray-800/50">
                                <td class="py-2 text-gray-600 dark:text-gray-300 pl-4">(-) Penarikan Modal (Prive) / Pembayaran Pinjaman</td>
                                <td class="py-2 text-right text-danger-600 font-mono">Rp {{ number_format($report['financing_outflows'], 2, ',', '.') }}</td>
                            </tr>
                            <tr class="font-semibold text-xs border-t border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/30">
                                <td class="py-2 pl-4 text-gray-900 dark:text-white">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                                <td class="py-2 text-right text-gray-900 dark:text-white font-mono">Rp {{ number_format($report['net_financing_cashflow'], 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- RINGKASAN SALDO KAS BERJALAN -->
                <div class="pt-4 border-t-2 border-gray-900 dark:border-white space-y-3">
                    <div class="flex justify-between items-center py-2 px-4 bg-gray-100 dark:bg-gray-800 rounded font-semibold text-gray-800 dark:text-gray-200">
                        <span>Kenaikan / (Penurunan) Bersih Kas & Bank</span>
                        <span class="font-mono {{ $report['net_change_in_cash'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            Rp {{ number_format($report['net_change_in_cash'], 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-2 px-4 text-gray-600 dark:text-gray-300">
                        <span>Saldo Kas & Bank Awal Periode</span>
                        <span class="font-mono">Rp {{ number_format($report['beginning_cash'], 2, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between items-center py-4 px-6 bg-primary-50 dark:bg-primary-950/40 rounded-xl font-bold text-primary-950 dark:text-primary-100">
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
