<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $stats = $this->getStatistics();
            $activities = $this->getActivities();
        @endphp

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 rounded-xl text-2xl shrink-0">
                        📜
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Aktivitas</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['total'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-success-50 dark:bg-success-950/60 text-success-600 dark:text-success-400 rounded-xl text-2xl shrink-0">
                        🧾
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaksi Jurnal</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['transaksi'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-info-50 dark:bg-info-950/60 text-info-600 dark:text-info-400 rounded-xl text-2xl shrink-0">
                        👛
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dompet & Rekening</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['dompet'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-warning-50 dark:bg-warning-950/60 text-warning-600 dark:text-warning-400 rounded-xl text-2xl shrink-0">
                        🤖
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telegram Bot</p>
                        <p class="text-2xl font-black text-gray-950 dark:text-white font-mono mt-0.5">{{ number_format($stats['bot'], 0, ',', '.') }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- Filter Form -->
        {{ $this->form }}

        <!-- Activity Timeline Table Section -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <span>📋</span>
                    <span>Catatan Aktivitas Sistem</span>
                </div>
            </x-slot>

            <x-slot name="headerEnd">
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                    Menampilkan {{ $activities->count() }} dari {{ $activities->total() }} aktivitas
                </span>
            </x-slot>

            @if($activities->isEmpty())
                <div class="py-12 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-4xl mb-3">📭</p>
                    <p class="font-semibold text-sm">Belum ada aktivitas yang tercatat pada filter ini.</p>
                </div>
            @else
                <div class="overflow-x-auto -mx-6 -my-4">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50/75 dark:bg-white/5 border-b border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-4 w-44">Waktu</th>
                                <th class="py-3.5 px-4 w-36">Kategori</th>
                                <th class="py-3.5 px-4 w-28">Aksi</th>
                                <th class="py-3.5 px-4 min-w-[280px]">Deskripsi Aktivitas</th>
                                <th class="py-3.5 px-4 w-40">Pelaku (Causer)</th>
                                <th class="py-3.5 px-4 w-24 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($activities as $act)
                                @php
                                    $detailData = ($act->properties && $act->properties->isNotEmpty()) 
                                        ? $act->properties->toArray() 
                                        : ($act->attribute_changes ? (is_array($act->attribute_changes) ? $act->attribute_changes : json_decode($act->attribute_changes, true)) : null);
                                @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5 transition">
                                    <td class="py-3.5 px-4 font-mono text-gray-600 dark:text-gray-400">
                                        {{ $act->created_at->translatedFormat('d M Y, H:i:s') }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($act->log_name === 'transaksi_jurnal')
                                            <x-filament::badge color="success" size="sm">🧾 Transaksi</x-filament::badge>
                                        @elseif($act->log_name === 'dompet_rekening')
                                            <x-filament::badge color="info" size="sm">👛 Dompet</x-filament::badge>
                                        @elseif($act->log_name === 'pengguna_autentikasi')
                                            <x-filament::badge color="primary" size="sm">👤 Pengguna</x-filament::badge>
                                        @elseif($act->log_name === 'telegram_bot')
                                            <x-filament::badge color="warning" size="sm">🤖 Telegram</x-filament::badge>
                                        @else
                                            <x-filament::badge color="gray" size="sm">{{ $act->log_name ?: 'System' }}</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($act->event === 'created')
                                            <x-filament::badge color="success" size="sm">Created</x-filament::badge>
                                        @elseif($act->event === 'updated')
                                            <x-filament::badge color="info" size="sm">Updated</x-filament::badge>
                                        @elseif($act->event === 'deleted')
                                            <x-filament::badge color="danger" size="sm">Deleted</x-filament::badge>
                                        @elseif($act->event === 'undo')
                                            <x-filament::badge color="warning" size="sm">Undo</x-filament::badge>
                                        @elseif($act->event === 'adjustment')
                                            <x-filament::badge color="warning" size="sm">Adjusted</x-filament::badge>
                                        @else
                                            <x-filament::badge color="gray" size="sm">{{ $act->event ?: 'Info' }}</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-gray-950 dark:text-white">
                                        {{ $act->description }}
                                    </td>
                                    <td class="py-3.5 px-4 text-gray-700 dark:text-gray-300">
                                        @if($act->causer)
                                            <span class="inline-flex items-center gap-1 font-semibold text-primary-600 dark:text-primary-400">
                                                👤 {{ $act->causer->name ?? $act->causer->email }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 italic">🤖 Sistem / Bot</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        @if(!empty($detailData))
                                            <details class="relative inline-block text-left">
                                                <summary class="cursor-pointer px-2.5 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 rounded-md text-[11px] font-semibold text-gray-700 dark:text-gray-300 transition">
                                                    Lihat Data
                                                </summary>
                                                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" onclick="this.parentElement.removeAttribute('open')">
                                                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl max-w-lg w-full p-5 shadow-2xl text-left" onclick="event.stopPropagation()">
                                                        <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 pb-3 mb-3">
                                                            <h4 class="font-bold text-gray-950 dark:text-white text-sm">📦 Data Properti Log</h4>
                                                            <button type="button" class="text-gray-400 hover:text-gray-600 text-lg" onclick="this.closest('details').removeAttribute('open')">&times;</button>
                                                        </div>
                                                        <pre class="bg-gray-50 dark:bg-gray-950 p-3 rounded-lg text-xs font-mono overflow-x-auto text-gray-800 dark:text-gray-200 max-h-72 border border-gray-100 dark:border-gray-800">{{ json_encode($detailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                </div>
                                            </details>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-white/10 mt-4">
                    {{ $activities->links() }}
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
