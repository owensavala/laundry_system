# Sistem Manajemen Laundry Terintegrasi (Multi-Role Web App)

Aplikasi berbasis web modern untuk digitalisasi operasional bisnis laundry. Sistem ini menangani pencatatan pesanan kasir (POS), pelacakan status cucian secara real-time oleh pelanggan tanpa perlu login, manajemen harga layanan, hingga rekapitulasi laporan pendapatan dan laba bersih otomatis untuk pemilik usaha.

---

## 🚀 Fitur Utama Berdasarkan Peran (Roles)

### 1. Level 1: Superadmin (Pemilik / Owner)
- **Dashboard Business Intelligence (BI)**: Grafik pendapatan harian & bulanan (Chart.js), total omzet, laba bersih (Omzet dikurangi Pengeluaran), dan performa kasir.
- **SLA & Turnaround Time**: Pemantauan rata-rata kecepatan penyelesaian cucian per hari.
- **Master Data Layanan & Harga**: CRUD jenis layanan (Kiloan, Satuan, Cuci Kering, Setrika, Karpet) beserta harga dan estimasi waktu pengerjaan.
- **Manajemen Karyawan**: Kelola akun staf/kasir dan hak akses.
- **Data Pelanggan (CRM)**: Analisis Customer Lifetime Value (LTV), total frekuensi order, dan status pelanggan setia.
- **Laporan Keuangan & Ekspor**: Filter rentang tanggal dengan preset cepat (Hari Ini, 7 Hari, Bulan Ini, Tahun Ini), cetak laporan kas, serta ekspor langsung ke format **Excel (.xls)** dan **CSV**.
- **Pengaturan Bisnis & WhatsApp Gateway**: Pengaturan identitas laundry, alamat, nomor telepon, logo, footer struk, serta integrasi WhatsApp Gateway (Fonnte, Wablas, atau Mode Manual).

### 2. Level 2: Admin (Pekerja / Kasir / Operator)
- **Point of Sale (POS) Modern**:
  - Pendaftaran pelanggan baru atau pencarian instan pelanggan lama berdasarkan nama / nomor HP.
  - Keranjang dinamis multi-layanan (Kiloan & Satuan).
  - Kalkulator diskon otomatis, kalkulator nominal uang tunai & kembalian live.
  - **Keyboard Shortcuts**: `F2` (Fokus Pelanggan), `F4` (Tambah Layanan), `F8` (Fokus Pembayaran), `Ctrl+Enter` (Simpan Pesanan).
- **Cetak Nota & Struk Thermal**: Format standar 58mm & 80mm dengan QR Code verifikasi unik.
- **Notifikasi WhatsApp Otomatis**: Kirim nota digital saat pesanan masuk dan notifikasi saat pakaian siap diambil.
- **Manajemen Antrean & Status**: Pembaruan status bertahap (Menunggu $\rightarrow$ Dicuci $\rightarrow$ Disetrika $\rightarrow$ Selesai $\rightarrow$ Sudah Diambil).
- **Manajemen Pembayaran**: Pencatatan metode pembayaran (Tunai, Transfer Bank, QRIS) dan status lunas / belum lunas.
- **Permintaan Penjemputan (Pickup)**: Konfirmasi dan konversi langsung permintaan jemput pakaian menjadi pesanan POS.
- **Pencatatan Biaya Operasional**: Input pengeluaran harian (deterjen, listrik, plastik, dll.).

### 3. Level 3: Pelanggan (Customer / Publik)
- **Pelacakan Status Real-time (Tanpa Perlu Login)**:
  - Input Kode Transaksi (misal: `LND-20261120-001`) untuk melihat status cucian terkini.
  - **Kamera QR Code Scanner**: Scan langsung barcode/QR dari struk fisik menggunakan kamera HP / laptop.
  - **Riwayat Pelacakan Tersimpan**: Quick access ke nota yang baru saja dicari menggunakan local storage.
  - **Tautan WhatsApp & Salin Tautan**: Bagikan tautan tracking langsung ke WhatsApp keluarga / teman.
- **Katalog Layanan & Tarif**: Informasi lengkap jenis layanan, estimasi durasi waktu, dan harga transparan.
- **Formulir Permintaan Jemput (Pickup Request)**: Form penjemputan pakaian kotor langsung ke alamat pelanggan dengan tombol konfirmasi WhatsApp otomatis ke admin.

### 4. 📖 Buku Panduan Terintegrasi (Help Center)
Tersedia menu **Panduan** di navbar publik dan sidebar sistem (`panduan.php`) yang merangkum tutorial langkah-demi-langkah, tips operasional, shortcut keyboard POS, dan FAQ interaktif untuk Pelanggan, Kasir, dan Pemilik.

---

## 🛠️ Tech Stack
- **Backend**: Native PHP 8.x (Ringan, cepat, tanpa dependensi framework rumit)
- **Database**: MySQL / MariaDB (Relasional dengan skema terindeks optimal)
- **Frontend**: Tailwind CSS 3.x, Alpine.js, FontAwesome 6, Chart.js
- **Scanner**: Html5-QRCode Camera Scanner
- **Integrasi**: Fonnte API / Wablas WhatsApp Gateway API
- **Cloud Ready**: Vercel Serverless Functions (`vercel-php`)

---

## 🔑 Akun Default Sistem

| Role | Username / Email | Password | Akses URL |
| :--- | :--- | :--- | :--- |
| **Superadmin (Owner)** | `admin` / `admin@gmail.com` | `admin123` | `/auth/login.php` $\rightarrow$ `/superadmin/` |
| **Admin (Kasir)** | `kasir` / `kasir@gmail.com` | `kasir123` | `/auth/login.php` $\rightarrow$ `/admin/` |
| **Pelanggan** | *Tanpa Login* | *Tanpa Password* | `/index.php` (Pelacakan & Pickup) |

---

## 💻 Cara Menjalankan di Lokal (XAMPP / PHP)

1. **Clone Repository**:
   ```bash
   git clone https://github.com/owensavala/laundry_system.git
   cd laundry_system
   ```

2. **Setup Database**:
   - Buka **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Buat database baru bernama `db_laundry`.
   - Import file `database.sql` ke dalam database `db_laundry`.
   *(Catatan: Sistem juga memiliki fitur auto-migration otomatis jika tabel belum lengkap).*

3. **Jalankan Web Server**:
   - Letakkan folder di dalam `c:/xampp/htdocs/laundry_system` dan akses melalui browser di `http://localhost/laundry_system/`
   - Atau jalankan PHP built-in server:
     ```bash
     php -S localhost:8000
     ```
     dan buka `http://localhost:8000/`.

---

## ☁️ Deployment ke Vercel

Aplikasi ini sudah dilengkapi dengan konfigurasi `vercel.json` menggunakan runtime serverless PHP (`vercel-php@0.9.0`).

### Langkah Deploy:
1. Hubungkan repository GitHub ini di [Vercel Dashboard](https://vercel.com/new).
2. Tambahkan **Environment Variables** database MySQL cloud (misal dari TiDB Cloud, Aiven, PlanetScale, atau Railway) di menu **Project Settings $\rightarrow$ Environment Variables**:
   - `DB_HOST` : `nama-host-mysql.com`
   - `DB_PORT` : `3306`
   - `DB_USER` : `username_database`
   - `DB_PASS` : `password_database`
   - `DB_NAME` : `db_laundry`
3. Klik **Deploy**. Aplikasi akan live dan dapat diakses publik dengan HTTPS otomatis.

---

## 📄 Lisensi
Hak Cipta &copy; 2026. Dikembangkan untuk efisiensi operasional dan transparansi bisnis laundry modern.
