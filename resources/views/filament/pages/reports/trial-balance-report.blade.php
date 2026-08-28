<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900 md:p-8">
            <!-- Header Laporan -->
            <div class="mb-6 border-b border-gray-200 pb-6 text-center dark:border-gray-800">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">NERACA SALDO (TRIAL BALANCE)</h2>
                <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Buku Keuangan Pribadi</p>
                <p class="mt-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                    Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Tabel Neraca Saldo -->
            <div class="overflow-x-auto">
                <table class="w-full font-sans text-sm">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/90 text-xs font-bold uppercase text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="py-3 px-4 text-left">Kode Akun</th>
                            <th class="py-3 px-4 text-left">Nama Akun</th>
                            <th class="py-3 px-4 text-center">Tipe Akun</th>
                            <th class="py-3 px-4 text-right">Debit (Rp)</th>
                            <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($report['rows'] as $row)
                            <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-gray-800/50">
                                <td class="py-2.5 px-4 font-mono font-bold text-gray-700 dark:text-gray-300">{{ $row['code'] }}</td>
                                <td class="py-2.5 px-4 font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="inline-flex items-center rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ $row['type'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-medium text-gray-900 dark:text-white">
                                    {{ $row['debit'] > 0 ? number_format($row['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-medium text-gray-900 dark:text-white">
                                    {{ $row['credit'] > 0 ? number_format($row['credit'], 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center italic text-gray-400 dark:text-gray-500">
                                    Belum ada saldo akun atau transaksi yang dibukukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-900 bg-emerald-50/90 font-bold text-emerald-950 dark:border-gray-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <td class="py-3 px-4 text-xs uppercase" colspan="3">TOTAL KESELURUHAN (DEBIT & KREDIT)</td>
                            <td class="py-3 px-4 text-right font-mono text-base font-black">
                                Rp {{ number_format($report['total_debit'], 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-base font-black">
                                Rp {{ number_format($report['total_credit'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Balance Status Verification Badge -->
            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4 dark:border-gray-800">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                        <span>✓</span> Neraca Saldo Seimbang (Total Debit = Total Kredit)
                    </div>
                @else
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3.5 py-1.5 text-xs font-semibold text-rose-800 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                        <span>⚠</span> Neraca Saldo Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_debit'] - $report['total_credit']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
