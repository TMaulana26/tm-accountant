<x-filament-panels::page>
    <div class="space-y-8 font-sans">
        <!-- Hero Banner Status -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-700 rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-black tracking-tight">Selamat Datang di TM Accountant 📊</h2>
                <p class="text-emerald-100 text-sm mt-1">
                    Sistem pembukuan berpasangan (Double-Entry Bookkeeping) pribadi dengan integrasi AI DeepSeek & Telegram Bot.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg font-mono bg-white/20 backdrop-blur-sm">
                    🤖 Bot: @tm_accountant_bot
                </span>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg font-mono bg-white/20 backdrop-blur-sm">
                    🧠 AI: DeepSeek Flash / Chat
                </span>
            </div>
        </div>

        <!-- SECTION 1: PANDUAN TELEGRAM BOT -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400">
                        <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">1. Panduan & Tata Cara Bot Telegram</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cara mencatat pengeluaran, pemasukan, dan cek saldo cukup lewat chat santai</p>
                    </div>
                </div>
            </div>

            <!-- Cara Menjalankan Bot -->
            <div class="bg-gray-50 dark:bg-gray-800/40 rounded-xl p-4 border border-gray-200 dark:border-gray-700/60">
                <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-command-line" class="h-4 w-4 text-primary-600" />
                    Cara Menjalankan Bot Telegram (Lokal / Polling)
                </h4>
                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                    Untuk pengujian di komputer lokal tanpa IP publik, jalankan perintah ini di terminal project:
                </p>
                <div class="mt-2 bg-gray-900 text-emerald-400 font-mono text-xs p-3 rounded-lg flex justify-between items-center">
                    <code>php artisan telegram:poll</code>
                    <span class="text-[10px] text-gray-400">Ctrl + C untuk stop</span>
                </div>
            </div>

            <!-- Contoh Format Chat -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- Pengeluaran -->
                <div class="p-4 rounded-xl border border-danger-200 dark:border-danger-900/40 bg-danger-50/30 dark:bg-danger-950/20 space-y-2">
                    <span class="font-bold text-danger-700 dark:text-danger-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        🔴 Mencatat Pengeluaran (Expense)
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Cukup ketik apa yang Anda beli, nominal, dan opsional metode bayar:</p>
                    <ul class="space-y-1 font-mono text-gray-800 dark:text-gray-200 list-disc list-inside">
                        <li><code>beli telur 1 kg 25k</code></li>
                        <li><code>makan siang padang 35rb pake bca</code></li>
                        <li><code>isi bensin vario 40rb gopay</code></li>
                        <li><code>bayar wifi indihome 385rb mandiri</code></li>
                        <li><code>beli pakan kucing whiskas 85k</code></li>
                    </ul>
                </div>

                <!-- Pemasukan -->
                <div class="p-4 rounded-xl border border-success-200 dark:border-success-900/40 bg-success-50/30 dark:bg-success-950/20 space-y-2">
                    <span class="font-bold text-success-700 dark:text-success-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        🟢 Mencatat Pemasukan (Income)
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Ketik sumber pemasukan dan rekening penerima:</p>
                    <ul class="space-y-1 font-mono text-gray-800 dark:text-gray-200 list-disc list-inside">
                        <li><code>gaji bulan ini masuk 15jt ke bca</code></li>
                        <li><code>dapat bonus project 2jt tunai</code></li>
                        <li><code>komisi freelance 1.500.000 ke mandiri</code></li>
                        <li><code>ada yang bayar hutang 500rb tunai</code></li>
                    </ul>
                </div>

                <!-- Transfer Antar Akun -->
                <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/40 bg-blue-50/30 dark:bg-blue-950/20 space-y-2">
                    <span class="font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        🔄 Transfer Saldo Antar Rekening & E-Wallet
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Pindah buku antar kas atau rekening bank:</p>
                    <ul class="space-y-1 font-mono text-gray-800 dark:text-gray-200 list-disc list-inside">
                        <li><code>transfer bca ke gopay 200rb</code></li>
                        <li><code>tarik tunai 500rb dari atm mandiri</code></li>
                        <li><code>top up shopeepay 100k dari bca</code></li>
                    </ul>
                </div>

                <!-- Tanya Saldo & Laporan -->
                <div class="p-4 rounded-xl border border-purple-200 dark:border-purple-900/40 bg-purple-50/30 dark:bg-purple-950/20 space-y-2">
                    <span class="font-bold text-purple-700 dark:text-purple-400 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                        📈 Tanya Ringkasan & Saldo
                    </span>
                    <p class="text-gray-600 dark:text-gray-300">Minta AI merangkum keuangan Anda:</p>
                    <ul class="space-y-1 font-mono text-gray-800 dark:text-gray-200 list-disc list-inside">
                        <li><code>/saldo</code> atau <code>saldo saya berapa</code></li>
                        <li><code>keuangan saya 1 minggu</code></li>
                        <li><code>pengeluaran bulan ini berapa</code></li>
                    </ul>
                </div>
            </div>

            <!-- Fitur Tombol Undo -->
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/40 flex items-start gap-3">
                <span class="text-2xl">↩️</span>
                <div class="text-xs space-y-1">
                    <h5 class="font-bold text-amber-900 dark:text-amber-200">Fitur Tombol Batalkan (Undo)</h5>
                    <p class="text-amber-800/90 dark:text-amber-300/80">
                        Setiap transaksi yang dicatat via Telegram akan memunculkan tombol <b>[ ↩️ Batalkan / Undo ]</b>. Jika salah ketik nominal atau deskripsi, tekan tombol tersebut dan transaksi akan otomatis dibatalkan & saldo dikembalikan ke posisi semula tanpa jejak saldo rusak.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PANDUAN KELOLA DOMPET & ONBOARDING WIZARD -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400">
                        <x-filament::icon icon="heroicon-o-wallet" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">2. Panduan Kelola Dompet, Rekening, & Saldo Awal</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Cara mengatur dompet tunai, rekening bank, e-wallet, saldo awal, dan dompet default</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        🧙‍♂️ Setup Wizard Onboarding
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Saat pertama kali login, Dashboard menampilkan banner pemandu 3 langkah untuk mencentang dompet yang Anda miliki, mengisi saldo riil saat ini, dan memilih dompet utama.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        ⭐ Dompet Utama (Default Wallet)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Dompet bertanda bintang (⭐) adalah sumber dana otomatis saat mencatat pengeluaran di Telegram tanpa menyebutkan nama bank (misal: <i>"beli telur 25k"</i>). Anda bisa menggantinya lewat web atau ketik <code>/default</code> di bot.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        ⚖️ Penyesuaian Saldo (Opname)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Jika saldo di buku berbeda dengan saldo nyata di m-banking, gunakan tombol aksi <b>Penyesuaian Saldo</b> pada menu Kelola Dompet. Sistem akan menghitung selisih dan membukukannya secara akurat.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PANDUAN LAPORAN KEUANGAN MEKARI STYLE -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400">
                        <x-filament::icon icon="heroicon-o-document-chart-bar" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">3. Membaca Laporan Keuangan (Standar Mekari Jurnal)</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Penjelasan arti dan fungsi masing-masing submenu pada Laporan Keuangan</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        📊 Laba Rugi (Income Statement)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Menampilkan seluruh Pendapatan dikurangi Harga Pokok Penjualan dan Beban Operasional pada rentang tanggal tertentu. Hasil akhirnya adalah <b>Surplus / Laba Bersih</b>.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        ⚖️ Neraca (Balance Sheet)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Menampilkan posisi kekayaan per tanggal tertentu dengan rumus akuntansi: <br>
                        <code class="font-bold text-primary-600">Total Aset = Kewajiban + Ekuitas (Modal + Laba)</code>.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        💸 Arus Kas (Cash Flow)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Metode langsung yang membedah mutasi uang tunai nyata masuk & keluar berdasarkan: Aktivitas Operasional, Investasi Aset, dan Pendanaan/Modal.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        📖 Buku Besar (General Ledger)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Kartu mutasi detail untuk satu akun terpilih (misal Bank BCA), menampilkan riwayat debit, kredit, dan saldo berjalan (*running balance*) baris demi baris.
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        🧮 Neraca Saldo (Trial Balance)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Rangkuman saldo akhir seluruh akun (Debit vs Kredit) untuk membuktikan integritas pembukuan berada dalam kondisi seimbang (balanced).
                    </p>
                </div>

                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 space-y-2">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white flex items-center gap-1.5">
                        ⚡ Transaksi Cepat (Web Input)
                    </h4>
                    <p class="text-gray-600 dark:text-gray-300">
                        Halaman input transaksi harian praktis dari dashboard web tanpa perlu menyusun entri debit dan kredit secara manual.
                    </p>
                </div>
            </div>
        </div>

        <!-- SECTION 3: DEPLOYMENT KE VPS TENCENT -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-6 space-y-4">
            <div class="border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">3. Setup Webhook untuk VPS Tencent Cloud</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Menjalankan bot 24/7 tanpa perlu terminal polling</p>
                    </div>
                </div>
            </div>

            <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                Saat aplikasi sudah diunggah ke VPS Tencent dengan domain HTTPS (misal: <code>https://keuangan.domainanda.com</code>), daftarkan URL Webhook ke server Telegram dengan menjalankan perintah ini:
            </p>

            <div class="bg-gray-900 text-emerald-400 font-mono text-xs p-4 rounded-xl overflow-x-auto">
                <code>curl -F "url=https://keuangan.domainanda.com/api/telegram/webhook" https://api.telegram.org/bot{{ config('telegram.bot_token') }}/setWebhook</code>
            </div>

            <p class="text-xs text-gray-500">
                Endpoint webhook telah otomatis dibebaskan dari proteksi CSRF di <code>bootstrap/app.php</code>.
            </p>
        </div>
    </div>
</x-filament-panels::page>
