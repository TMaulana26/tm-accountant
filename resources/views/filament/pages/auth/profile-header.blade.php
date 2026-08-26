@php
    $user = auth()->user();
    $name = $user?->name ?? 'Tomi';
    $initial = strtoupper(substr($name, 0, 1));
@endphp

<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 p-6 sm:p-8 text-white shadow-lg">
    <div class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center gap-5">
        <div class="relative">
            <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white font-black text-2xl border border-white/30 shadow-inner">
                {{ $initial }}
            </div>
            <span class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-400 border-2 border-emerald-900 rounded-full"></span>
        </div>

        <div class="space-y-1 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h2 class="text-xl sm:text-2xl font-black tracking-tight">{{ $name }}</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 backdrop-blur-md text-white border border-white/20">
                    Owner & Akuntan Utama
                </span>
            </div>
            <p class="text-xs sm:text-sm text-emerald-100 font-mono">
                {{ $user?->email }}
            </p>
            <p class="text-[11px] text-emerald-200/80">
                Bergabung sejak: {{ $user?->created_at?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 text-emerald-50">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Sistem Aktif
            </span>
        </div>
    </div>
</div>
