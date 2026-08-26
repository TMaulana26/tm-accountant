<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $groupedMessages = $this->getMessages();
        @endphp

        <!-- Filter Form -->
        {{ $this->form }}

        <!-- Messenger Chat Viewport Section -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <span>💬</span>
                    <span>Riwayat Percakapan Interaktif</span>
                </div>
            </x-slot>

            <div class="rounded-2xl p-4 lg:p-6 min-h-[480px] bg-gray-100/70 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800">
                @if($groupedMessages->isEmpty())
                    <div class="py-24 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-800 text-3xl mb-3 shadow-xs">
                            💬
                        </div>
                        <h4 class="font-bold text-base text-gray-900 dark:text-white">Tidak ada riwayat percakapan</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto leading-relaxed">
                            Gunakan filter periode di atas atau kirim pesan ke Telegram Bot Anda untuk memulai pencatatan otomatis.
                        </p>
                    </div>
                @else
                    <div class="space-y-8 max-w-4xl mx-auto">
                        @foreach($groupedMessages as $dateString => $messages)
                            <!-- Date Header Separator -->
                            <div class="flex items-center justify-center my-4">
                                <span class="px-4 py-1.5 rounded-full text-xs font-bold bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-xs">
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

                                                <div class="bg-primary-600 text-white rounded-2xl rounded-br-xs px-4 py-2.5 shadow-sm text-sm">
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
                                            <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-950 flex items-center justify-center text-xs font-bold text-primary-700 dark:text-primary-300 shrink-0 border border-primary-200 dark:border-primary-800">
                                                {{ strtoupper(substr($msg->from_username ?: 'U', 0, 2)) }}
                                            </div>
                                        </div>

                                        <!-- 2. BOT CHAT BUBBLE (Left Aligned) -->
                                        @if($msg->ai_response)
                                            <div class="flex justify-start items-start gap-2 pr-12">
                                                <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-950/80 border border-amber-300 dark:border-amber-700 flex items-center justify-center text-sm shrink-0">
                                                    🤖
                                                </div>

                                                <div class="flex flex-col items-start max-w-lg">
                                                    <div class="flex items-center gap-2 mb-1 pl-1">
                                                        <span class="text-[11px] font-bold text-gray-800 dark:text-gray-200">
                                                            TM AI Assistant
                                                        </span>

                                                        @if($msg->intent === 'record_expense')
                                                            <x-filament::badge color="danger" size="sm">💸 Pengeluaran</x-filament::badge>
                                                        @elseif($msg->intent === 'record_income')
                                                            <x-filament::badge color="success" size="sm">💰 Pemasukan</x-filament::badge>
                                                        @elseif($msg->intent === 'record_transfer')
                                                            <x-filament::badge color="info" size="sm">🔄 Transfer</x-filament::badge>
                                                        @elseif($msg->intent === 'check_balance')
                                                            <x-filament::badge color="warning" size="sm">💳 Saldo</x-filament::badge>
                                                        @elseif($msg->intent === 'financial_report')
                                                            <x-filament::badge color="primary" size="sm">📊 Laporan</x-filament::badge>
                                                        @else
                                                            <x-filament::badge color="gray" size="sm">{{ $msg->intent ?: 'Bot' }}</x-filament::badge>
                                                        @endif

                                                        @if($msg->status === \App\Enums\TelegramMessageStatus::Processed)
                                                            <x-filament::badge color="success" size="sm">✓ Sukses</x-filament::badge>
                                                        @elseif($msg->status === \App\Enums\TelegramMessageStatus::Reverted)
                                                            <x-filament::badge color="warning" size="sm">↩️ Undo</x-filament::badge>
                                                        @elseif($msg->status === \App\Enums\TelegramMessageStatus::Failed)
                                                            <x-filament::badge color="danger" size="sm">❌ Gagal</x-filament::badge>
                                                        @else
                                                            <x-filament::badge color="gray" size="sm">⏳ Pending</x-filament::badge>
                                                        @endif
                                                    </div>

                                                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100 rounded-2xl rounded-bl-xs px-4 py-3 shadow-xs text-sm space-y-2">
                                                        <div class="prose dark:prose-invert max-w-none text-xs leading-relaxed text-gray-900 dark:text-gray-100">
                                                            {!! nl2br($msg->ai_response) !!}
                                                        </div>

                                                        @if($msg->journal_entry_id && $msg->journalEntry)
                                                            <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Tercatat di Jurnal:</span>
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
        </x-filament::section>
    </div>
</x-filament-panels::page>
