<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $stats = $this->getStatistics();
            $groupedMessages = $this->getMessages();
        @endphp

        <!-- Metric Stat Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 rounded-lg text-2xl">
                    💬
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Chat</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono">{{ number_format($stats['total'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-success-50 dark:bg-success-950/50 text-success-600 dark:text-success-400 rounded-lg text-2xl">
                    ✅
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transaksi Sukses</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono">{{ number_format($stats['processed'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-info-50 dark:bg-info-950/50 text-info-600 dark:text-info-400 rounded-lg text-2xl">
                    📸
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">OCR Struk / Nota</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono">{{ number_format($stats['ocr'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
                <div class="p-3 bg-warning-50 dark:bg-warning-950/50 text-warning-600 dark:text-warning-400 rounded-lg text-2xl">
                    ↩️
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dibatalkan / Undo</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white font-mono">{{ number_format($stats['reverted'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        {{ $this->form }}

        <!-- Messenger Chat Viewport -->
        <div class="bg-gray-100/70 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 lg:p-6 shadow-inner min-h-[500px]">
            @if($groupedMessages->isEmpty())
                <div class="py-24 text-center text-gray-500 dark:text-gray-400">
                    <p class="text-5xl mb-3">💬</p>
                    <p class="font-bold text-base text-gray-700 dark:text-gray-300">Tidak ada riwayat percakapan</p>
                    <p class="text-xs mt-1">Gunakan filter di atas atau kirim pesan ke Telegram Bot untuk memulai pencatatan.</p>
                </div>
            @else
                <div class="space-y-8 max-w-4xl mx-auto">
                    @foreach($groupedMessages as $dateString => $messages)
                        <!-- Date Header Separator -->
                        <div class="flex items-center justify-center my-4">
                            <span class="px-4 py-1 rounded-full text-xs font-bold bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 shadow-xs">
                                📅 {{ \Carbon\Carbon::parse($dateString)->translatedFormat('l, d F Y') }}
                            </span>
                        </div>

                        <!-- Messages in this date -->
                        <div class="space-y-6">
                            @foreach($messages as $msg)
                                <div class="space-y-3">
                                    <!-- 1. USER CHAT BUBBLE (Right Aligned) -->
                                    <div class="flex justify-end items-end gap-2 pl-12">
                                        <div class="flex flex-col items-end max-w-lg">
                                            <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 mb-1 pr-1">
                                                👤 {{ $msg->from_username ? '@'.$msg->from_username : 'Pemilik Pembukuan' }} • {{ $msg->created_at->format('H:i') }}
                                            </span>

                                            <div class="bg-primary-600 dark:bg-primary-700 text-white rounded-2xl rounded-br-xs px-4 py-2.5 shadow-sm text-sm">
                                                @if($msg->receipt_image)
                                                    <div class="mb-2">
                                                        <a href="{{ Storage::disk('public')->url($msg->receipt_image) }}" target="_blank" class="block group relative overflow-hidden rounded-lg border border-white/20">
                                                            <img src="{{ Storage::disk('public')->url($msg->receipt_image) }}" alt="Foto Struk" class="w-44 h-32 object-cover transition group-hover:scale-105" />
                                                            <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-xs font-bold text-white">
                                                                🔍 Buka Foto
                                                            </div>
                                                        </a>
                                                    </div>
                                                @endif

                                                <p class="whitespace-pre-wrap leading-relaxed">{{ $msg->raw_text ?: '[Mengirim Foto Struk/Nota]' }}</p>
                                            </div>
                                        </div>
                                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-xs font-bold text-primary-700 dark:text-primary-300 shrink-0">
                                            {{ strtoupper(substr($msg->from_username ?: 'U', 0, 2)) }}
                                        </div>
                                    </div>

                                    <!-- 2. BOT CHAT BUBBLE (Left Aligned) -->
                                    @if($msg->ai_response)
                                        <div class="flex justify-start items-start gap-2 pr-12">
                                            <div class="w-8 h-8 rounded-full bg-amber-500/20 dark:bg-amber-500/30 border border-amber-500/30 flex items-center justify-center text-sm shrink-0">
                                                🤖
                                            </div>

                                            <div class="flex flex-col items-start max-w-lg">
                                                <div class="flex items-center gap-2 mb-1 pl-1">
                                                    <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300">
                                                        TM AI Assistant
                                                    </span>

                                                    @php
                                                        $intentLabel = match($msg->intent) {
                                                            'record_expense' => ['label' => '💸 Pengeluaran', 'class' => 'bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-300'],
                                                            'record_income' => ['label' => '💰 Pemasukan', 'class' => 'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-300'],
                                                            'record_transfer' => ['label' => '🔄 Transfer', 'class' => 'bg-info-100 text-info-800 dark:bg-info-950 dark:text-info-300'],
                                                            'check_balance' => ['label' => '💳 Cek Saldo', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300'],
                                                            'financial_report' => ['label' => '📊 Laporan', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300'],
                                                            default => ['label' => $msg->intent ?: 'Percakapan', 'class' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'],
                                                        };

                                                        $statusLabel = match($msg->status) {
                                                            \App\Enums\TelegramMessageStatus::Processed => ['label' => '✓ Sukses', 'class' => 'bg-success-50 text-success-700 dark:bg-success-950/50 dark:text-success-300 border border-success-200 dark:border-success-800'],
                                                            \App\Enums\TelegramMessageStatus::Reverted => ['label' => '↩️ Dibatalkan', 'class' => 'bg-warning-50 text-warning-700 dark:bg-warning-950/50 dark:text-warning-300 border border-warning-200 dark:border-warning-800'],
                                                            \App\Enums\TelegramMessageStatus::Failed => ['label' => '❌ Gagal', 'class' => 'bg-danger-50 text-danger-700 dark:bg-danger-950/50 dark:text-danger-300 border border-danger-200 dark:border-danger-800'],
                                                            default => ['label' => '⏳ Pending', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
                                                        };
                                                    @endphp

                                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $intentLabel['class'] }}">
                                                        {{ $intentLabel['label'] }}
                                                    </span>

                                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusLabel['class'] }}">
                                                        {{ $statusLabel['label'] }}
                                                    </span>
                                                </div>

                                                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-100 rounded-2xl rounded-bl-xs px-4 py-3 shadow-sm text-sm space-y-2">
                                                    <div class="prose dark:prose-invert max-w-none text-xs leading-relaxed">
                                                        {!! nl2br($msg->ai_response) !!}
                                                    </div>

                                                    @if($msg->journal_entry_id && $msg->journalEntry)
                                                        <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                                                            <span class="text-gray-500 dark:text-gray-400">Tercatat di Jurnal:</span>
                                                            <span class="font-mono font-bold text-primary-600 dark:text-primary-400">
                                                                {{ $msg->journalEntry->entry_number }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
