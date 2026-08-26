# 🤖 Panduan Lengkap Pembuatan & Pengaturan Bot Telegram TM Accountant

Panduan ini memandu Anda langkah demi langkah mulai dari membuat bot di Telegram, mendapatkan API Token & User ID, menghubungkannya ke DeepSeek AI, hingga siap digunakan untuk mencatat keuangan pribadi via chat.

---

## 📑 Daftar Isi
1. [Langkah 1: Membuat Bot di Telegram via @BotFather](#1-langkah-1-membuat-bot-di-telegram-via-botfather)
2. [Langkah 2: Mengetahui Telegram User ID (Otorisasi & Keamanan)](#2-langkah-2-mengetahui-telegram-user-id-otorisasi--keamanan)
3. [Langkah 3: Mendapatkan API Key DeepSeek](#3-langkah-3-mendapatkan-api-key-deepseek)
4. [Langkah 4: Konfigurasi File `.env` Project](#4-langkah-4-konfigurasi-file-env-project)
5. [Langkah 5: Menjalankan Bot Telegram](#5-langkah-5-menjalankan-bot-telegram)
   - [Mode A: Pengujian Lokal (Long Polling)](#mode-a-pengujian-lokal-tanpa-ip-publik--long-polling)
   - [Mode B: Deploy di VPS Tencent (Webhook)](#mode-b-deploy-di-vps-tencent-production--webhook)
6. [Langkah 6: Contoh Perintah & Format Chatting](#6-langkah-6-contoh-perintah--format-chatting)
7. [Fitur Tombol [↩️ Batalkan / Undo]](#7-fitur-tombol-batalkan--undo)
8. [Troubleshooting & Tanya Jawab](#8-troubleshooting--tanya-jawab)

---

## 1. Langkah 1: Membuat Bot di Telegram via @BotFather

1. Buka aplikasi Telegram di HP atau Laptop Anda.
2. Di kolom pencarian, cari **`@BotFather`** (pastikan yang memiliki centang biru terverifikasi ✅) atau klik tautan [https://t.me/BotFather](https://t.me/BotFather).
3. Klik tombol **Start** atau ketik perintah:
   ```text
   /start
   ```
4. Buat bot baru dengan mengetik:
   ```text
   /newbot
   ```
5. BotFather akan meminta **Nama Tampilan Bot** (Display Name). Bebas menggunakan spasi.  
   *Contoh:*
   ```text
   Akuntan Pribadi Saya
   ```
6. Selanjutnya masukkan **Username Bot**. Username ini harus unik dan berakhiran `bot` atau `_bot`.  
   *Contoh:*
   ```text
   tm_accountant_bot
   ```
7. Jika berhasil, BotFather akan memberikan pesan ucapan selamat beserta **API Token HTTP**.  
   *Bentuk token seperti:*
   ```text
   7123456789:AAFlkjw9823hkljadskjf9238jhkasdf
   ```
   > ⚠️ **PENTING:** Simpan token ini dengan aman dan jangan dibagikan ke publik.

8. *(Opsional)* Menambahkan daftar perintah cepat di menu Telegram:
   - Ketik `/setcommands` di BotFather.
   - Pilih bot Anda.
   - Kirim teks berikut:
     ```text
     saldo - Cek saldo kas & rekening bank
     help - Panduan format pencatatan transaksi
     ```

---

## 2. Langkah 2: Mengetahui Telegram User ID (Otorisasi & Keamanan)

Untuk memastikan bot hanya merespons perintah dari Anda (pemilik) dan menolak orang asing:

1. Di kolom pencarian Telegram, cari **`@userinfobot`** atau klik [https://t.me/userinfobot](https://t.me/userinfobot).
2. Klik tombol **Start**.
3. Bot akan membalas dengan info akun Anda:
   ```text
   Id: 123456789
   First: NamaAnda
   Last: ...
   Lang: id
   ```
4. Salin angka pada baris **`Id`** (misal: `123456789`). Angka inilah Telegram User ID Anda.

---

## 3. Langkah 3: Mendapatkan API Key DeepSeek

Bot ini menggunakan AI cerdas dari DeepSeek untuk membaca bahasa natural Indonesia Anda dan mengubahnya menjadi jurnal akuntansi debit/kredit:

1. Buka browser dan kunjungi [https://platform.deepseek.com](https://platform.deepseek.com).
2. Login atau daftar akun baru.
3. Masuk ke menu **API Keys** di bilah navigasi sebelah kiri.
4. Klik **Create new API Key**, beri nama (misal: `tm-accountant`), lalu salin key yang dihasilkan (dimulai dengan `sk-...`).
5. Pastikan akun DeepSeek Anda memiliki saldo/kredit aktif.

---

## 4. Langkah 4: Konfigurasi File `.env` Project

Buka file `.env` di folder project `tm-accountant`, lalu masukkan data yang sudah Anda dapatkan pada bagian berikut:

```dotenv
# DeepSeek AI Configuration
DEEPSEEK_API_KEY=sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_TIMEOUT=30

# Telegram Bot Configuration
TELEGRAM_BOT_TOKEN=7123456789:AAFlkjw9823hkljadskjf9238jhkasdf
TELEGRAM_ALLOWED_USER_IDS=123456789
TELEGRAM_WEBHOOK_SECRET=rahasia_webhook_bebas
```

> 💡 **Multi-User ID:** Jika ingin mengizinkan lebih dari 1 akun (misal akun HP & akun Laptop pasangan), pisahkan dengan koma:  
> `TELEGRAM_ALLOWED_USER_IDS=123456789,987654321`

---

## 5. Langkah 5: Menjalankan Bot Telegram

### Mode A: Pengujian Lokal (Tanpa IP Publik / Long Polling)
Jika Anda sedang menguji di komputer lokal / laptop tanpa domain HTTPS:

1. Buka terminal di folder project:
   ```bash
   php artisan telegram:poll
   ```
2. Terminal akan menampilkan status `[POLLING] Telegram Bot is listening for updates...`.
3. Buka Telegram Anda, buka chat dengan bot Anda, dan kirim pesan `/start` atau `"beli telur 25k"`.

---

### Mode B: Deploy di VPS Tencent (Production / Webhook)
Saat project sudah di-deploy di VPS Tencent dengan domain HTTPS (misal: `https://keuangan.domainanda.com`):

1. Pastikan route webhook sudah terpasang.
2. Daftarkan URL Webhook ke server Telegram dengan menjalankan perintah cURL di terminal / browser:
   ```bash
   curl -F "url=https://keuangan.domainanda.com/api/telegram/webhook" https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook
   ```
   *Ganti `<TELEGRAM_BOT_TOKEN>` dengan token bot Anda.*

3. Telegram akan merespons:
   ```json
   {"ok": true, "result": true, "description": "Webhook was set"}
   ```
4. Bot kini langsung aktif 24/7 di server tanpa perlu menjalankan command `telegram:poll`.

---

## 6. Langkah 6: Contoh Perintah & Format Chatting

Bot didukung AI fleksibel, sehingga Anda bisa mengetik dengan bahasa santai sehari-hari:

### 🥩 1. Pengeluaran (Expense)
- `"beli telur 1 kg 25k"` $\rightarrow$ Otomatis mencatat Beban Makanan & Minuman Rp 25.000 (Kas Tunai).
- `"makan siang padang 35rb bayar pake bca"` $\rightarrow$ Mencatat Rp 35.000 dipotong dari Bank BCA.
- `"isi bensin vario 40rb gopay"` $\rightarrow$ Mencatat Beban Transportasi Rp 40.000 dari GoPay.
- `"bayar tagihan internet indihome 385.000 dari mandiri"` $\rightarrow$ Mencatat Beban Utilitas Rp 385.000 dari Bank Mandiri.
- *"beli pakan kucing whiskas 85rb"* $\rightarrow$ Otomatis membuat sub-kategori akun beban baru jika belum ada.

---

### 💵 2. Pemasukan (Income)
- `"gaji bulan ini masuk 15jt ke bca"` $\rightarrow$ Mencatat Pendapatan Gaji Rp 15.000.000 masuk ke BCA.
- `"dapat komisi freelance 1.500.000 transfer ke mandiri"` $\rightarrow$ Mencatat Pendapatan Lain-lain Rp 1.500.000 ke Mandiri.
- `"ada yang bayar hutang 500rb tunai"` $\rightarrow$ Mencatat penerimaan kas Rp 500.000.

---

### 🔄 3. Transfer Antar Rekening & E-Wallet
- `"transfer bca ke gopay 200rb"` $\rightarrow$ Mutasi saldo dari BCA ke GoPay.
- `"tarik tunai 500rb dari atm mandiri"` $\rightarrow$ Mutasi saldo dari Mandiri ke Kas Tunai (Dompet).
- `"top up shopeepay 100k dari bca"` $\rightarrow$ Mutasi saldo dari BCA ke ShopeePay.

---

### 📊 4. Tanya Ringkasan Keuangan & Saldo
- `/saldo` atau `"saldo saya berapa"` $\rightarrow$ Menampilkan ringkasan saldo seluruh rekening, kas, dan e-wallet.
- `"keuangan saya 1 minggu"` $\rightarrow$ Menampilkan total pemasukan, pengeluaran, dan surplus/defisit 7 hari terakhir.
- `"pengeluaran bulan ini berapa"` $\rightarrow$ Menampilkan ringkasan beban bulan berjalan.

---

## 7. Fitur Tombol [↩️ Batalkan / Undo]

Setiap kali bot berhasil mencatat transaksi, bot akan membalas dengan rincian transaksi lengkap dan sebuah tombol inline keyboard:

```text
✅ PENGELUARAN BERHASIL DICATAT

📝 Keterangan: Beli telur 1 kg
💰 Nominal: Rp 25.000
📁 Akun Beban: [6-10001] Makanan & Minuman
💳 Sumber Dana: [1-10001] Kas Tunai (Dompet)
📅 Tanggal: 24 Aug 2026
🔖 No. Jurnal: JE-202608-0001

[ ↩️ Batalkan / Undo ]
```

- Jika Anda salah ketik atau salah input nominal, cukup tekan tombol **`[ ↩️ Batalkan / Undo ]`**.
- Sistem akan langsung membatalkan jurnal tersebut, mengembalikan saldo akun ke posisi semula, dan memperbarui pesan menjadi:
  ```text
  🚫 TRANSAKSI DIBATALKAN: Jurnal JE-202608-0001 (Rp 25.000) telah dihapus dari pembukuan.
  ```

---

## 8. Troubleshooting & Tanya Jawab

### Q: Bot tidak merespons sama sekali saat di-chat?
- **Mode Lokal**: Pastikan `php artisan telegram:poll` sedang berjalan di terminal Anda.
- **Mode Webhook**: Pastikan URL Webhook HTTPS Anda valid dan dapat diakses publik. Cek info webhook dengan membuka URL:  
  `https://api.telegram.org/bot<TOKEN>/getWebhookInfo` di browser.
- **Whitelist ID**: Pastikan `TELEGRAM_ALLOWED_USER_IDS` di `.env` sudah sesuai dengan ID dari `@userinfobot`.

### Q: Muncul balasan "⛔ Akses Ditolak"?
- Akun Telegram yang Anda gunakan belum didaftarkan di `TELEGRAM_ALLOWED_USER_IDS`. Tambahkan ID Anda lalu simpan `.env`.

### Q: Balasan transaksi lambat?
- DeepSeek memproses kalimat dalam $\sim 1\text{--}2$ detik. Pastikan koneksi internet server stabil dan kuota DeepSeek API Anda tidak habis.
