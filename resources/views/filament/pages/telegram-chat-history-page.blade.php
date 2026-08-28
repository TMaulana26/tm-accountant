<x-filament-panels::page>
    <style>
        .tm-chat-viewport {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .dark .tm-chat-viewport {
            background-color: #0b1120;
            border: 1px solid #1e293b;
        }

        .tm-chat-date-pill {
            background-color: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .dark .tm-chat-date-pill {
            background-color: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
        }

        .tm-user-bubble {
            background-color: #2563eb !important;
            color: #ffffff !important;
            border-radius: 1rem 1rem 0.25rem 1rem;
            padding: 0.75rem 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        .tm-user-bubble p, .tm-user-bubble span {
            color: #ffffff !important;
        }

        .tm-bot-bubble {
            background-color: #ffffff;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-radius: 1rem 1rem 1rem 0.25rem;
            padding: 0.85rem 1.15rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .dark .tm-bot-bubble {
            background-color: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
        }

        .tm-bot-content {
            font-size: 0.875rem;
            line-height: 1.6;
            color: inherit;
        }
        .tm-bot-content b, .tm-bot-content strong {
            font-weight: 700;
            color: inherit;
        }
        .tm-bot-content code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.82em;
            padding: 0.15rem 0.35rem;
            border-radius: 0.25rem;
            background-color: rgba(0, 0, 0, 0.06);
            color: #0284c7;
            font-weight: 600;
        }
        .dark .tm-bot-content code {
            background-color: rgba(255, 255, 255, 0.12);
            color: #38bdf8;
        }
        .tm-bot-content pre {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 0.75rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 0.5rem 0;
        }
        .dark .tm-bot-content pre {
            background-color: #020617;
            color: #f8fafc;
            border: 1px solid #1e293b;
        }
    </style>

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

            <div class="tm-chat-viewport rounded-2xl p-4 lg:p-6 min-h-[480px]">
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
                                <span class="tm-chat-date-pill px-4 py-1.5 rounded-full text-xs font-bold shadow-xs">
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

                                                <div class="tm-user-bubble text-sm">
                                                    @if($msg->receipt_image)
                                                        <div class="mb-2">
                                                            <a href="{{ Storage::disk('public')->url($msg->receipt_image) }}" target="_blank" class="block group relative overflow-hidden rounded-lg border border-white/30">
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
                                            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-950 flex items-center justify-center text-xs font-bold text-blue-700 dark:text-blue-300 shrink-0 border border-blue-200 dark:border-blue-800">
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
                                                        @elseif($msg->intent === 'query_account_balance' || $msg->intent === 'check_balance')
                                                            <x-filament::badge color="warning" size="sm">💳 Saldo</x-filament::badge>
                                                        @elseif($msg->intent === 'query_financial_summary' || $msg->intent === 'financial_report')
                                                            <x-filament::badge color="primary" size="sm">📊 Laporan</x-filament::badge>
                                                        @elseif($msg->intent === 'help')
                                                            <x-filament::badge color="info" size="sm">ℹ️ Bantuan</x-filament::badge>
                                                        @elseif($msg->intent === 'set_default_wallet')
                                                            <x-filament::badge color="warning" size="sm">⭐ Dompet</x-filament::badge>
                                                        @elseif($msg->intent === 'general_chat')
                                                            <x-filament::badge color="gray" size="sm">💬 Chat</x-filament::badge>
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

                                                    <div class="tm-bot-bubble text-sm space-y-2">
                                                        <div class="tm-bot-content">
                                                            {!! $this->formatChatResponse($msg->ai_response) !!}
                                                        </div>

                                                        @if($msg->journal_entry_id && $msg->journalEntry)
                                                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700/60 flex items-center justify-between text-xs">
                                                                <span class="text-gray-500 dark:text-gray-400 font-medium">Tercatat di Jurnal:</span>
                                                                <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
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
