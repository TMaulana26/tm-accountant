@php
    $user = auth()->user();
    $credentials = $user?->passkey_credentials ?? [];
    $hasBiometrics = count($credentials) > 0;
@endphp

<div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-xs space-y-5">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-gray-100 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
                <x-filament::icon icon="heroicon-o-finger-print" class="h-6 w-6" />
            </div>
            <div>
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Autentikasi Biometrik (Passkey / WebAuthn)</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Login instan menggunakan sidik jari (Touch ID / Fingerprint), wajah (Face ID / Windows Hello) tanpa mengetik kata sandi.</p>
            </div>
        </div>

        <div>
            @if ($hasBiometrics)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-success-50 dark:bg-success-950/50 text-success-700 dark:text-success-300 border border-success-200 dark:border-success-800">
                    <span class="w-2 h-2 rounded-full bg-success-500"></span> {{ count($credentials) }} Perangkat Aktif
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                    ⚪ Belum Diaktifkan
                </span>
            @endif
        </div>
    </div>

    <!-- Device List -->
    @if ($hasBiometrics)
        <div class="space-y-2">
            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Perangkat Terdaftar:</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($credentials as $cred)
                    <div class="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="text-lg">💻</span>
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white">{{ $cred['device_name'] ?? 'Perangkat Biometrik' }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">Didaftarkan: {{ $cred['registered_at'] ?? '-' }}</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold">Terverifikasi</span>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="p-4 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/30 text-xs text-amber-800 dark:text-amber-300 flex items-center gap-3">
            <span class="text-xl">💡</span>
            <p>Daftarkan laptop, komputer, atau smartphone ini agar Anda bisa masuk ke panel admin dengan satu sentuhan sidik jari atau pemindaian wajah.</p>
        </div>
    @endif

    <!-- Action Buttons -->
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button
            type="button"
            onclick="window.registerPasskey()"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs bg-purple-600 hover:bg-purple-700 text-white shadow-sm transition cursor-pointer"
        >
            <x-filament::icon icon="heroicon-m-plus-circle" class="h-4 w-4" />
            + Daftarkan Sidik Jari / Wajah Perangkat Ini
        </button>

        @if ($hasBiometrics)
            <button
                type="button"
                onclick="window.clearPasskeys()"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs bg-danger-50 dark:bg-danger-950/50 text-danger-700 dark:text-danger-300 border border-danger-200 dark:border-danger-800 hover:bg-danger-100 transition cursor-pointer"
            >
                <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                Hapus Semua Perangkat Biometrik
            </button>
        @endif
    </div>
</div>
