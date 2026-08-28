<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        @if($report)
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 md:p-8">
            <!-- Header Laporan -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-slate-200 dark:border-slate-800 pb-6 mb-6 gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">BUKU BESAR</h2>
                    <p class="text-base font-semibold text-emerald-600 dark:text-emerald-400 mt-1">
                        [{{ $report['account']->code }}] {{ $report['account']->name }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Tipe: {{ $report['account']->type->getLabel() }} | Kategori: {{ $report['account']->category->getLabel() }}
                    </p>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">Periode Mutasi</p>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                        {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d M Y') }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Saldo Awal: <span class="font-mono font-bold text-slate-900 dark:text-white">Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}</span>
                    </p>
                </div>
            </div>

            <!-- Tabel Mutasi Jurnal Buku Besar -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 uppercase text-xs border-y border-slate-200 dark:border-slate-700">
                            <th class="py-3 px-3 text-left font-bold">Tanggal</th>
                            <th class="py-3 px-3 text-left font-bold">No. Jurnal</th>
                            <th class="py-3 px-3 text-left font-bold">Deskripsi / Memo</th>
                            <th class="py-3 px-3 text-center font-bold">Sumber</th>
                            <th class="py-3 px-3 text-right font-bold">Debit (Rp)</th>
                            <th class="py-3 px-3 text-right font-bold">Kredit (Rp)</th>
                            <th class="py-3 px-3 text-right font-bold">Saldo Berjalan (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <!-- Baris Saldo Awal -->
                        <tr class="bg-slate-50/50 dark:bg-slate-800/40 font-semibold italic text-xs">
                            <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d/m/Y') }}</td>
                            <td class="py-2.5 px-3 text-slate-400 dark:text-slate-500">-</td>
                            <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300" colspan="4">Saldo Awal Sebelum Periode Ini</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">
                                Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @forelse($report['transactions'] as $tx)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                                <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $tx['date'] }}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">{{ $tx['entry_number'] }}</td>
                                <td class="py-2.5 px-3 text-slate-800 dark:text-slate-200">{{ $tx['description'] }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tx['source']?->value === 'telegram' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-200 dark:border-blue-800' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                                        {{ $tx['source']?->getLabel() ?? 'Web' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-900 dark:text-white">
                                    {{ $tx['debit'] > 0 ? number_format($tx['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-900 dark:text-white">
                                    {{ $tx['credit'] > 0 ? number_format($tx['credit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold {{ $tx['running_balance'] >= 0 ? 'text-slate-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                    Rp {{ number_format($tx['running_balance'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400 dark:text-slate-500 italic text-sm">
                                    Tidak ada mutasi transaksi untuk akun ini pada periode yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border-t-2 border-slate-900 dark:border-slate-600 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-950 dark:text-emerald-100">
                            <td class="py-3 px-3 uppercase text-xs" colspan="6">SALDO AKHIR PERIODE</td>
                            <td class="py-3 px-3 text-right font-mono text-base font-black">
                                Rp {{ number_format($report['ending_balance'], 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>
