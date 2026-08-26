@php
    $user = auth()->user();
    $botUsername = '@tm_accountant_bot';
    $allowedUserIds = config('telegram.allowed_user_ids', []);
    $isWhitelisted = in_array((string)$user?->telegram_chat_id, array_map('strval', $allowedUserIds));
@endphp

<div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-xs space-y-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-100 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6" />
            </div>
            <div>
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Integrasi Bot Telegram & AI DeepSeek</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Status sinkronisasi akun Telegram Anda untuk input transaksi cepat via smartphone.</p>
            </div>
        </div>

        <div>
            @if ($isWhitelisted)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-success-50 dark:bg-success-950/50 text-success-700 dark:text-success-300 border border-success-200 dark:border-success-800">
                    <span class="w-2 h-2 rounded-full bg-success-500"></span> Bot Terhubung & Whitelisted
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    ⚠️ Belum Terverifikasi
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800">
            <span class="text-gray-500 text-[11px]">Akun Bot Telegram:</span>
            <p class="font-mono font-bold text-gray-900 dark:text-white mt-0.5">{{ $botUsername }}</p>
        </div>

        <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800">
            <span class="text-gray-500 text-[11px]">Proteksi Keamanan:</span>
            <p class="font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">🔒 Whitelist Terkunci (.env)</p>
        </div>

        <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-800">
            <span class="text-gray-500 text-[11px]">Provider AI Engine:</span>
            <p class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">DeepSeek Flash / Chat</p>
        </div>
    </div>
</div>
