<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 md:p-8">
            <!-- Header Laporan -->
            <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-6 mb-6">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">NERACA SALDO (TRIAL BALANCE)</h2>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">Buku Keuangan Pribadi</p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                    Posisi Per Tanggal: {{ \Carbon\Carbon::parse($report['as_of_date'])->translatedFormat('d F Y') }}
                </p>
            </div>

            <!-- Tabel Neraca Saldo -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 uppercase text-xs border-y border-slate-200 dark:border-slate-700">
                            <th class="py-3 px-4 text-left font-bold">Kode Akun</th>
                            <th class="py-3 px-4 text-left font-bold">Nama Akun</th>
                            <th class="py-3 px-4 text-center font-bold">Tipe Akun</th>
                            <th class="py-3 px-4 text-right font-bold">Debit (Rp)</th>
                            <th class="py-3 px-4 text-right font-bold">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($report['rows'] as $row)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                <td class="py-2.5 px-4 font-mono font-bold text-slate-700 dark:text-slate-300">{{ $row['code'] }}</td>
                                <td class="py-2.5 px-4 text-slate-900 dark:text-white font-medium">{{ $row['name'] }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ $row['type'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-slate-900 dark:text-white">
                                    {{ $row['debit'] > 0 ? number_format($row['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono text-slate-900 dark:text-white">
                                    {{ $row['credit'] > 0 ? number_format($row['credit'], 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400 dark:text-slate-500 italic">
                                    Belum ada saldo akun atau transaksi yang dibukukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border-t-2 border-slate-900 dark:border-slate-600 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-950 dark:text-emerald-100">
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
            <div class="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                @if($report['is_balanced'])
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        <span>✓</span> Neraca Saldo Seimbang (Total Debit = Total Kredit)
                    </div>
                @else
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                        <span>⚠</span> Neraca Saldo Belum Seimbang (Selisih: Rp {{ number_format(abs($report['total_debit'] - $report['total_credit']), 2, ',', '.') }})
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
