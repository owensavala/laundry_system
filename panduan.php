<?php
// panduan.php - Interactive User & Staff Manual / Help Center for All Roles
require_once 'config/database.php';

$set = get_settings($conn);
$active_role = $_GET['role'] ?? 'customer';
if (!in_array($active_role, ['customer', 'kasir', 'owner'])) {
    $active_role = 'customer';
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Panduan Penggunaan - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-indigo-500 selection:text-white" x-data="{ currentRole: '<?= $active_role; ?>', searchFaq: '' }">

    <!-- Top Navigation Bar -->
    <nav class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-slate-100 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3.5 flex justify-between items-center gap-3">
            <a href="index.php" class="flex items-center gap-2.5 font-black text-lg text-indigo-600 truncate">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200 shrink-0">
                    <i class="fas fa-soap"></i>
                </span>
                <span class="truncate"><?= htmlspecialchars($set['nama_laundry']); ?></span>
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                <a href="index.php" class="text-xs font-bold text-slate-600 hover:text-indigo-600 px-3 py-2 rounded-xl transition flex items-center gap-1.5">
                    <i class="fas fa-arrow-left text-[11px]"></i>
                    <span class="hidden sm:inline">Halaman Utama</span>
                </a>
                <a href="auth/login.php" class="text-xs font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-indigo-200">
                    <i class="fas fa-lock text-[10px]"></i>
                    <span>Portal Staf</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Header Hero Banner -->
    <div class="bg-gradient-to-b from-indigo-950 via-indigo-900 to-indigo-950 text-white py-12 sm:py-16 px-4 sm:px-6 relative overflow-hidden">
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-indigo-700/60 border border-indigo-400/40 text-indigo-200 text-xs font-extrabold uppercase tracking-wider mb-4 backdrop-blur-md">
                <i class="fas fa-book-open text-amber-400"></i> Pusat Panduan & Dokumentasi Resmi
            </span>
            <h1 class="text-2xl sm:text-4xl md:text-5xl font-black tracking-tight leading-tight mb-3">
                Panduan Penggunaan Sistem Laundry
            </h1>
            <p class="text-indigo-200 text-xs sm:text-base max-w-2xl mx-auto font-medium">
                Pilih peran Anda di bawah ini untuk melihat panduan langkah demi langkah yang disesuaikan untuk Pelanggan, Kasir, atau Pemilik Usaha.
            </p>

            <!-- Role Selector Tabs -->
            <div class="mt-8 flex flex-wrap justify-center gap-2 p-1.5 bg-white/10 backdrop-blur-md rounded-2xl max-w-xl mx-auto border border-white/20">
                <button type="button" 
                        @click="currentRole = 'customer'"
                        :class="currentRole === 'customer' ? 'bg-indigo-600 text-white shadow-lg' : 'text-indigo-200 hover:text-white hover:bg-white/10'"
                        class="flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-black transition flex items-center justify-center gap-2">
                    <i class="fas fa-user"></i>
                    <span>Pelanggan</span>
                </button>
                <button type="button" 
                        @click="currentRole = 'kasir'"
                        :class="currentRole === 'kasir' ? 'bg-indigo-600 text-white shadow-lg' : 'text-indigo-200 hover:text-white hover:bg-white/10'"
                        class="flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-black transition flex items-center justify-center gap-2">
                    <i class="fas fa-cash-register"></i>
                    <span>Kasir / Staf</span>
                </button>
                <button type="button" 
                        @click="currentRole = 'owner'"
                        :class="currentRole === 'owner' ? 'bg-indigo-600 text-white shadow-lg' : 'text-indigo-200 hover:text-white hover:bg-white/10'"
                        class="flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-black transition flex items-center justify-center gap-2">
                    <i class="fas fa-crown"></i>
                    <span>Pemilik (Owner)</span>
                </button>
            </div>
        </div>

        <!-- Decorative lights -->
        <div class="absolute -top-24 -left-24 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Main Content Container -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 sm:py-14">

        <!-- ========================================== -->
        <!-- 1. PANDUAN PELANGGAN / CUSTOMER -->
        <!-- ========================================== -->
        <div x-show="currentRole === 'customer'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
            
            <div class="border-b border-slate-200 pb-4">
                <span class="text-indigo-600 font-extrabold text-xs uppercase tracking-wider block">Level 3: Pelanggan (Customer)</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">Panduan Pengguna untuk Pelanggan</h2>
                <p class="text-slate-500 text-sm mt-1">Tanpa perlu login atau mendaftar akun, pelanggan dapat langsung melacak progres cucian dan memesan layanan antar jemput pakaian.</p>
            </div>

            <!-- Steps Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Step 1 -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-search"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-indigo-600 tracking-wider">Langkah 1</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Melacak Status Cucian (Tracking)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Buka halaman utama web. Pada kolom pencarian, ketikkan <strong>Nomor Nota Transaksi</strong> Anda (contoh: <code class="bg-slate-100 text-indigo-600 px-1.5 py-0.5 rounded font-mono font-bold">LND-20261120-001</code>) lalu tekan tombol <strong>Lacak</strong>.
                        </p>
                        <div class="mt-4 p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1 border border-slate-100">
                            <p><strong><i class="fas fa-qrcode text-indigo-500 mr-1"></i> Scan QR Struk Kamera:</strong> Jika Anda memegang struk fisik thermal, cukup klik tombol <em>Scan QR Struk Kamera</em> dan arahkan smartphone Anda ke kode QR yang tercetak di kertas nota.</p>
                            <p><strong><i class="fas fa-history text-indigo-500 mr-1"></i> Pencarian Terakhir:</strong> Sistem otomatis mengingat kode transaksi yang pernah Anda cek di tombol chip bawah kolom input.</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-route"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-emerald-600 tracking-wider">Langkah 2</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Memahami 5 Milestone Progres</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Setiap tahapan proses pakaian Anda ditampilkan secara transparan lengkap dengan stempel jam penyelesaian:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-600">
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center text-[10px] font-black">1</span>
                                <span><strong>Menunggu:</strong> Pakaian telah diterima kasir di outlet.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-black">2</span>
                                <span><strong>Dicuci:</strong> Sedang dicuci bersih higienis & dikeringkan.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-[10px] font-black">3</span>
                                <span><strong>Disetrika:</strong> Sedang disetrika rapi & dipacking plastik.</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-black">4</span>
                                <span><strong>Selesai:</strong> Cucian sudah wangi & siap diambil di toko!</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-black">5</span>
                                <span><strong>Diambil:</strong> Pakaian telah diambil oleh pelanggan.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-truck-pickup"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-amber-600 tracking-wider">Langkah 3</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Minta Jemput Cucian (Pickup)</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Pakaian kotor menumpuk dan tidak sempat ke outlet? Cukup gunakan formulir online penjemputan:
                        </p>
                        <ol class="mt-3 space-y-1.5 text-xs text-slate-600 list-decimal list-inside">
                            <li>Buka bagian <strong>Minta Jemput Cucian</strong> di halaman utama.</li>
                            <li>Ketikkan <strong>Nama Lengkap</strong>, <strong>No. WhatsApp</strong>, dan <strong>Alamat Penjemputan</strong>.</li>
                            <li>(Opsional) Berikan catatan waktu jemput (misal: "Jemput jam 3 sore").</li>
                            <li>Tekan tombol <strong>Kirim Permintaan Penjemputan</strong>.</li>
                            <li>Klik tombol hijau <em>Kirim Konfirmasi ke WhatsApp Toko</em> untuk langsung mengabari admin kurir.</li>
                        </ol>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-md transition">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-teal-600 tracking-wider">Langkah 4</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Interaksi & Notifikasi WhatsApp</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Sistem terintegrasi penuh dengan WhatsApp untuk kemudahan Anda:
                        </p>
                        <div class="mt-3 space-y-2 text-xs text-slate-600">
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-2">
                                <i class="fas fa-comment-dots text-emerald-600 mt-0.5"></i>
                                <span><strong>Tanya Kasir:</strong> Tombol interaktif untuk langsung menyapa kasir jika ada pertanyaan atau instruksi khusus perihal pakaian.</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-2">
                                <i class="fas fa-share-nodes text-indigo-600 mt-0.5"></i>
                                <span><strong>Bagikan ke WA:</strong> Kirim tautan pelacakan langsung ke pasangan atau anggota keluarga hanya dengan satu ketukan.</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-2">
                                <i class="fas fa-link text-slate-600 mt-0.5"></i>
                                <span><strong>Salin Tautan:</strong> Menyimpan URL pelacakan ke papan klip untuk dibuka kapan pun.</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Quick Action Box -->
            <div class="p-6 rounded-3xl bg-indigo-50 border border-indigo-100 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
                <div>
                    <h4 class="font-extrabold text-indigo-950 text-base">Siap Mencoba Pelacakan Cucian Anda?</h4>
                    <p class="text-xs text-slate-600 mt-0.5">Kunjungi halaman utama untuk mencari status pesanan atau meminta kurir jemput cucian.</p>
                </div>
                <a href="index.php" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition whitespace-nowrap">
                    Buka Halaman Pelacakan &rarr;
                </a>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- 2. PANDUAN KASIR / STAF (ADMIN POS) -->
        <!-- ========================================== -->
        <div x-show="currentRole === 'kasir'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
            
            <div class="border-b border-slate-200 pb-4">
                <span class="text-indigo-600 font-extrabold text-xs uppercase tracking-wider block">Level 2: Admin (Pekerja / Kasir / Operator)</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">Panduan Operasional Kasir & POS</h2>
                <p class="text-slate-500 text-sm mt-1">Panduan lengkap input pesanan POS, kalkulator kembalian, cetak struk thermal, pergerakan antrean cuci, dan penjemputan.</p>
            </div>

            <!-- Speed Shortcuts Banner -->
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-5 sm:p-6 rounded-3xl shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[11px] font-bold uppercase mb-1">
                        <i class="fas fa-keyboard text-amber-400"></i> Keyboard Shortcuts Kasir
                    </span>
                    <h3 class="text-lg font-black text-white">Bekerja Lebih Cepat dengan Tombol Pintas</h3>
                    <p class="text-xs text-slate-300 mt-0.5">Kasir dapat menyelesaikan transaksi tanpa menyentuh mouse:</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="bg-white/10 px-3 py-1.5 rounded-xl border border-white/20"><kbd class="bg-white text-slate-900 px-1.5 py-0.5 rounded font-mono font-bold text-[11px]">F2</kbd> Cari Pelanggan</span>
                    <span class="bg-white/10 px-3 py-1.5 rounded-xl border border-white/20"><kbd class="bg-white text-slate-900 px-1.5 py-0.5 rounded font-mono font-bold text-[11px]">F4</kbd> Status Bayar</span>
                    <span class="bg-white/10 px-3 py-1.5 rounded-xl border border-white/20"><kbd class="bg-white text-slate-900 px-1.5 py-0.5 rounded font-mono font-bold text-[11px]">F8</kbd> Uang Tunai</span>
                    <span class="bg-white/10 px-3 py-1.5 rounded-xl border border-white/20"><kbd class="bg-white text-slate-900 px-1.5 py-0.5 rounded font-mono font-bold text-[11px]">Ctrl+Enter</kbd> Simpan</span>
                </div>
            </div>

            <!-- Flow Cards -->
            <div class="space-y-6">

                <!-- Modul 1: POS -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider">Modul 1</span>
                            <h3 class="text-lg font-extrabold text-slate-900">Alur Input Transaksi di Kasir POS</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600">
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                            <h4 class="font-bold text-slate-800 text-sm">1. Data Pelanggan</h4>
                            <p>• Ketik nama atau nomor HP di kolom pencarian untuk pelanggan lama (autocomplete otomatis).</p>
                            <p>• Atau klik tombol <strong>Pelanggan Baru (+)</strong> untuk mendaftarkan kontak & alamat baru secara instan.</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                            <h4 class="font-bold text-slate-800 text-sm">2. Pilih Layanan & Timbang</h4>
                            <p>• Gunakan filter kategori <em>Kiloan</em> atau <em>Satuan</em>.</p>
                            <p>• Masukkan berat timbangan (kg) atau kuantitas potong (pcs). Subtotal dan estimasi jam pengerjaan terhitung otomatis.</p>
                            <p>• Masukkan nominal diskon / voucher jika ada promo toko.</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                            <h4 class="font-bold text-slate-800 text-sm">3. Pembayaran & Kembalian</h4>
                            <p>• Pilih status: <strong>Lunas</strong> atau <strong>Belum Lunas</strong> (bayar nanti saat ambil).</p>
                            <p>• Jika Tunai, ketik nominal uang diterima atau klik tombol cepat (<em>Uang Pas, 20rb, 50rb, 100rb</em>) untuk menghitung kembalian.</p>
                            <p>• Klik <strong>Simpan & Buat Nota</strong>.</p>
                        </div>
                    </div>
                </div>

                <!-- Modul 2: Cetak Struk -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                            <i class="fas fa-print"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase text-emerald-600 tracking-wider">Modul 2</span>
                            <h3 class="text-lg font-extrabold text-slate-900">Mencetak Struk Thermal & Kirim WA</h3>
                        </div>
                    </div>

                    <div class="text-xs text-slate-600 space-y-2">
                        <p>Setelah pesanan disimpan, kasir dapat langsung membuka halaman struk:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <strong>Pilihan Lebar Kertas (80mm & 58mm):</strong> Struk mendukung printer thermal USB/Bluetooth dengan tombol toggle ukuran agar hasil cetak tidak terpotong.
                            </div>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-100">
                                <strong>QR Code Tracking Cepat:</strong> Setiap struk mencetak QR code langsung ke status cucian pelanggan tanpa harus mengetik kode nota secara manual.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modul 3: Antrean & Status -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase text-purple-600 tracking-wider">Modul 3</span>
                            <h3 class="text-lg font-extrabold text-slate-900">Pergerakan Status Cucian & Pelunasan</h3>
                        </div>
                    </div>

                    <div class="text-xs text-slate-600 space-y-2 leading-relaxed">
                        <p>Buka menu <strong>Status & Antrean Cucian</strong> (`admin/pesanan.php`) untuk memperbarui status sesuai operasional bengkel cucian:</p>
                        <div class="flex flex-wrap items-center gap-2 py-2">
                            <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg font-bold">1. Menunggu</span>
                            <i class="fas fa-arrow-right text-slate-400"></i>
                            <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-lg font-bold">2. Sedang Dicuci</span>
                            <i class="fas fa-arrow-right text-slate-400"></i>
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-lg font-bold">3. Sedang Disetrika</span>
                            <i class="fas fa-arrow-right text-slate-400"></i>
                            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-lg font-bold">4. Selesai (Siap Diambil)</span>
                            <i class="fas fa-arrow-right text-slate-400"></i>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-lg font-bold">5. Sudah Diambil</span>
                        </div>
                        <p class="text-slate-500">• Saat status diubah ke <strong>Selesai</strong>, sistem memunculkan konfirmasi pengiriman WhatsApp otomatis agar pelanggan segera mengambil pakaian.</p>
                        <p class="text-slate-500">• Jika pakaian berstatus <em>Belum Lunas</em>, klik tombol <strong>Bayar Lunas</strong> pada nota saat pelanggan datang mengambil pakaian.</p>
                    </div>
                </div>

                <!-- Modul 4: Pickup & Pengeluaran -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
                            <i class="fas fa-truck-pickup"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase text-amber-600 tracking-wider">Modul 4</span>
                            <h3 class="text-lg font-extrabold text-slate-900">Permintaan Pickup & Catat Biaya Operasional</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-600">
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                            <h4 class="font-bold text-slate-800 mb-1">Permintaan Pickup (`admin/pickup.php`)</h4>
                            <p>Periksa daftar pesanan jemput dari web publik. Konfirmasi status ke <em>Dikonfirmasi</em> atau <em>Dijemput</em>, lalu klik <strong>Input POS</strong> untuk langsung mengisi data pelanggan tanpa mengetik ulang.</p>
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                            <h4 class="font-bold text-slate-800 mb-1">Biaya Toko (`admin/pengeluaran.php`)</h4>
                            <p>Catat setiap pengeluaran kasir harian (seperti pembelian deterjen, pewangi, plastik packing, token listrik) agar rekapitulasi laba bersih di dashboard owner tetap akurat.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Quick Action Box -->
            <div class="p-6 rounded-3xl bg-indigo-50 border border-indigo-100 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
                <div>
                    <h4 class="font-extrabold text-indigo-950 text-base">Masuk ke Portal Kasir Sekarang</h4>
                    <p class="text-xs text-slate-600 mt-0.5">Buka kasir POS atau pantau antrean cucian hari ini.</p>
                </div>
                <div class="flex gap-2">
                    <a href="admin/pos.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition">
                        Buka POS Kasir &rarr;
                    </a>
                    <a href="admin/pesanan.php" class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold text-xs rounded-xl transition">
                        Daftar Antrean
                    </a>
                </div>
            </div>

        </div>

        <!-- ========================================== -->
        <!-- 3. PANDUAN PEMILIK / SUPERADMIN (OWNER) -->
        <!-- ========================================== -->
        <div x-show="currentRole === 'owner'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">
            
            <div class="border-b border-slate-200 pb-4">
                <span class="text-indigo-600 font-extrabold text-xs uppercase tracking-wider block">Level 1: Superadmin (Pemilik / Owner)</span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">Panduan Eksekutif Pemilik Usaha</h2>
                <p class="text-slate-500 text-sm mt-1">Fokus pada Business Intelligence, laporan laba bersih, ekspor pembukuan, master tarif, tim karyawan, dan integrasi WhatsApp.</p>
            </div>

            <!-- Owner Capabilities Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- 1. Dashboard Analytics -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-indigo-600 tracking-wider">Modul Eksekutif 1</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Dashboard Analytics & Keuangan</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Pemilik usaha dapat memantau denyut bisnis dari rumah tanpa harus datang ke outlet fisik:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-600">
                            <li><strong>• Laba Bersih Otomatis:</strong> Menghitung total uang masuk riil dikurangi pengeluaran operasional toko bulan berjalan.</li>
                            <li><strong>• Grafik Tren Omset 7 Hari:</strong> Visualisasi performa pendapatan harian transaksi lunas via Chart.js.</li>
                            <li><strong>• Top 5 Layanan Terlaris:</strong> Mengetahui jenis layanan yang menyumbang pemasukan terbesar (misal: Cuci Kiloan vs Bed Cover).</li>
                            <li><strong>• Donat Metode Bayar:</strong> Persentase pembayaran Tunai vs QRIS vs Transfer Bank.</li>
                            <li><strong>• SLA Durasi Turnaround:</strong> Rata-rata jam pengerjaan dari pakaian masuk hingga siap diambil.</li>
                            <li><strong>• Performa Kasir:</strong> Leaderboard produktivitas staf berdasarkan jumlah transaksi dan omset.</li>
                        </ul>
                    </div>
                </div>

                <!-- 2. Laporan & Ekspor -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-emerald-600 tracking-wider">Modul Eksekutif 2</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Rekap Laporan Keuangan & Ekspor</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Pembukuan rapi yang siap dipertanggungjawabkan atau dilaporkan ke perpajakan:
                        </p>
                        <div class="mt-3 space-y-2 text-xs text-slate-600">
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <strong>Tombol Preset Cepat 1-Klik:</strong> Filter instan rentang waktu: <em>Hari Ini, 7 Hari Terakhir, Bulan Ini, Bulan Lalu, Tahun Ini</em>.
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <strong>Ekspor Excel (.xls):</strong> Mengunduh tabel transaksi berformat spreadsheet lengkap dengan rumus subtotal.
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <strong>Ekspor CSV (.csv):</strong> Format standar universal ber-BOM UTF-8, dapat dibuka mulus di Google Sheets, LibreOffice, atau Apple Numbers.
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                <strong>Cetak / Simpan PDF:</strong> Format lembar cetak resmi dengan kop surat laundry dan kolom tanda tangan penanggung jawab.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Master Layanan & Karyawan -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-tags"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-purple-600 tracking-wider">Modul Eksekutif 3</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Master Layanan & Manajemen Tim</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Kendali penuh atas konfigurasi operasional dan otorisasi akses:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-600">
                            <li><strong>• Master Layanan & Tarif (`superadmin/layanan.php`):</strong> Menambah jenis cucian, mengubah harga per kg atau pcs, serta menentukan durasi estimasi waktu selesai (jam).</li>
                            <li><strong>• Manajemen Karyawan (`superadmin/karyawan.php`):</strong> Mendaftarkan akun kasir baru, mereset password staf, atau menonaktifkan akun pekerja. Hak akses superadmin terisolasi ketat dari staf biasa.</li>
                        </ul>
                    </div>
                </div>

                <!-- 4. Pengaturan & Gateway WA -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold mb-4">
                            <i class="fas fa-sliders"></i>
                        </div>
                        <span class="text-[11px] font-black uppercase text-amber-600 tracking-wider">Modul Eksekutif 4</span>
                        <h3 class="text-lg font-black text-slate-900 mt-1">Pengaturan Sistem & WhatsApp Gateway</h3>
                        <p class="text-xs text-slate-600 leading-relaxed mt-2">
                            Personalisasi identitas toko dan integrasi bot notifikasi otomatis:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs text-slate-600">
                            <li><strong>• Profil Usaha:</strong> Mengatur nama laundry, alamat outlet, dan nomor telepon yang tercetak di struk nota kasir.</li>
                            <li><strong>• WhatsApp Provider:</strong> Mendukung <em>Mode Manual 1-Klik wa.me</em> (100% gratis), <em>Fonnte REST API</em>, serta <em>Wablas API</em> lengkap dengan konfigurasi custom domain server.</li>
                            <li><strong>• Template Pesan:</strong> Personalisasi pesan otomatis menggunakan variabel dinamis <code class="bg-slate-100 text-indigo-600 px-1 rounded">{nama}</code>, <code class="bg-slate-100 text-indigo-600 px-1 rounded">{no_nota}</code>, dan <code class="bg-slate-100 text-indigo-600 px-1 rounded">{link_tracking}</code>.</li>
                            <li><strong>• Kustomisasi Struk Thermal:</strong> Menyesuaikan teks syarat dan ketentuan (T&C) di bagian footer struk kasir.</li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Quick Action Box -->
            <div class="p-6 rounded-3xl bg-indigo-50 border border-indigo-100 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
                <div>
                    <h4 class="font-extrabold text-indigo-950 text-base">Akses Panel Superadmin (Owner)</h4>
                    <p class="text-xs text-slate-600 mt-0.5">Buka Dashboard Analytics atau Laporan Keuangan usaha Anda.</p>
                </div>
                <div class="flex gap-2">
                    <a href="superadmin/index.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition">
                        Buka Dashboard Analytics &rarr;
                    </a>
                    <a href="superadmin/laporan.php" class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold text-xs rounded-xl transition">
                        Laporan Keuangan
                    </a>
                </div>
            </div>

        </div>

    </div>

    <!-- FAQ Section -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 pb-16">
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-100 space-y-6">
            <div class="text-center max-w-lg mx-auto">
                <span class="text-indigo-600 font-extrabold text-xs uppercase tracking-wider">Bantuan & Solusi</span>
                <h3 class="text-2xl font-black text-slate-900 mt-1">Pertanyaan yang Sering Diajukan (FAQ)</h3>
            </div>

            <div class="space-y-3 text-xs sm:text-sm">
                <details class="p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer group">
                    <summary class="font-bold text-slate-800 flex justify-between items-center">
                        <span>Apakah pelanggan wajib mendaftar akun untuk mengecek cucian?</span>
                        <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
                    </summary>
                    <p class="text-slate-600 mt-2 text-xs leading-relaxed">
                        Tidak. Sistem dirancang tanpa login wajib bagi pelanggan. Pelanggan cukup memasukkan Nomor Nota di halaman utama atau memindai QR Code di struk fisik nota mereka menggunakan kamera smartphone.
                    </p>
                </details>

                <details class="p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer group">
                    <summary class="font-bold text-slate-800 flex justify-between items-center">
                        <span>Bagaimana jika printer kasir menggunakan ukuran kertas 58mm?</span>
                        <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
                    </summary>
                    <p class="text-slate-600 mt-2 text-xs leading-relaxed">
                        Pada halaman cetak struk kasir (`admin/cetak_nota.php`), klik tombol <strong>Lebar 58mm</strong> di bagian atas. Tampilan struk akan otomatis menyesuaikan ukuran 58mm yang pas untuk printer bluetooth portabel.
                    </p>
                </details>

                <details class="p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer group">
                    <summary class="font-bold text-slate-800 flex justify-between items-center">
                        <span>Bagaimana cara kerja notifikasi WhatsApp otomatis?</span>
                        <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
                    </summary>
                    <p class="text-slate-600 mt-2 text-xs leading-relaxed">
                        Jika menggunakan Fonnte API atau Wablas, pesan status otomatis dikirim langsung oleh server saat pesanan dibuat dan saat status diubah menjadi Selesai. Jika menggunakan mode manual (gratis), sistem menyediakan tombol hijau untuk membuka WhatsApp dengan teks yang sudah terisi otomatis.
                    </p>
                </details>

                <details class="p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer group">
                    <summary class="font-bold text-slate-800 flex justify-between items-center">
                        <span>Apakah data laporan keuangan bisa diekspor ke Excel?</span>
                        <i class="fas fa-chevron-down text-slate-400 group-open:rotate-180 transition"></i>
                    </summary>
                    <p class="text-slate-600 mt-2 text-xs leading-relaxed">
                        Ya. Menu Laporan Keuangan pemilik usaha menyediakan tombol <strong>Ekspor Excel (.xls)</strong>, <strong>Ekspor CSV</strong>, serta tombol <strong>Cetak / Simpan PDF</strong> berformat dokumen resmi.
                    </p>
                </details>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 py-10 border-t border-slate-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
            <div class="flex items-center gap-2 font-bold text-white">
                <i class="fas fa-soap text-indigo-400"></i>
                <span><?= htmlspecialchars($set['nama_laundry']); ?> &copy; <?= date('Y'); ?></span>
            </div>
            <div class="flex items-center gap-4 text-slate-400">
                <a href="index.php" class="hover:text-white transition">Halaman Utama</a>
                <a href="panduan.php?role=customer" class="hover:text-white transition">Panduan Pelanggan</a>
                <a href="panduan.php?role=kasir" class="hover:text-white transition">Panduan Kasir</a>
                <a href="panduan.php?role=owner" class="hover:text-white transition">Panduan Owner</a>
                <a href="auth/login.php" class="hover:text-white transition">Login Staf</a>
            </div>
        </div>
    </footer>

</body>
</html>
