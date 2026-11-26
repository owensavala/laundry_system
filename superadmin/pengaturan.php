<?php
// superadmin/pengaturan.php - System Settings & WhatsApp Integration
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

require_once '../includes/whatsapp.php';

$msg = '';
$err = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $nama = trim($_POST['nama_laundry'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $wa_provider = $_POST['wa_gateway_provider'] ?? 'manual';
    $wa_key = trim($_POST['wa_api_key'] ?? '');
    $wa_domain = trim($_POST['wa_domain'] ?? 'https://phone.wablas.com');
    $wa_tpl_order = trim($_POST['wa_template_order'] ?? '');
    $wa_tpl_ready = trim($_POST['wa_template_ready'] ?? '');
    $receipt_footer = trim($_POST['receipt_footer'] ?? '');

    $stmt = mysqli_prepare($conn, "UPDATE settings SET nama_laundry=?, alamat=?, whatsapp=?, wa_gateway_provider=?, wa_api_key=?, wa_domain=?, wa_template_order=?, wa_template_ready=?, receipt_footer=? WHERE id=1");
    mysqli_stmt_bind_param($stmt, "sssssssss", $nama, $alamat, $whatsapp, $wa_provider, $wa_key, $wa_domain, $wa_tpl_order, $wa_tpl_ready, $receipt_footer);
    
    if (mysqli_stmt_execute($stmt)) {
        $msg = 'Pengaturan sistem, WhatsApp, & Struk Thermal berhasil disimpan!';
    } else {
        $err = 'Gagal menyimpan pengaturan: ' . mysqli_error($conn);
    }
}

// Handle Test WhatsApp Send
if (isset($_POST['test_wa'])) {
    $target_phone = trim($_POST['test_phone'] ?? '');
    if (!empty($target_phone)) {
        $test_res = send_wa_notification($conn, $target_phone, "Halo! Ini adalah pesan uji coba (test) notifikasi otomatis dari sistem " . $sys_settings['nama_laundry']);
        if ($test_res['success']) {
            $msg = 'Pesan uji coba WhatsApp berhasil dikirim ke ' . htmlspecialchars($target_phone);
        } else {
            $err = 'Pengiriman API gagal: ' . htmlspecialchars($test_res['message']) . '. Silakan gunakan tautan manual berikut jika belum ada API key: <a href="' . $test_res['direct_url'] . '" target="_blank" class="underline font-bold">Kirim Uji Coba Manual (wa.me)</a>';
        }
    } else {
        $err = 'Masukkan nomor HP untuk pengujian.';
    }
}

$set = get_settings($conn);

require_once '../layout/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Pengaturan Sistem & WhatsApp</h1>
        <p class="text-slate-400 text-sm">Sesuaikan profil toko laundry, integrasi WhatsApp Gateway, dan template notifikasi otomatis.</p>
    </div>
    <a href="../admin/tampilan.php" class="px-4 py-2.5 btn-brand rounded-xl text-xs font-bold shadow-md flex items-center gap-2 shrink-0">
        <i class="fas fa-palette"></i> Ubah Warna, Nama & Logo
    </a>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-500"></i>
        <span><?= $err; ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Main Settings Form -->
    <div class="lg:col-span-2 space-y-6">
        <form action="" method="POST" class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 space-y-6">
            
            <div class="border-b border-slate-100 pb-4">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-store text-indigo-500"></i> Profil Usaha Laundry
                </h3>
                <p class="text-xs text-slate-400 mt-1">Informasi ini akan tertera pada nota/struk thermal dan header sistem.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Laundry *</label>
                <input type="text" name="nama_laundry" required value="<?= htmlspecialchars($set['nama_laundry']); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">No. WhatsApp Toko / Admin *</label>
                    <input type="text" name="whatsapp" required value="<?= htmlspecialchars($set['whatsapp']); ?>" placeholder="628123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <span class="text-[11px] text-slate-400 mt-1 block">Gunakan awalan 62 atau 08.</span>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat Toko / Outlet *</label>
                    <input type="text" name="alamat" required value="<?= htmlspecialchars($set['alamat']); ?>" placeholder="Jl. Sudirman No. 12" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="border-b border-slate-100 pt-4 pb-4">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fab fa-whatsapp text-emerald-500 text-xl"></i> Konfigurasi WhatsApp Notifikasi
                </h3>
                <p class="text-xs text-slate-400 mt-1">Sistem mendukung pengiriman otomatis via Fonnte API atau mode manual klik wa.me.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Metode / Gateway WhatsApp</label>
                    <select name="wa_gateway_provider" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="manual" <?= ($set['wa_gateway_provider'] ?? '') === 'manual' ? 'selected' : ''; ?>>Mode 1-Klik wa.me (Tanpa Biaya / Gratis)</option>
                        <option value="fonnte" <?= ($set['wa_gateway_provider'] ?? '') === 'fonnte' ? 'selected' : ''; ?>>Fonnte API (Otomatis)</option>
                        <option value="wablas" <?= ($set['wa_gateway_provider'] ?? '') === 'wablas' ? 'selected' : ''; ?>>Wablas API (Otomatis)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">API Token / Secret Key (Fonnte / Wablas)</label>
                    <input type="password" name="wa_api_key" value="<?= htmlspecialchars($set['wa_api_key'] ?? ''); ?>" placeholder="Masukkan Token API..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <span class="text-[11px] text-slate-400 mt-1 block">Dapatkan di fonnte.com atau wablas.com</span>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Wablas Server Domain (Hanya jika pakai Wablas)</label>
                    <input type="text" name="wa_domain" value="<?= htmlspecialchars($set['wa_domain'] ?? 'https://phone.wablas.com'); ?>" placeholder="https://phone.wablas.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <span class="text-[11px] text-slate-400 mt-1 block">Domain endpoint server Wablas akun Anda, contoh: https://phone.wablas.com atau https://kudus.wablas.com</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Template Pesan: Pesanan Baru (Input Drop-off)</label>
                <textarea name="wa_template_order" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($set['wa_template_order']); ?></textarea>
                <span class="text-[11px] text-slate-400 mt-1 block">Tag variabel yang tersedia: <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{nama}</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{no_nota}</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{total}</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{link_tracking}</code></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Template Pesan: Cucian Selesai (Siap Diambil)</label>
                <textarea name="wa_template_ready" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($set['wa_template_ready']); ?></textarea>
                <span class="text-[11px] text-slate-400 mt-1 block">Tag variabel: <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{nama}</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{no_nota}</code>, <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{laundry_name}</code></span>
            </div>

            <div class="border-b border-slate-100 pt-4 pb-4">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-print text-indigo-500"></i> Kustomisasi Struk Thermal & Kasir
                </h3>
                <p class="text-xs text-slate-400 mt-1">Atur teks footer, syarat & ketentuan yang dicetak di bagian bawah struk fisik.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Syarat & Ketentuan / Footer Struk</label>
                <textarea name="receipt_footer" rows="3" placeholder="Syarat & Ketentuan: Barang yang tidak diambil dalam tempo 30 hari di luar tanggung jawab pihak laundry..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($set['receipt_footer'] ?? ''); ?></textarea>
                <span class="text-[11px] text-slate-400 mt-1 block">Teks ini akan otomatis dicetak pada bagian paling bawah struk kasir 80mm dan 58mm.</span>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" name="simpan_pengaturan" class="w-full sm:w-auto px-6 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100 transition flex items-center justify-center">
                    <i class="fas fa-save mr-2"></i> Simpan Semua Pengaturan
                </button>
            </div>
        </form>
    </div>

    <!-- Right Side: Test & Guide -->
    <div class="space-y-6">
        
        <!-- Test WhatsApp Card -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <h3 class="text-base font-bold text-slate-800 mb-2 flex items-center gap-2">
                <i class="fas fa-paper-plane text-indigo-500"></i> Tes Kirim WhatsApp
            </h3>
            <p class="text-xs text-slate-400 mb-4">Uji koneksi gateway atau buat link tes pesan ke nomor HP tujuan.</p>

            <form action="" method="POST" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor WhatsApp Tujuan</label>
                    <input type="tel" name="test_phone" required placeholder="081234567890" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <button type="submit" name="test_wa" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-xl font-bold text-xs shadow-md shadow-emerald-100 transition">
                    <i class="fab fa-whatsapp mr-1"></i> Kirim Pesan Uji Coba
                </button>
            </form>
        </div>

        <!-- Guide Card -->
        <div class="bg-indigo-50/70 p-6 rounded-3xl border border-indigo-100 text-xs text-slate-600 space-y-3">
            <h4 class="font-bold text-indigo-950 flex items-center gap-2 text-sm">
                <i class="fas fa-info-circle text-indigo-600"></i> Panduan WhatsApp & Struk
            </h4>
            <p><strong>1. Mode Manual (Gratis):</strong> Setiap kali kasir selesai menginput pesanan atau memperbarui status, sistem memunculkan tombol hijau untuk langsung membuka WhatsApp Web/Mobile dengan pesan terisi otomatis.</p>
            <p><strong>2. Mode Fonnte API:</strong> Mengirim notifikasi otomatis di latar belakang melalui REST API Fonnte (<a href="https://fonnte.com" target="_blank" class="text-indigo-600 underline font-bold">fonnte.com</a>).</p>
            <p><strong>3. Mode Wablas API:</strong> Mengirim notifikasi otomatis server-to-server menggunakan gateway Wablas (<a href="https://wablas.com" target="_blank" class="text-indigo-600 underline font-bold">wablas.com</a>).</p>
            <p><strong>4. Struk Kasir Thermal:</strong> Struk otomatis dilengkapi QR Code dynamic untuk pelacakan langsung oleh pelanggan, tombol beralih ukuran printer 80mm / 58mm, serta rincian diskon dan uang kembali.</p>
        </div>

    </div>

</div>

<?php require_once '../layout/footer.php'; ?>
