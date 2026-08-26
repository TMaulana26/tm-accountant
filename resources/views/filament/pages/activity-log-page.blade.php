<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $stats = $this->getStatistics();
            $activities = $this->getActivities();
        @endphp

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs flex items-center gap-4">
                <div class="p-3.5 bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 rounded-xl text-2xl shrink-0">
                    📜
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Aktivitas</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ number_format($stats['total'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs flex items-center gap-4">
                <div class="p-3.5 bg-success-50 dark:bg-success-950/60 text-success-600 dark:text-success-400 rounded-xl text-2xl shrink-0">
                    🧾
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaksi Jurnal</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ number_format($stats['transaksi'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs flex items-center gap-4">
                <div class="p-3.5 bg-info-50 dark:bg-info-950/60 text-info-600 dark:text-info-400 rounded-xl text-2xl shrink-0">
                    👛
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dompet & Rekening</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ number_format($stats['dompet'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-xs flex items-center gap-4">
                <div class="p-3.5 bg-warning-50 dark:bg-warning-950/60 text-warning-600 dark:text-warning-400 rounded-xl text-2xl shrink-0">
                    🤖
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telegram Bot</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ number_format($stats['bot'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        {{ $this->form }}

        <!-- Activity Timeline Table -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xs border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                <h3 class="font-bold text-gray-900 dark:text-white text-sm flex items-center gap-2">
                    <span>📋</span> Catatan Aktivitas Sistem
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                    Menampilkan {{ $activities->count() }} dari {{ $activities->total() }} aktivitas
                </span>
            </div>

            @if($activities->isEmpty())
                <div class="py-16 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-4xl mb-3">📭</p>
                    <p class="font-semibold text-sm">Belum ada aktivitas yang tercatat pada filter ini.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-800/60 border-b border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4 w-44">Waktu</th>
                                <th class="py-3 px-4 w-36">Kategori</th>
                                <th class="py-3 px-4 w-28">Aksi</th>
                                <th class="py-3 px-4 min-w-[280px]">Deskripsi Aktivitas</th>
                                <th class="py-3 px-4 w-40">Pelaku (Causer)</th>
                                <th class="py-3 px-4 w-24 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($activities as $act)
                                @php
                                    $logNameBadge = match($act->log_name) {
                                        'transaksi_jurnal' => ['label' => '🧾 Transaksi', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800'],
                                        'dompet_rekening' => ['label' => '👛 Dompet', 'class' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-300 dark:border-blue-800'],
                                        'pengguna_autentikasi' => ['label' => '👤 Pengguna', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800'],
                                        'telegram_bot' => ['label' => '🤖 Bot Telegram', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800'],
                                        default => ['label' => $act->log_name ?: 'System', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'],
                                    };

                                    $eventBadge = match($act->event) {
                                        'created' => ['label' => 'Created', 'class' => 'bg-success-50 text-success-700 dark:bg-success-950/50 dark:text-success-300 border border-success-300 dark:border-success-800'],
                                        'updated' => ['label' => 'Updated', 'class' => 'bg-info-50 text-info-700 dark:bg-info-950/50 dark:text-info-300 border border-info-300 dark:border-info-800'],
                                        'deleted' => ['label' => 'Deleted', 'class' => 'bg-danger-50 text-danger-700 dark:bg-danger-950/50 dark:text-danger-300 border border-danger-300 dark:border-danger-800'],
                                        'undo' => ['label' => 'Undo', 'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-950/50 dark:text-warning-300 border border-warning-300 dark:border-warning-800'],
                                        'adjustment' => ['label' => 'Adjusted', 'class' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300 border border-purple-300 dark:border-purple-800'],
                                        default => ['label' => $act->event ?: 'Info', 'class' => 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
                                    };

                                    $detailData = ($act->properties && $act->properties->isNotEmpty()) ? $act->properties->toArray() : ($act->attribute_changes ? (is_array($act->attribute_changes) ? $act->attribute_changes : json_decode($act->attribute_changes, true)) : null);
                                @endphp
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40 transition">
                                    <td class="py-3 px-4 font-mono text-gray-500 dark:text-gray-400">
                                        {{ $act->created_at->translatedFormat('d M Y, H:i:s') }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $logNameBadge['class'] }}">
                                            {{ $logNameBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $eventBadge['class'] }}">
                                            {{ $eventBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $act->description }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-300">
                                        @if($act->causer)
                                            <span class="inline-flex items-center gap-1 font-semibold text-primary-600 dark:text-primary-400">
                                                👤 {{ $act->causer->name ?? $act->causer->email }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 italic">🤖 Sistem / Bot</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if(!empty($detailData))
                                            <details class="relative inline-block text-left">
                                                <summary class="cursor-pointer px-2.5 py-1 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-md text-[11px] font-semibold text-gray-700 dark:text-gray-300 transition">
                                                    Lihat Data
                                                </summary>
                                                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs" onclick="this.parentElement.removeAttribute('open')">
                                                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl max-w-lg w-full p-5 shadow-2xl text-left" onclick="event.stopPropagation()">
                                                        <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-800 pb-3 mb-3">
                                                            <h4 class="font-bold text-gray-900 dark:text-white text-sm">📦 Data Properti Log</h4>
                                                            <button type="button" class="text-gray-400 hover:text-gray-600 text-lg" onclick="this.closest('details').removeAttribute('open')">&times;</button>
                                                        </div>
                                                        <pre class="bg-gray-50 dark:bg-gray-950 p-3 rounded-lg text-xs font-mono overflow-x-auto text-gray-800 dark:text-gray-200 max-h-72">{{ json_encode($detailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
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

                <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
