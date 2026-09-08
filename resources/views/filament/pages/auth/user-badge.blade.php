@php
    $user = \App\Models\User::first();
    $name = $user?->name ?? 'Admin';
    $email = $user?->email ?? 'admin@example.com';
    $initial = strtoupper(substr($name, 0, 1));
@endphp

<div class="flex items-center gap-3.5 p-3.5 bg-violet-50/70 dark:bg-slate-900/80 border border-violet-200/70 dark:border-slate-800 rounded-2xl shadow-xs">
    <div class="relative">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-violet-600 to-indigo-900 flex items-center justify-center text-white font-black text-lg shadow-sm">
            {{ $initial }}
        </div>
        <span class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full" title="Aktif"></span>
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
            <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ $name }}</h4>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-violet-100 text-violet-800 dark:bg-violet-900/60 dark:text-violet-300">
                Owner
            </span>
        </div>
        <p class="text-xs text-violet-700 dark:text-violet-400 font-mono truncate mt-0.5">
            {{ $email }}
        </p>
    </div>
</div>
