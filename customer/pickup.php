<?php
// customer/pickup.php - Customer Laundry Pickup Request Form
require_once '../includes/auth_check.php';
check_auth(['customer']);

$cust_id = (int)$_SESSION['user_id'];
$msg = '';
$err = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_pickup'])) {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $address = trim($_POST['pickup_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($name) && !empty($phone) && !empty($address)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pickup_requests (customer_id, customer_name, customer_phone, pickup_address, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        mysqli_stmt_bind_param($stmt, "issss", $cust_id, $name, $phone, $address, $notes);
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Permintaan penjemputan berhasil dikirim! Petugas kami akan segera menghubungi Anda melalui WhatsApp.';
        } else {
            $err = 'Gagal mengirim permintaan: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Nama, nomor WhatsApp, dan alamat penjemputan wajib diisi!';
    }
}

// Fetch user profile for default values
$q_prof = mysqli_query($conn, "SELECT * FROM users WHERE id='$cust_id'");
$prof = mysqli_fetch_assoc($q_prof);

// Fetch existing pickup requests
$q_my_pickups = mysqli_query($conn, "SELECT * FROM pickup_requests WHERE customer_id='$cust_id' ORDER BY created_at DESC");

require_once '../layout/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Minta Jemput Cucian (Pickup)</h1>
        <p class="text-slate-400 text-sm">Tidak sempat ke outlet? Kami jemput pakaian kotor langsung ke rumah atau kos Anda.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-red-500"></i>
        <span><?= $err; ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Pickup Form (Col 1) -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 h-fit space-y-4">
        <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-3">
            <i class="fas fa-truck-pickup text-indigo-500"></i> Formulir Penjemputan
        </h3>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Pemesan *</label>
                <input type="text" name="customer_name" required value="<?= htmlspecialchars($prof['name'] ?? $_SESSION['name']); ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nomor WhatsApp *</label>
                <input type="tel" name="customer_phone" required value="<?= htmlspecialchars($prof['phone'] ?? ''); ?>" placeholder="08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-[11px] text-slate-400 mt-1 block">Petugas akan konfirmasi via WA sebelum berangkat.</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat Penjemputan Lengkap *</label>
                <textarea name="pickup_address" required rows="3" placeholder="Jl. Melati No. 12, RT 03/RW 04, Pagar hitam sebelah warung..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= htmlspecialchars($prof['address'] ?? ''); ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Perkiraan Cucian & Catatan</label>
                <textarea name="notes" rows="2" placeholder="Contoh: Sekitar 2 kantong pakaian campur + 1 bed cover..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <button type="submit" name="submit_pickup" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold py-3.5 rounded-xl shadow-lg shadow-indigo-100 transition flex items-center justify-center gap-2">
                <i class="fas fa-paper-plane"></i>
                <span>Kirim Permintaan Jemput</span>
            </button>
        </form>
    </div>

    <!-- Pickup History (Col 2) -->
    <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-extrabold text-slate-800 text-base">Riwayat Permintaan Jemput Saya</h3>
            <span class="text-xs text-slate-400">Status Penjemputan</span>
        </div>

        <div class="divide-y divide-slate-100">
            <?php 
            $count = 0;
            while($p = mysqli_fetch_assoc($q_my_pickups)): 
                $count++;
                $st = $p['status'];
                $badge = 'bg-slate-100 text-slate-600';
                $lbl = 'Menunggu Konfirmasi';
                if ($st === 'confirmed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Kurir Menuju Lokasi'; }
                elseif ($st === 'picked_up') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Pakaian Sudah Diterima Outlet'; }
                elseif ($st === 'cancelled') { $badge = 'bg-rose-100 text-rose-800'; $lbl = 'Dibatalkan'; }
            ?>
            <div class="p-6 hover:bg-slate-50/50 transition">
                <div class="flex justify-between items-start gap-4 mb-2">
                    <div>
                        <span class="text-xs text-slate-400"><i class="far fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($p['created_at'])); ?></span>
                        <h4 class="font-bold text-slate-800 text-sm mt-1"><i class="fas fa-map-marker-alt text-rose-500 mr-1.5"></i><?= htmlspecialchars($p['pickup_address']); ?></h4>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase <?= $badge; ?> shrink-0">
                        <?= $lbl; ?>
                    </span>
                </div>
                <?php if (!empty($p['notes'])): ?>
                    <p class="text-xs text-slate-500 mt-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                        <strong>Catatan Anda:</strong> <?= htmlspecialchars($p['notes']); ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>

            <?php if ($count === 0): ?>
            <div class="p-12 text-center text-slate-400">
                <i class="fas fa-motorcycle text-3xl text-slate-300 block mb-2"></i>
                <p class="font-bold text-slate-700 text-sm">Belum ada riwayat penjemputan.</p>
                <p class="text-xs text-slate-400 mt-1">Gunakan formulir di samping untuk meminta staf kami menjemput pakaian kotor Anda.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once '../layout/footer.php'; ?>
