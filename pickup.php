<?php
// pickup.php - Public Pickup Request Form (Tanpa Perlu Login)
require_once 'config/database.php';
require_once 'includes/whatsapp.php';

$set = get_settings($conn);
$msg = '';
$err = '';
$wa_confirm_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pickup'])) {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $address = trim($_POST['pickup_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($name) && !empty($phone) && !empty($address)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pickup_requests (customer_id, customer_name, customer_phone, pickup_address, notes, status, created_at) VALUES (0, ?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $phone, $address, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Permintaan jemput cucian berhasil dikirim! Staf kami akan segera mengonfirmasi dan menjemput pakaian kotor Anda.';
            
            $wa_text = "Halo Admin " . $set['nama_laundry'] . ", saya baru saja mengirim permintaan jemput cucian:\n\n" .
                       "Nama: " . $name . "\n" .
                       "No. WA: " . $phone . "\n" .
                       "Alamat: " . $address . "\n" .
                       ($notes ? "Catatan: " . $notes . "\n" : "") .
                       "\nMohon dikonfirmasi ya, terima kasih!";
            $wa_confirm_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $set['whatsapp']) . "?text=" . rawurlencode($wa_text);
        } else {
            $err = 'Gagal mengirim permintaan: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Nama, nomor WhatsApp, dan alamat penjemputan wajib diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minta Jemput Cucian - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-white to-slate-100 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white/90 backdrop-blur-md sticky top-0 z-50 border-b border-slate-100 shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3.5 flex justify-between items-center">
            <a href="index.php" class="flex items-center gap-2.5 font-black text-xl text-indigo-600">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm shadow-md shadow-indigo-200">
                    <i class="fas fa-soap"></i>
                </span>
                <span><?= htmlspecialchars($set['nama_laundry']); ?></span>
            </a>
            <div class="flex items-center gap-2">
                <a href="index.php" class="text-xs font-bold text-slate-600 hover:text-indigo-600 px-3 py-2">
                    <i class="fas fa-arrow-left mr-1"></i> Beranda & Lacak
                </a>
                <a href="auth/login.php" class="text-xs font-extrabold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3.5 py-2 rounded-xl transition">
                    Login Staf
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto px-4 py-10">
        
        <div class="text-center mb-8">
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-100 text-indigo-800 text-xs font-black uppercase tracking-wider mb-3">
                <i class="fas fa-truck-pickup text-indigo-600"></i> Layanan Antar Jemput
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Formulir Jemput Cucian (Pickup)</h1>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">Tidak perlu repot ke outlet! Isi formulir di bawah ini, kami yang akan datang mengambil pakaian kotor Anda.</p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 rounded-3xl p-6 shadow-sm">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-emerald-900 text-sm">Permintaan Berhasil Terkirim!</h4>
                        <p class="text-xs text-emerald-700"><?= htmlspecialchars($msg); ?></p>
                    </div>
                </div>
                <?php if ($wa_confirm_url): ?>
                <div class="pt-3 border-t border-emerald-200 text-center">
                    <a href="<?= $wa_confirm_url; ?>" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition">
                        <i class="fab fa-whatsapp text-sm"></i>
                        <span>Chat WhatsApp Toko untuk Konfirmasi Cepat</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
                <i class="fas fa-circle-exclamation text-rose-500 shrink-0"></i>
                <span><?= htmlspecialchars($err); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-8">
            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Lengkap Anda *</label>
                    <input type="text" name="customer_name" required placeholder="Contoh: Ibu Rina / Pak Budi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nomor WhatsApp / HP Aktif *</label>
                    <input type="tel" name="customer_phone" required placeholder="Contoh: 08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <span class="text-[11px] text-slate-400 mt-1 block">Kurir akan mengirim pesan WhatsApp sebelum menuju ke lokasi.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat Penjemputan Lengkap *</label>
                    <textarea name="pickup_address" required rows="3" placeholder="Jl. Melati No. 15, RT 02/04, Patokan depan minimarket..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Perkiraan Cucian & Catatan Khusus</label>
                    <textarea name="notes" rows="2" placeholder="Contoh: 2 kantong kresek pakaian campur, 1 selimut tebal..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <button type="submit" name="submit_pickup" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-2xl shadow-xl shadow-indigo-100 transition flex items-center justify-center gap-2 mt-4 text-sm">
                    <i class="fas fa-paper-plane"></i>
                    <span>Kirim Permintaan Jemput Sekarang</span>
                </button>
            </form>
        </div>

    </div>

</body>
</html>
