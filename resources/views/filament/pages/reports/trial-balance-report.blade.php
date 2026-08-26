<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-gray-200 dark:border-gray-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">NERACA SALDO (TRIAL BALANCE)</h2>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">Buku Keuangan Pribadi</p>
                <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-1">
                    Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Tabel Neraca Saldo -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 uppercase text-xs border-y border-gray-200 dark:border-gray-700">
                            <th class="py-3 px-4 text-left font-bold">Kode Akun</th>
                            <th class="py-3 px-4 text-left font-bold">Nama Akun</th>
                            <th class="py-3 px-4 text-center font-bold">Tipe Akun</th>
                            <th class="py-3 px-4 text-right font-bold">Debit (Rp)</th>
                            <th class="py-3 px-4 text-right font-bold">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="py-2.5 px-4 font-mono font-bold text-gray-700 dark:text-gray-300">{{ $row['code'] }}</td>
                                <td class="py-2.5 px-4 text-gray-900 dark:text-white font-medium">{{ $row['name'] }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                        {{ $row['type'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-gray-900 dark:text-white">
                                    {{ $row['debit'] > 0 ? number_format($row['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-gray-900 dark:text-white">
                                    {{ $row['credit'] > 0 ? number_format($row['credit'], 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-gray-400 italic">
                                    Belum ada saldo akun atau transaksi yang dibukukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border-t-2 border-gray-900 dark:border-white bg-primary-50 dark:bg-primary-950/40 text-primary-950 dark:text-primary-100">
                            <td class="py-3 px-4 uppercase text-xs" colspan="3">TOTAL KESELURUHAN (DEBIT & KREDIT)</td>
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
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-end">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300">
                        <span>✓</span> Neraca Saldo Seimbang (Total Debit = Total Kredit)
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-300">
                        <span>⚠</span> Neraca Saldo Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_debit'] - $report['total_credit']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
