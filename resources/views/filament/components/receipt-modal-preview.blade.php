<div class="space-y-4">
    @if ($record->receipt_image)
        <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2 text-center">
            <img
                src="{{ $record->receipt_image_url }}"
                alt="Lampiran Struk {{ $record->entry_number }}"
                class="max-h-[500px] w-auto mx-auto rounded-xl object-contain shadow-sm"
            />
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500">
            <span>🔖 No. Jurnal: <b>{{ $record->entry_number }}</b></span>
            <a
                href="{{ $record->receipt_image_url }}"
                target="_blank"
                class="font-bold text-emerald-600 hover:underline inline-flex items-center gap-1"
            >
                Buka Ukuran Penuh ↗
            </a>
        </div>
    @else
        <p class="text-sm text-gray-500 text-center py-6">Tidak ada lampiran foto struk untuk transaksi ini.</p>
    @endif
</div>
