# 📋 Roadmap & Spesifikasi Perencanaan: Multi-Mata Uang & Internasionalisasi (I18n)

Dokumen ini memuat arsitektur teknis, skema basis data, alur bisnis akuntansi, dan tahapan implementasi fitur **Multi-Mata Uang (Multi-Currency)** dan **Internasionalisasi (I18n)** untuk aplikasi **TM Accountant**.

---

## 🎯 1. Ringkasan & Tujuan

1. **Multi-Currency (Multi-Mata Uang)**:
   - Memungkinkan pengguna mencatat transaksi dan memiliki dompet/rekening dalam valuta asing (USD, SGD, JPY, EUR, MYR, GBP, AUD, CNY, dll.).
   - Menyediakan mata uang dasar (*Base Currency*: `IDR`) dengan pencatatan nilai konversi kurs (*Exchange Rate*) pada setiap entri jurnal pembukuan ganda (*double-entry*).
   - Mendukung pelacakan keuntungan/kerugian selisih kurs (*Realized & Unrealized Foreign Exchange Gain/Loss*).
   - Integrasi cerdas dengan Telegram Bot AI & Gemini Vision OCR untuk mendeteksi transaksi valas (contoh: `bayar server $25 via Paypal`, `beli makan di Singapur 12 SGD pakai Wise`).

2. **Internasionalisasi (I18n / Multi-Language)**:
   - Dukungan dwibahasa penuh: **Bahasa Indonesia (`id`)** dan **English (`en`)**.
   - Language Switcher interaktif pada panel admin Filament.
   - Respon Telegram Bot AI dan template pesan yang dapat menyesuaikan preferensi bahasa pengguna.
   - Ekspor laporan keuangan bilingual sesuai standar akuntansi (PSAK & IFRS terminologi).

---

## 💱 2. Arsitektur Teknis: Multi-Currency

### A. Konsep Akuntansi Valas (Multi-Currency Accounting)
- **Mata Uang Dasar (*Base Currency*)**: `IDR` (Rupiah). Semua laporan keuangan utama dan neraca saldo akan dikonsolidasikan dalam nilai Rupiah.
- **Entri Valuta Asing**: Setiap baris jurnal (`journal_items`) yang melibatkan akun valas akan mencatat:
  - `currency_code`: Kode mata uang (contoh: `USD`).
  - `exchange_rate`: Nilai kurs terhadap IDR saat transaksi terjadi (contoh: `16.250`).
  - `foreign_amount`: Nominal dalam valuta asli (contoh: `25.00`).
  - `debit` / `credit`: Nominal dalam IDR dasar (contoh: `406.250`).
- **Akun Selisih Kurs (*Exchange Rate Fluctuation*)**:
  - `[7-20001] Keuntungan Selisih Kurs (FX Gain)` (*Other Revenue*).
  - `[8-20001] Kerugian Selisih Kurs (FX Loss)` (*Other Expense*).

---

### B. Perubahan Skema Basis Data (Database Schema)

```
┌─────────────────────────────────┐       ┌─────────────────────────────────┐
│           currencies            │       │         currency_rates          │
├─────────────────────────────────┤       ├─────────────────────────────────┤
│ id (PK)                         │       │ id (PK)                         │
│ code (VARCHAR 3, e.g. USD)      │◄──┐   │ currency_id (FK -> currencies)  │
│ name (VARCHAR, e.g. US Dollar)  │   └───│ rate (DECIMAL 18, 6)            │
│ symbol (VARCHAR, e.g. $)        │       │ date (DATE)                     │
│ is_base (BOOLEAN, default false)│       │ source (VARCHAR: manual, api)   │
│ is_active (BOOLEAN)             │       │ created_at, updated_at          │
│ created_at, updated_at          │       └─────────────────────────────────┘
└─────────────────────────────────┘
                 ▲
                 │ (1 to Many)
┌────────────────┴────────────────┐       ┌─────────────────────────────────┐
│            accounts             │       │          journal_items          │
├─────────────────────────────────┤       ├─────────────────────────────────┤
│ ... (existing fields)           │       │ ... (existing fields)           │
│ currency_code (VARCHAR 3, 'IDR')│◄──────│ currency_code (VARCHAR 3, 'IDR')│
│                                 │       │ exchange_rate (DECIMAL 18, 6)   │
│                                 │       │ foreign_amount (DECIMAL 18, 2)  │
└─────────────────────────────────┘       └─────────────────────────────────┘
```

#### 1. Tabel Baru: `currencies`
- `id` (PK)
- `code` (string, 3 karakter, unik: `IDR`, `USD`, `SGD`, `EUR`, `JPY`, dll.)
- `name` (string: `Indonesian Rupiah`, `United States Dollar`, dll.)
- `symbol` (string: `Rp`, `$`, `S$`, `€`, `¥`, dll.)
- `decimal_places` (integer, default: `2`, untuk IDR/JPY: `0`)
- `is_base` (boolean, `true` untuk IDR)
- `is_active` (boolean, default: `true`)

#### 2. Tabel Baru: `currency_rates`
- `id` (PK)
- `currency_id` (FK -> `currencies.id`)
- `rate` (decimal 18,6: nilai tukar terhadap 1 unit mata uang dasar)
- `date` (date: tanggal kurs berlaku)
- `source` (string: `bank_indonesia`, `frankfurter_api`, `manual`)

#### 3. Penambahan Kolom pada `accounts`
- `currency_code` (string 3, default: `'IDR'`, foreign key ke `currencies.code`)

#### 4. Penambahan Kolom pada `journal_items`
- `currency_code` (string 3, default: `'IDR'`)
- `exchange_rate` (decimal 18,6, default: `1.000000`)
- `foreign_amount` (decimal 18,2, default: `0.00`)

---

### C. Sinkronisasi Kurs Otomatis (*Exchange Rate Sync*)
- Menggunakan Provider API Kurs Publik Gratis (seperti [Frankfurter API](https://www.frankfurter.app/) atau [ExchangeRate-API](https://www.exchangerate-api.com/)).
- **Scheduled Artisan Command**: `php artisan currency:sync-rates` dijadwalkan berjalan harian (misal: setiap jam 08:00 WIB).
- **Manual Input / Override**: Pengguna tetap bisa memasukkan kurs riil saat transaksi (misal kurs bank/kartu kredit yang berbeda dengan kurs pasar).

---

## 🌐 3. Arsitektur Teknis: Internasionalisasi (I18n)

### A. Struktur File Bahasa Laravel (`lang/`)
```
lang/
├── id/
│   ├── app.php          # Istilah umum, navigasi, branding
│   ├── accounting.php   # COA, Jurnal, Debit, Kredit, Neraca, Laba Rugi
│   ├── telegram.php     # Template pesan bot Telegram, respon AI
│   └── validation.php   # Pesan error validasi form
└── en/
    ├── app.php
    ├── accounting.php
    ├── telegram.php
    └── validation.php
```

### B. Language Switcher di Filament Panel
- Menambahkan tombol ganti bahasa (🇮🇩 ID / 🇬🇧 EN) di dropdown user menu navbar Filament.
- Menyimpan preferensi bahasa ke kolom `preferred_locale` pada tabel `users` dan `session('locale')`.
- Middleware `SetUserLocale` untuk mengatur `app()->setLocale($user->preferred_locale)`.

### C. Lokalisasi Bot Telegram
- Perintah `/language` atau tombol inline keyboard pada bot untuk memilih bahasa respon:
  - 🇮🇩 Bahasa Indonesia
  - 🇬🇧 English
- DeepSeek AI System Prompt dinamis yang merespons sesuai bahasa pilihan pengguna.

---

## 📅 4. Tahapan Eksekusi (Phased Implementation Plan)

### 📌 Fase 1: Fondasi Basis Data & Master Mata Uang
- [ ] Buat migration tabel `currencies` dan `currency_rates`.
- [ ] Tambahkan kolom `currency_code` pada tabel `accounts`, serta `currency_code`, `exchange_rate`, dan `foreign_amount` pada `journal_items`.
- [ ] Buat Seeder mata uang utama (`IDR`, `USD`, `SGD`, `EUR`, `JPY`, `MYR`, `GBP`, `AUD`).
- [ ] Buat Model Eloquent: `Currency` dan `CurrencyRate` beserta relasinya ke `Account` dan `JournalItem`.

### 📌 Fase 2: Exchange Rate Service & Sync Engine
- [ ] Buat `CurrencyRateService` untuk fetch kurs harian dari API publik gratis dan menyimpan histori kurs.
- [ ] Buat Artisan Command `app:sync-currency-rates` untuk update kurs otomatis via scheduler.
- [ ] Buat Resource Filament `CurrencyResource` untuk mengelola daftar mata uang aktif dan input kurs manual.

### 📌 Fase 3: Integrasi Akuntansi & Dompet Multi-Valas
- [ ] Perbarui `AccountResource` (Kelola Dompet & Rekening): Tambahkan pilihan mata uang dompet (misal: Dompet Paypal USD, Rekening DBS SGD).
- [ ] Perbarui `JournalEntryResource` & Form Input Transaksi Cepat:
  - Otomatis mengambil kurs terkini saat akun valas dipilih.
  - Menghitung otomatis nominal konversi IDR (`foreign_amount * exchange_rate = debit/credit IDR`).
- [ ] Buat service kalkulasi selisih kurs (*FX Gain/Loss*).

### 📌 Fase 4: Laporan Keuangan Multi-Currency
- [ ] Perbarui `FinancialReportService`:
  - Laporan Neraca & Laba Rugi menampilkan nilai konsolidasi dalam IDR.
  - Buku Besar (*General Ledger*) menampilkan saldo dalam valuta asli dan ekuivalen IDR.
- [ ] Tambahkan filter pilihan mata uang tampilan pada laporan keuangan.

### 📌 Fase 5: Integrasi Telegram Bot AI (Multi-Currency & OCR)
- [ ] Perbarui System Prompt DeepSeek & Gemini Vision untuk mengenali simbol dan kode mata uang asing (`$`, `USD`, `SGD`, `¥`, `EUR`, dll.).
- [ ] Otomatisasi konversi kurs saat pencatatan transaksi via chat/foto struk luar negeri.
- [ ] Perbarui template respon chat bot dengan rincian nominal valas dan kurs konversi IDR.

### 📌 Fase 6: Internasionalisasi (I18n) & Language Switcher
- [ ] Susun berkas translasi lengkap di `lang/id/` dan `lang/en/`.
- [ ] Buat middleware `SetLocaleMiddleware` dan daftarkan di `AdminPanelProvider`.
- [ ] Pasang Language Switcher component di Filament navbar/profil.
- [ ] Tambahkan perintah `/language` di Telegram Bot.

### 📌 Fase 7: Pengujian Otomatis (Pest) & Verifikasi Menyeluruh
- [ ] Unit Test `CurrencyRateServiceTest` & `ExchangeRateCalculationTest`.
- [ ] Feature Test pencatatan transaksi multi-mata uang via Web & Telegram Bot.
- [ ] Feature Test Language Switcher dan laporan keuangan bilingual.
- [ ] Dokumentasi penggunaan di `Panduan Penggunaan` Filament.

---

*Dokumen ini disimpan di `.agents/task.md` sebagai acuan eksekusi saat fitur Multi-Mata Uang & I18n mulai dikerjakan.*
