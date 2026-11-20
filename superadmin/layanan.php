<?php
// superadmin/layanan.php - Master Data Services CRUD
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

require_once '../layout/header.php';

$msg = '';
$err = '';

// Create
if (isset($_POST['tambah_layanan'])) {
    $name = trim($_POST['service_name'] ?? '');
    $type = $_POST['service_type'] ?? 'kiloan';
    $price = (float)($_POST['price'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'kg');
    $duration = (int)($_POST['duration_hours'] ?? 24);

    if (!empty($name) && $price > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO services (service_name, service_type, price, unit, duration_hours) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssdsi", $name, $type, $price, $unit, $duration);
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Layanan baru berhasil ditambahkan!';
        } else {
            $err = 'Gagal menambahkan layanan: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Mohon isi nama layanan dan harga dengan benar.';
    }
}

// Update
if (isset($_POST['edit_layanan'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['service_name'] ?? '');
    $type = $_POST['service_type'] ?? 'kiloan';
    $price = (float)($_POST['price'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'kg');
    $duration = (int)($_POST['duration_hours'] ?? 24);

    if (!empty($name) && $price > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE services SET service_name=?, service_type=?, price=?, unit=?, duration_hours=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "ssdsii", $name, $type, $price, $unit, $duration, $id);
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Layanan berhasil diperbarui!';
        } else {
            $err = 'Gagal memperbarui layanan: ' . mysqli_error($conn);
        }
    }
}

// Delete
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if (mysqli_query($conn, "DELETE FROM services WHERE id='$id'")) {
        $msg = 'Layanan berhasil dihapus!';
    } else {
        $err = 'Gagal menghapus layanan: ' . mysqli_error($conn);
    }
}

// Fetch list
$services = mysqli_query($conn, "SELECT * FROM services ORDER BY service_type ASC, price ASC");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Master Data Layanan & Tarif</h1>
        <p class="text-slate-400 text-sm">Kelola daftar layanan laundry kiloan, satuan, harga per unit, dan estimasi waktu selesai.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" 
            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-100 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Tambah Layanan
    </button>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-red-500"></i>
        <span><?= htmlspecialchars($err); ?></span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Nama Layanan</th>
                    <th class="px-6 py-4">Kategori / Tipe</th>
                    <th class="px-6 py-4">Harga / Satuan</th>
                    <th class="px-6 py-4">Estimasi Selesai</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while ($s = mysqli_fetch_assoc($services)): ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 font-bold text-slate-800">
                        <?= htmlspecialchars($s['service_name']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($s['service_type'] === 'kiloan'): ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">
                                <i class="fas fa-weight-hanging mr-1"></i> Kiloan
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                <i class="fas fa-shirt mr-1"></i> Satuan
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 font-extrabold text-indigo-600 font-mono">
                        Rp <?= number_format($s['price'], 0, ',', '.'); ?> <span class="text-xs text-slate-400 font-normal">/ <?= htmlspecialchars($s['unit']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-slate-600">
                        <i class="far fa-clock text-indigo-400 mr-1"></i>
                        <?= (int)$s['duration_hours']; ?> Jam (<?= round($s['duration_hours'] / 24, 1); ?> Hari)
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='editService(<?= json_encode($s); ?>)' class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition flex items-center justify-center text-xs">
                                <i class="fas fa-pen"></i>
                            </button>
                            <a href="?hapus=<?= $s['id']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus layanan ini?')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 transition flex items-center justify-center text-xs">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Layanan -->
<div id="modalTambah" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Tambah Layanan Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Layanan *</label>
                <input type="text" name="service_name" required placeholder="Contoh: Cuci Kering Setrika Reguler" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tipe Layanan *</label>
                    <select name="service_type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="kiloan">Kiloan</option>
                        <option value="satuan">Satuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Satuan Unit *</label>
                    <input type="text" name="unit" required value="kg" placeholder="kg / pcs / lembar" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Harga (Rp) *</label>
                    <input type="number" name="price" required min="100" step="500" placeholder="7000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Durasi (Jam) *</label>
                    <input type="number" name="duration_hours" required value="48" min="1" placeholder="48" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="tambah_layanan" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100">Simpan Layanan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Layanan -->
<div id="modalEdit" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Edit Layanan</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="id" id="edit_id">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Layanan *</label>
                <input type="text" name="service_name" id="edit_name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tipe Layanan *</label>
                    <select name="service_type" id="edit_type" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="kiloan">Kiloan</option>
                        <option value="satuan">Satuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Satuan Unit *</label>
                    <input type="text" name="unit" id="edit_unit" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Harga (Rp) *</label>
                    <input type="number" name="price" id="edit_price" required min="100" step="500" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Durasi (Jam) *</label>
                    <input type="number" name="duration_hours" id="edit_duration" required min="1" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="edit_layanan" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-indigo-100">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editService(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_name').value = s.service_name;
    document.getElementById('edit_type').value = s.service_type;
    document.getElementById('edit_unit').value = s.unit;
    document.getElementById('edit_price').value = parseInt(s.price);
    document.getElementById('edit_duration').value = s.duration_hours;
    document.getElementById('modalEdit').classList.remove('hidden');
}
</script>

<?php require_once '../layout/footer.php'; ?>
