<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $report = $this->getReport();
        @endphp

        @if($report)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900 md:p-8">
            <!-- Header Laporan -->
            <div class="mb-6 flex flex-col items-start justify-between gap-4 border-b border-gray-200 pb-6 dark:border-gray-800 md:flex-row md:items-center">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">BUKU BESAR</h2>
                    <p class="mt-1 text-base font-semibold text-emerald-600 dark:text-emerald-400">
                        [{{ $report['account']->code }}] {{ $report['account']->name }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Tipe: {{ $report['account']->type->getLabel() }} | Kategori: {{ $report['account']->category->getLabel() }}
                    </p>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">Periode Mutasi</p>
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($report['end_date'])->translatedFormat('d M Y') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Saldo Awal: <span class="font-mono font-bold text-gray-900 dark:text-white">Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}</span>
                    </p>
                </div>
            </div>

            <!-- Tabel Mutasi Jurnal Buku Besar -->
            <div class="overflow-x-auto">
                <table class="w-full font-sans text-sm">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/90 text-xs font-bold uppercase text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
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
                        <tr class="bg-gray-50/50 text-xs font-semibold italic dark:bg-gray-800/40">
                            <td class="py-2.5 px-3 text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($report['start_date'])->translatedFormat('d/m/Y') }}</td>
                            <td class="py-2.5 px-3 text-gray-400 dark:text-gray-500">-</td>
                            <td class="py-2.5 px-3 text-gray-600 dark:text-gray-300" colspan="4">Saldo Awal Sebelum Periode Ini</td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                Rp {{ number_format($report['beginning_balance'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @forelse($report['transactions'] as $tx)
                            <tr class="transition-colors hover:bg-gray-50/70 dark:hover:bg-gray-800/50">
                                <td class="whitespace-nowrap py-2.5 px-3 text-gray-600 dark:text-gray-300">{{ $tx['date'] }}</td>
                                <td class="whitespace-nowrap py-2.5 px-3 font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ $tx['entry_number'] }}</td>
                                <td class="py-2.5 px-3 text-gray-800 dark:text-gray-200">{{ $tx['description'] }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $tx['source']?->value === 'telegram' ? 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-800 dark:bg-blue-950/80 dark:text-blue-300' : 'border-gray-200 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $tx['source']?->getLabel() ?? 'Web' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900 dark:text-white">
                                    {{ $tx['debit'] > 0 ? number_format($tx['debit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-gray-900 dark:text-white">
                                    {{ $tx['credit'] > 0 ? number_format($tx['credit'], 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold {{ $tx['running_balance'] >= 0 ? 'text-gray-900 dark:text-white' : 'text-rose-600 dark:text-rose-400' }}">
                                    Rp {{ number_format($tx['running_balance'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm italic text-gray-400 dark:text-gray-500">
                                    Tidak ada mutasi transaksi untuk akun ini pada periode yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-900 bg-emerald-50/90 font-bold text-emerald-950 dark:border-gray-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <td class="py-3 px-3 text-xs uppercase" colspan="6">SALDO AKHIR PERIODE</td>
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
