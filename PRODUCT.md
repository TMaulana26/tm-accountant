# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Individu profesional, pekerja lepas (freelancer), dan kreator digital ("Kang Tama") yang mengelola keuangan pribadi harian, multi-dompet (kas fisik, rekening bank, e-wallet), tabungan/investasi, serta pendapatan proyek freelance dalam satu ekosistem terpadu.

## Product Purpose

Menyediakan sistem pencatatan dan analisis akuntansi pribadi cerdas yang menggabungkan kemudahan input percakapan via bot Telegram AI dengan kedalaman dashboard web berbasis standar akuntansi double-entry (buku besar berimbang). Sukses berarti pengguna memiliki kendali penuh atas arus kas, saldo riil seluruh dompet selalu akurat, serta laporan keuangan (laba rugi, neraca, arus kas) tersaji seketika tanpa kerumitan pembukuan manual.

## Positioning

Satu-satunya asisten keuangan pribadi yang memadukan input natural language & OCR struk belanja via Telegram Bot bertenaga multi-model AI (Gemini, DeepSeek, OpenRouter) dengan sistem double-entry bookkeeping standar korporat di panel web Filament, dilengkapi otentikasi biometrik modern (Passkey) dan sinkronisasi saldo realtime (Laravel Reverb).

## Operating Context

- **Pencatatan Cepat Harian**: Pengguna mencatat mutasi saat beraktivitas mobile (beli makan, bayar kopi, isi bensin, terima fee) melalui chat Telegram dengan bahasa natural atau foto struk.
- **Command Center & Evaluasi Web**: Pengguna membuka panel web di laptop/desktop untuk meninjau dasbor grafik likuiditas, memeriksa laporan keuangan periodik (Laba Rugi, Neraca, Arus Kas), merekonsiliasi dompet, mengonfigurasi pengaturan sistem AI, dan mengevaluasi kesehatan finansial.
- **Dukungan Tema**: Responsif dan fleksibel mendukung Dark Mode dan Light Mode.

## Capabilities and Constraints

- **Buku Besar Akuntansi (Double-Entry)**: Setiap transaksi dicatat seimbang (debit/kredit) menggunakan bagan akun standar (Chart of Accounts: Aset, Kewajiban, Ekuitas, Pendapatan, Beban).
- **Multi-Wallet Management**: Melacak saldo terpisah untuk kas tunai, bank transfer (BCA, Mandiri, Jago), dan e-wallet (GoPay, ShopeePay, DANA).
- **Laporan Keuangan Komprehensif**: Neraca (Balance Sheet), Laba Rugi (Income Statement), Arus Kas (Cash Flow), Buku Besar (General Ledger), dan Neraca Saldo (Trial Balance).
- **Keamanan & Autentikasi Modern**: Login aman dengan biometrik Passkey (WebAuthn), proteksi sesi enkripsi, dan rate limiting.
- **Realtime Dashboard Sync**: Update mutasi dan saldo kas tanpa reload menggunakan WebSocket (Laravel Reverb).
- **Batasan Teknis**: Berjalan di atas stack Laravel 12, PHP 8.5, Filament v4, Livewire, Tailwind CSS v4, dan SQLite.

## Brand Commitments

- **Nama Brand**: TM Accountant
- **Persona & Gaya Bahasa**: Asisten akuntan pribadi yang ramah, sopan, cerdas, dengan sentuhan hangat kedaerahan khas Sunda ("Akang / Teteh", "Kang Tama").
- **Komitmen Palet Visual**:
  - **Primary**: Violet (melambangkan kecerdasan, modernitas finansial, dan prestise)
  - **Secondary**: Emerald (melambangkan kesehatan finansial, pertumbuhan kas, dan stabilitas)
  - **Trinary**: Biru Navy (melambangkan fondasi kepercayaan, ketelitian, dan kedalaman akuntansi)
  - **Accent**: Putih (melambangkan kejernihan, kontras tinggi, dan ruang baca yang bersih)

## Evidence on Hand

- Dasbor Filament aktif di `/admin` dengan 6 widget bawaan (Pinned Wallets, Wallet Onboarding, Account Balance, Income Expense Chart, Wallet Breakdown, Recent Journal Entries).
- Halaman laporan keuangan lengkap (`/admin/reports/*`).
- Halaman riwayat chat Telegram (`/admin/telegram-chat-history`).
- Seeder bagan akun Indonesia (`AccountSeeder`) dengan 40+ akun standar.
- Suite pengujian otomatis (81 test passing) di `tests/Feature/`.

## Product Principles

1. **Akurasi Tanpa Kompromi**: Setiap angka harus bersumber dari jurnal berimbang; tidak ada data dummy atau estimasi kabur pada laporan inti.
2. **Kecepatan di Lapangan, Kedalaman di Meja**: Chat Telegram untuk gesit mencatat dalam hitungan detik; Web Dashboard untuk mendalami wawasan dan kendali strategis.
3. **Hirarki Informasi yang Tenang & Terarah**: Informasi finansial bisa memicu stres; antarmuka harus memberikan ketenangan visual, kontras yang nyaman, dan kejelasan data tanpa visual clutter.
4. **Fleksibilitas Cahaya**: Tampilan harus nyaman dibaca baik dalam kondisi terang (Light Mode) maupun redup malam hari (Dark Mode).

## Accessibility & Inclusion

- Kontras warna teks dan elemen aksi harus memenuhi standar WCAG AA di kedua mode (Dark & Light).
- Format angka mata uang Indonesia (Rp, titik pemisah ribuan) yang konsisten dan mudah dipindai mata secara vertikal.
- Navigasi keyboard dan keterbacaan label yang ramah pembaca layar.
