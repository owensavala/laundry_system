<?php
// admin/pickup.php - Manage Customer Laundry Pickup Requests
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../layout/header.php';

$msg = '';
$err = '';

// Handle manual pickup entry by cashier (misal pelanggan telpon/chat WA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pickup_manual'])) {
    $c_name = trim($_POST['customer_name'] ?? '');
    $c_phone = trim($_POST['customer_phone'] ?? '');
    $c_address = trim($_POST['pickup_address'] ?? '');
    $c_notes = trim($_POST['notes'] ?? '');

    if (!empty($c_name) && !empty($c_phone) && !empty($c_address)) {
        $stmt_ins = mysqli_prepare($conn, "INSERT INTO pickup_requests (customer_id, customer_name, customer_phone, pickup_address, notes, status, created_at) VALUES (0, ?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($stmt_ins, "ssss", $c_name, $c_phone, $c_address, $c_notes);
        if (mysqli_stmt_execute($stmt_ins)) {
            $msg = 'Permintaan jemput berhasil ditambahkan!';
        } else {
            $err = 'Gagal menyimpan: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Nama, nomor HP/WA, dan alamat penjemputan wajib diisi!';
    }
}

// Update pickup status
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $st = $_GET['status'];
    $valid = ['pending', 'confirmed', 'picked_up', 'cancelled'];
    if (in_array($st, $valid)) {
        mysqli_query($conn, "UPDATE pickup_requests SET status='$st' WHERE id='$id'");
        $msg = 'Status penjemputan berhasil diperbarui!';
    }
}

// Fetch pickup requests
$pickups = mysqli_query($conn, "SELECT p.* 
    FROM pickup_requests p 
    ORDER BY FIELD(p.status, 'pending', 'confirmed', 'picked_up', 'cancelled'), p.created_at DESC");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Permintaan Pickup (Jemput Cucian)</h1>
        <p class="text-slate-400 text-sm">Daftar permintaan pelanggan agar pakaian kotor dijemput ke rumah/lokasi mereka.</p>
    </div>
    <div class="flex gap-2">
        <button onclick="document.getElementById('modalTambahPickup').classList.remove('hidden')" 
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Catat Request Manual
        </button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-500"></i>
        <span><?= $err; ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Waktu Request</th>
                    <th class="px-6 py-4">Nama Pelanggan</th>
                    <th class="px-6 py-4">Kontak (WhatsApp)</th>
                    <th class="px-6 py-4">Alamat Penjemputan</th>
                    <th class="px-6 py-4">Catatan</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-center">Aksi Petugas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php 
                $count = 0;
                while($p = mysqli_fetch_assoc($pickups)): 
                    $count++;
                    $st = $p['status'];
                    $badge = 'bg-slate-100 text-slate-600';
                    $lbl = 'Menunggu';
                    if ($st === 'confirmed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Dikonfirmasi'; }
                    elseif ($st === 'picked_up') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Sudah Dijemput'; }
                    elseif ($st === 'cancelled') { $badge = 'bg-rose-100 text-rose-800'; $lbl = 'Dibatalkan'; }
                ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                        <?= date('d/m/y H:i', strtotime($p['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-800">
                        <?= htmlspecialchars($p['customer_name']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $p['customer_phone']); ?>?text=Halo%20<?= urlencode($p['customer_name']); ?>,%20kami%20dari%20laundry%20mengonfirmasi%20jadwal%20penjemputan%20cucian%20Anda." 
                           target="_blank" class="inline-flex items-center gap-1.5 text-xs text-emerald-600 hover:text-emerald-700 font-bold bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                            <i class="fab fa-whatsapp text-sm"></i>
                            <?= htmlspecialchars($p['customer_phone']); ?>
                        </a>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-600 max-w-xs">
                        <i class="fas fa-map-marker-alt text-rose-500 mr-1"></i>
                        <?= htmlspecialchars($p['pickup_address']); ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500 italic max-w-xs">
                        <?= htmlspecialchars($p['notes'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-black uppercase <?= $badge; ?>">
                            <?= $lbl; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <?php if ($st === 'pending'): ?>
                                <a href="?id=<?= $p['id']; ?>&status=confirmed" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition">
                                    Konfirmasi
                                </a>
                            <?php elseif ($st === 'confirmed'): ?>
                                <a href="?id=<?= $p['id']; ?>&status=picked_up" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition">
                                    Sudah Dijemput
                                </a>
                                <a href="pos.php?pickup_id=<?= $p['id']; ?>&name=<?= urlencode($p['customer_name']); ?>&phone=<?= urlencode($p['customer_phone']); ?>&address=<?= urlencode($p['pickup_address']); ?>&notes=<?= urlencode($p['notes'] ?? ''); ?>" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                    <i class="fas fa-cash-register"></i> Input POS
                                </a>
                            <?php elseif ($st === 'picked_up'): ?>
                                <a href="pos.php?pickup_id=<?= $p['id']; ?>&name=<?= urlencode($p['customer_name']); ?>&phone=<?= urlencode($p['customer_phone']); ?>&address=<?= urlencode($p['pickup_address']); ?>&notes=<?= urlencode($p['notes'] ?? ''); ?>" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                    <i class="fas fa-plus-circle"></i> Input POS
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($st !== 'cancelled'): ?>
                                <a href="?id=<?= $p['id']; ?>&status=cancelled" onclick="return confirm('Batalkan permintaan pickup ini?')" class="p-1.5 bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 rounded-lg text-xs transition" title="Batalkan">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($count === 0): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-motorcycle text-3xl text-slate-300 block mb-2"></i>
                        Belum ada permintaan penjemputan dari pelanggan.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pickup Manual -->
<div id="modalTambahPickup" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Catat Permintaan Jemput Baru</h3>
            <button onclick="document.getElementById('modalTambahPickup').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Pelanggan *</label>
                <input type="text" name="customer_name" required placeholder="Contoh: Bpk. Joko" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">No. WhatsApp / HP *</label>
                <input type="tel" name="customer_phone" required placeholder="08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat Penjemputan *</label>
                <textarea name="pickup_address" required rows="2" placeholder="Jl. Melati No. 5, Kos Kuning..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Catatan</label>
                <input type="text" name="notes" placeholder="Contoh: 1 Bed cover + pakaian kiloan" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalTambahPickup').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="tambah_pickup_manual" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100">Simpan Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../layout/footer.php'; ?>
