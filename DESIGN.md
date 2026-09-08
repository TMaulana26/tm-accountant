---
name: TM Accountant
description: Intelligent personal accounting dashboard with AI assistant, multi-wallet tracking, and double-entry rigor
colors:
  primary: "#7c3aed"
  primary-hover: "#6d28d9"
  primary-active: "#5b21b6"
  primary-light: "#ede9fe"
  primary-dark: "#4c1d95"
  secondary: "#10b981"
  secondary-hover: "#059669"
  secondary-light: "#ecfdf5"
  secondary-dark: "#064e3b"
  trinary: "#0f172a"
  trinary-surface: "#1e293b"
  trinary-dark: "#020617"
  accent: "#ffffff"
  accent-muted: "#f8fafc"
  neutral-bg: "#f8fafc"
  neutral-surface: "#ffffff"
  neutral-card: "#ffffff"
  neutral-border: "#e2e8f0"
  neutral-text: "#0f172a"
  neutral-muted: "#64748b"
  dark-bg: "#090d16"
  dark-surface: "#0f172a"
  dark-card: "#182234"
  dark-border: "#1e293b"
  dark-text: "#f8fafc"
  dark-muted: "#94a3b8"
  danger: "#f43f5e"
  warning: "#f59e0b"
typography:
  display:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.75rem, 3vw, 2.5rem)"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.02em"
  title:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: "-0.01em"
  body:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "-0.01em"
rounded:
  xs: "4px"
  sm: "6px"
  md: "10px"
  lg: "14px"
  xl: "18px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.accent}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.accent}"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.accent}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  card-wallet:
    backgroundColor: "{colors.neutral-surface}"
    rounded: "{rounded.lg}"
    padding: "18px"
---

# Design System: TM Accountant

## Overview

TM Accountant mengusung bahasa visual **Modern Intelligent Accounting**. Antarmuka dirancang untuk memadukan ketenangan psikologis finansial dengan ketelitian sistem buku besar ganda (*double-entry bookkeeping*). Palet warna didasarkan pada komitmen identitas yang solid:
- **Primary Violet** (`#7c3aed`): Warna identitas utama untuk tombol aksi prioritas, menu navigasi aktif, brand header, dan interaksi primer.
- **Secondary Emerald** (`#10b981`): Warna penanda pertumbuhan kas positif, saldo aktif, status sukses, indikator realtime WebSocket, dan badge verifikasi.
- **Trinary Biru Navy** (`#0f172a` / `#1e293b`): Fondasi struktural untuk kontainer data, sidebar navigasi, header tabel, dan canvas mode gelap yang tenang serta minim silau.
- **Accent Putih** (`#ffffff`): Kunci kontras tinggi pada teks aksi, ikon, badge kontras, dan pembatas visual yang jernih di kedua mode pencahayaan.

## Colors

### Hierarchy & Strategic Roles
- **Primary Violet Palette**:
  - `primary-50` (`#ede9fe`): Background badge aktif, highlight baris tabel.
  - `primary-500` (`#8b5cf6`): Aksen garis fokus, state hover interaktif.
  - `primary-600` (`#7c3aed`): Warna tombol utama (*CTA*), radio/checkbox aktif, ikon aktif.
  - `primary-700` (`#6d28d9`): Tombol state hover & active.
  - `primary-900` (`#4c1d95`): Background kontras tinggi pada mode gelap.
- **Secondary Emerald Palette**:
  - `emerald-500` (`#10b981`): Mutasi kas masuk, total likuiditas bertambah, indikator status online.
  - `emerald-600` (`#059669`): Teks angka surplus laba bersih, badge nominal positif.
  - `emerald-950/40`: Background card indikator saldo sehat pada Dark Mode.
- **Trinary Navy Palette**:
  - `navy-900` (`#0f172a`): Latar belakang utama pada Dark Mode dan panel header.
  - `navy-800` (`#1e293b`): Latar belakang kartu dashboard, popup modal, dan baris tabel gelap.
  - `navy-950` (`#020617`): Warna canvas terdalam untuk menciptakan efek kedalaman layer.
- **Accent White**:
  - Digunakan untuk teks di atas background Violet dan Navy, garis batas halus (`border-white/10`), serta surface putih bersih di Light Mode.

## Typography

### Font Family
- **UI & Display**: `Instrument Sans` didukung sistem font fallback native (`ui-sans-serif, system-ui, sans-serif`). Huruf proporsional modern dengan keterbacaan tajam pada angka maupun label teknis.
- **Monospace (Data Finansial & Kode Akun)**: `ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas` untuk seluruh representasi nominal mata uang Rupiah (`Rp 1.250.000`), kode akun (`1-10001`), dan nomor entri jurnal.

### Scale & Weight
- **Display / Hero**: 28px - 36px, Weight 800 (Extra Bold), tracking tight (`-0.025em`).
- **Page Headings**: 20px - 24px, Weight 700 (Bold).
- **Card Titles & Section Labels**: 14px - 16px, Weight 600 (Semi Bold).
- **Body & Forms**: 13px - 14px, Weight 400 (Regular) / 500 (Medium).
- **Financial Figures**: 14px - 18px, Weight 700 (Bold) Monospace, tabular figures aligned.

## Layout

- **Sidebar + Main Canvas Grid**: Sidebar kiri berbasis Navy gelap (`#0f172a`), memuat logo brand TM Accountant bergradasi Violet-Navy dan navigasi hierarkis.
- **Dashboard Grid**:
  - Row 1: Pinned Wallets Grid (Kartu Dompet Favorit dengan status saldo realtime).
  - Row 2: Ringkasan Likuiditas & Grafik Arus Kas (Laba Rugi & Breakdown Saldo).
  - Row 3: Riwayat Jurnal Transaksi Terakhir dengan filter cepat.
- **Responsive Breakpoints**:
  - Mobile (< 640px): 1 kolom kartu, navigasi drawer hamburger, tombol aksi full-width.
  - Tablet (640px - 1024px): 2 kolom kartu dompet, tabel dengan horizontal scroll indicator.
  - Desktop (> 1024px): 3 hingga 4 kolom kartu dompet, dasbor analitik berdampingan.

## Elevation & Depth

- **Light Mode**:
  - `Elevation-1` (Card): `box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05)`, border `border-gray-200/80`.
  - `Elevation-2` (Hover Card / Dropdown): `box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.08)`, border `border-violet-500/30`.
  - `Elevation-3` (Modal Dialog): `box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1)`.
- **Dark Mode**:
  - Mengandalkan **Tonal Layering** (lapisan warna Navy yang bertingkat):
    - Level 0 (Canvas): `#090d16`
    - Level 1 (Card/Container): `#0f172a` dengan border halus `rgba(255, 255, 255, 0.08)`
    - Level 2 (Elevated Card / Modal): `#1e293b` dengan border `rgba(124, 58, 237, 0.2)`
    - Level 3 (Glow & Active): Soft violet ambient glow `rgba(124, 58, 237, 0.15)`.

## Shapes

- **Radius Scale**:
  - Buttons & Inputs: `rounded-xl` (12px) untuk sentuhan modern yang bersahabat namun presisi.
  - Cards & Widgets: `rounded-2xl` (16px) untuk membingkai data finansial secara rapi.
  - Badges & Pills: `rounded-full` (9999px) untuk status live, tipe akun, dan kategori.
  - Modal & Panels: `rounded-2xl` atau `rounded-3xl` (20px - 24px).

## Components

### 1. Primary Action Button
- Background: Violet `#7c3aed` dengan hover `#6d28d9`.
- Teks: Putih Murni `#ffffff`, Weight 600.
- Padding: 10px 18px, Radius `rounded-xl`.
- Focus Ring: `ring-2 ring-violet-500/40 ring-offset-2`.

### 2. Secondary Growth / Success Badge
- Background: Emerald soft `bg-emerald-500/10` (Dark: `bg-emerald-400/10`).
- Teks: Emerald `#047857` (Dark: `#34d399`), font-mono weight 700.
- Border: `ring-1 ring-inset ring-emerald-500/20`.

### 3. Pinned Wallet Card
- Surface: Putih (`#ffffff`) pada Light Mode, Deep Navy (`#0f172a`) pada Dark Mode.
- Border: `border-slate-200` (Light) / `border-slate-800` (Dark).
- Hover: Transisi halus ke border `violet-500/50` dengan subtle glow.
- Sisa Saldo: Teks tebal monospace dengan warna Emerald jika positif, Rose jika negatif.

### 4. Financial Table Row
- Alternating subtle striping: `hover:bg-violet-50/40` pada Light Mode, `hover:bg-slate-800/50` pada Dark Mode.
- Nominal kolom Debit/Kredit rata kanan dengan font monospace sejajar vertikal.

## Do's and Don'ts

### Do:
- **Gunakan Violet untuk aksi primer dan identitas brand utama**, bukan hijau atau biru umum.
- **Gunakan Emerald secara disiplin hanya untuk konotasi positif/pertumbuhan kas**, saldo aset, dan indikator aktif.
- **Gunakan Biru Navy sebagai fondasi canvas gelap dan struktur panel**, menghindari warna hitam pekat `#000000` yang membuat mata cepat lelah.
- **Selalu pastikan angka moneter menggunakan font monospace** agar posisi digit titik desimal dan pemisah ribuan sejajar rapi saat diaudit.

### Don't:
- **Jangan mencampur warna primer baru** (seperti oranye atau merah) untuk tombol utama aplikasi.
- **Jangan gunakan teks abu-abu terang di atas background putih** yang melanggar rasio kontras WCAG AA (< 4.5:1).
- **Jangan gunakan border tebal atau bayangan hitam kasar**; gunakan border tipis bernuansa Navy/Slate dengan opacity lembut.
- **Jangan hilangkan indikator live WebSocket** pada widget saldo dompet saat memodifikasi tampilan.
