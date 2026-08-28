<x-filament-panels::page>
    <div class="space-y-8 font-sans">
        <!-- Hero Banner Status -->
        <div class="flex flex-col items-start justify-between gap-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white shadow-lg md:flex-row md:items-center">
            <div>
                <h2 class="text-2xl font-black tracking-tight">Selamat Datang di TM Accountant 📊</h2>
                <p class="mt-1 text-sm text-emerald-100">
                    Sistem pembukuan berpasangan (Double-Entry Bookkeeping) pribadi dengan integrasi AI & Telegram Bot.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center rounded-lg bg-white/20 px-3 py-1.5 font-mono backdrop-blur-sm">
                    🤖 Bot: @tm_accountant_bot
                </span>
                <span class="inline-flex items-center rounded-lg bg-white/20 px-3 py-1.5 font-mono backdrop-blur-sm">
                    🧠 AI: DeepSeek / Gemini
                </span>
            </div>
        </div>

        <!-- SECTION 1: PANDUAN TELEGRAM BOT -->
        <div class="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-2.5 text-blue-600 dark:border-blue-800/60 dark:bg-blue-950/50 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">1. Panduan & Tata Cara Bot Telegram</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cara mencatat pengeluaran, pemasukan, dan cek saldo cukup lewat chat santai</p>
                    </div>
                </div>
            </div>

            <!-- Cara Menjalankan Bot -->
            <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700/60 dark:bg-gray-800/40">
                <h4 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                    <x-filament::icon icon="heroicon-o-command-line" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    Cara Menjalankan Bot Telegram (Lokal / Polling)
                </h4>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                    Untuk pengujian di komputer lokal tanpa IP publik, jalankan perintah ini di terminal project:
                </p>
                <div class="mt-2 flex items-center justify-between rounded-lg border border-gray-800 bg-gray-950 p-3 font-mono text-xs text-emerald-400 dark:bg-black">
                    <code>php artisan telegram:poll</code>
                    <span class="text-[10px] text-gray-400">Ctrl + C untuk stop</span>
                </div>
            </div>

            <!-- Contoh Format Chat -->
            <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2">
                <!-- Pengeluaran -->
                <div class="space-y-2 rounded-xl border border-rose-200 bg-rose-50/60 p-4 dark:border-rose-900/60 dark:bg-rose-950/30">
                    <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
                        🔴 Mencatat Pengeluaran (Expense)
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Cukup ketik apa yang Anda beli, nominal, dan opsional metode bayar:</p>
                    <ul class="list-inside list-disc space-y-1 font-mono text-gray-800 dark:text-gray-200">
                        <li><code>beli telur 1 kg 25k</code></li>
                        <li><code>makan siang padang 35rb pake bca</code></li>
                        <li><code>isi bensin vario 40rb gopay</code></li>
                        <li><code>bayar wifi indihome 385rb mandiri</code></li>
                        <li><code>beli pakan kucing whiskas 85k</code></li>
                    </ul>
                </div>

                <!-- Pemasukan -->
                <div class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                    <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                        🟢 Mencatat Pemasukan (Income)
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Ketik sumber pemasukan dan rekening penerima:</p>
                    <ul class="list-inside list-disc space-y-1 font-mono text-gray-800 dark:text-gray-200">
                        <li><code>gaji bulan ini masuk 15jt ke bca</code></li>
                        <li><code>dapat bonus project 2jt tunai</code></li>
                        <li><code>komisi freelance 1.500.000 ke mandiri</code></li>
                        <li><code>ada yang bayar hutang 500rb tunai</code></li>
                    </ul>
                </div>

                <!-- Transfer Antar Akun -->
                <div class="space-y-2 rounded-xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                    <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">
                        🔄 Transfer Saldo Antar Rekening & E-Wallet
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Pindah buku antar kas atau rekening bank:</p>
                    <ul class="list-inside list-disc space-y-1 font-mono text-gray-800 dark:text-gray-200">
                        <li><code>transfer bca ke gopay 200rb</code></li>
                        <li><code>tarik tunai 500rb dari atm mandiri</code></li>
                        <li><code>top up shopeepay 100k dari bca</code></li>
                    </ul>
                </div>

                <!-- Tanya Saldo & Laporan -->
                <div class="space-y-2 rounded-xl border border-purple-200 bg-purple-50/60 p-4 dark:border-purple-900/60 dark:bg-purple-950/30">
                    <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-purple-700 dark:text-purple-400">
                        📈 Tanya Ringkasan & Saldo
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Minta AI merangkum keuangan Anda:</p>
                    <ul class="list-inside list-disc space-y-1 font-mono text-gray-800 dark:text-gray-200">
                        <li><code>/saldo</code> atau <code>saldo saya berapa</code></li>
                        <li><code>keuangan saya 1 minggu</code></li>
                        <li><code>pengeluaran bulan ini berapa</code></li>
                    </ul>
                </div>
            </div>

            <!-- Fitur Tombol Undo -->
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/30">
                <span class="text-2xl">↩️</span>
                <div class="space-y-1 text-xs">
                    <h5 class="font-bold text-amber-900 dark:text-amber-200">Fitur Tombol Batalkan (Undo)</h5>
                    <p class="text-amber-800/90 dark:text-amber-300/80">
                        Setiap transaksi yang dicatat via Telegram akan memunculkan tombol <b>[ ↩️ Batalkan / Undo ]</b>. Jika salah ketik nominal atau deskripsi, tekan tombol tersebut dan transaksi akan otomatis dibatalkan & saldo dikembalikan ke posisi semula tanpa jejak saldo rusak.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PANDUAN KELOLA DOMPET & ONBOARDING WIZARD -->
        <div class="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-2.5 text-purple-600 dark:border-purple-800/60 dark:bg-purple-950/50 dark:text-purple-400">
                        <x-filament::icon icon="heroicon-o-wallet" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">2. Panduan Kelola Dompet, Rekening, & Saldo Awal</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cara mengatur dompet tunai, rekening bank, e-wallet, saldo awal, dan dompet default</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-3">
                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        🧙‍♂️ Setup Wizard Onboarding
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Saat pertama kali login, Dashboard menampilkan banner pemandu 3 langkah untuk mencentang dompet yang Anda miliki, mengisi saldo riil saat ini, dan memilih dompet utama.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        ⭐ Dompet Utama (Default Wallet)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Dompet bertanda bintang (⭐) adalah sumber dana otomatis saat mencatat pengeluaran di Telegram tanpa menyebutkan nama bank (misal: <i>"beli telur 25k"</i>). Anda bisa menggantinya lewat web atau ketik <code>/default</code> di bot.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        ⚖️ Penyesuaian Saldo (Opname)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Jika saldo di buku berbeda dengan saldo nyata di m-banking, gunakan tombol aksi <b>Penyesuaian Saldo</b> pada menu Kelola Dompet. Sistem akan menghitung selisih dan membukukannya secara akurat.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PANDUAN LAPORAN KEUANGAN MEKARI STYLE -->
        <div class="space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-2.5 text-emerald-600 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-400">
                        <x-filament::icon icon="heroicon-o-document-chart-bar" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">3. Membaca Laporan Keuangan (Standar Mekari Jurnal)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Penjelasan arti dan fungsi masing-masing submenu pada Laporan Keuangan</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 text-xs md:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        📊 Laba Rugi (Income Statement)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Menampilkan seluruh Pendapatan dikurangi Harga Pokok Penjualan dan Beban Operasional pada rentang tanggal tertentu. Hasil akhirnya adalah <b>Surplus / Laba Bersih</b>.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        ⚖️ Neraca (Balance Sheet)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Menampilkan posisi kekayaan per tanggal tertentu dengan rumus akuntansi: <br>
                        <code class="font-bold text-emerald-600 dark:text-emerald-400">Total Aset = Kewajiban + Ekuitas (Modal + Laba)</code>.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        💸 Arus Kas (Cash Flow)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Metode langsung yang membedah mutasi uang tunai nyata masuk & keluar berdasarkan: Aktivitas Operasional, Investasi Aset, dan Pendanaan/Modal.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        📖 Buku Besar (General Ledger)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Kartu mutasi detail untuk satu akun terpilih (misal Bank BCA), menampilkan riwayat debit, kredit, dan saldo berjalan (*running balance*) baris demi baris.
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        🧮 Neraca Saldo (Trial Balance)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Rangkuman saldo akhir seluruh akun (Debit vs Kredit) untuk membuktikan integritas pembukuan berada dalam kondisi seimbang (balanced).
                    </p>
                </div>

                <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-800 dark:bg-gray-800/40">
                    <h4 class="flex items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-white">
                        ⚡ Transaksi Cepat (Web Input)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Halaman input transaksi harian praktis dari dashboard web tanpa perlu menyusun entri debit dan kredit secara manual.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 4: DEPLOYMENT KE VPS -->
        <div class="space-y-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-2.5 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">4. Setup Webhook untuk Server / VPS Cloud</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Menjalankan bot 24/7 tanpa perlu terminal polling</p>
                    </div>
                </div>
            </div>

            <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                Saat aplikasi sudah diunggah ke VPS dengan domain HTTPS (misal: <code>https://keuangan.domainanda.com</code>), daftarkan URL Webhook ke server Telegram dengan menjalankan perintah ini:
            </p>

            <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-950 p-4 font-mono text-xs text-emerald-400 dark:bg-black">
                <code>curl -F "url=https://keuangan.domainanda.com/api/telegram/webhook" https://api.telegram.org/bot{{ config('telegram.bot_token') }}/setWebhook</code>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Endpoint webhook telah otomatis dibebaskan dari proteksi CSRF di <code>bootstrap/app.php</code>.
            </p>
        </div>
    </div>
</x-filament-panels::page>
