<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        @if($report)
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-8">
            <!-- Header Laporan -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-200 dark:border-gray-800 pb-6 mb-6 gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">BUKU BESAR</h2>
                    <p class="text-base font-semibold text-primary-600 dark:text-primary-400 mt-1">
                        [{{ $report['account']->code }}] {{ $report['account']->name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tipe: {{ $report['account']->type->getLabel() }} | Kategori: {{ $report['account']->category->getLabel() }}
                    </p>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-xs font-semibold uppercase text-gray-400">Periode Mutasi</p>
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d M Y') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Saldo Awal: <span class="font-mono font-bold text-gray-900 dark:text-white">Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}</span>
                    </p>
                </div>
            </div>

            <!-- Tabel Mutasi Jurnal Buku Besar -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm font-sans">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 uppercase text-xs border-y border-gray-200 dark:border-gray-700">
                            <th class="py-3 px-3 text-left">Tanggal</th>
                            <th class="py-3 px-3 text-left">No. Jurnal</th>
                            <th class="py-3 px-3 text-left">Deskripsi / Memo</th>
                            <th class="py-3 px-3 text-center">Sumber</th>
                            <th class="py-3 px-3 text-right">Debit (Rp)</th>
                            <th class="py-3 px-3 text-right">Kredit (Rp)</th>
                            <th class="py-3 px-3 text-right">Saldo Berjalan (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <!-- Baris Saldo Awal -->
                        <tr class="bg-gray-50/40 dark:bg-gray-800/20 font-semibold italic text-xs">
                            <td class="py-2.5 px-3 text-gray-500">{{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d/m/Y') }}</td>
                            <td class="py-2.5 px-3 text-gray-400">-</td>
                            <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300" colspan="4">Saldo Awal Sebelum Periode Ini</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @forelse($report['transactions'] as $tx)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $tx['date'] }}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-primary-600 dark:text-primary-400 whitespace-nowrap">{{ $tx['entry_number'] }}</td>
                                <td class="py-2.5 px-3 text-gray-800 dark:text-gray-200">{{ $tx['description'] }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tx['source']?->value === 'telegram' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $tx['source']?->getLabel() ?? 'Web' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-900 dark:text-white">
                                    {{ $tx['debit'] > 0 ? number_format($tx['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-gray-900 dark:text-white">
                                    {{ $tx['credit'] > 0 ? number_format($tx['credit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold {{ $tx['running_balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-danger-600' }}">
                                    Rp {{ number_format($tx['running_balance'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-gray-400 italic text-sm">
                                    Tidak ada mutasi transaksi untuk akun ini pada periode yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="font-bold border-t-2 border-gray-900 dark:border-white bg-primary-50 dark:bg-primary-950/40 text-primary-950 dark:text-primary-100">
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
