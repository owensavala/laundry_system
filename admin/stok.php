<?php
// admin/stok.php - Manajemen Stok Bahan Habis Pakai (Deterjen, Pewangi, Plastik, Kertas Struk)
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

$user_id = (int)($_SESSION['user_id'] ?? 1);
$msg = '';
$err = '';

// Handle Add New Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_item'])) {
    $name = trim($_POST['item_name'] ?? '');
    $cat = trim($_POST['category'] ?? 'Bahan Cuci');
    $qty = max(0, (float)($_POST['stock_qty'] ?? 0));
    $unit = trim($_POST['unit'] ?? 'Liter');
    $min = max(0, (float)($_POST['min_stock'] ?? 5));

    if (!empty($name)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO inventory_items (item_name, category, stock_qty, unit, min_stock) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssdsd", $name, $cat, $qty, $unit, $min);
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            if ($qty > 0) {
                mysqli_query($conn, "INSERT INTO inventory_logs (item_id, user_id, change_qty, type, notes) VALUES ($new_id, $user_id, $qty, 'in', 'Stok awal barang baru')");
            }
            $msg = "Bahan '$name' berhasil ditambahkan ke inventaris!";
        } else {
            $err = 'Gagal menambah item: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Nama bahan wajib diisi!';
    }
}

// Handle Stock In / Out Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $type = ($_POST['type'] ?? 'in') === 'out' ? 'out' : 'in';
    $amount = max(0.1, (float)($_POST['amount'] ?? 0));
    $notes = trim($_POST['notes'] ?? ($type === 'in' ? 'Restok masuk' : 'Pemakaian operasional'));

    if ($item_id > 0 && $amount > 0) {
        $op = ($type === 'in') ? '+' : '-';
        $sql_upd = ($type === 'in')
            ? "UPDATE inventory_items SET stock_qty = stock_qty + $amount WHERE id = $item_id"
            : "UPDATE inventory_items SET stock_qty = GREATEST(0, stock_qty - $amount) WHERE id = $item_id";
        if (mysqli_query($conn, $sql_upd)) {
            $stmt_log = mysqli_prepare($conn, "INSERT INTO inventory_logs (item_id, user_id, change_qty, type, notes) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_log, "iidss", $item_id, $user_id, $amount, $type, $notes);
            mysqli_stmt_execute($stmt_log);
            $msg = 'Stok bahan berhasil diperbarui!';
        }
    }
}

$q_items = mysqli_query($conn, "SELECT * FROM inventory_items ORDER BY (stock_qty <= min_stock) DESC, category ASC, item_name ASC");
$items_list = [];
$low_stock_count = 0;
while ($row = mysqli_fetch_assoc($q_items)) {
    if ((float)$row['stock_qty'] <= (float)$row['min_stock']) $low_stock_count++;
    $items_list[] = $row;
}

$q_logs = mysqli_query($conn, "SELECT l.*, i.item_name, i.unit, u.name as staff_name 
    FROM inventory_logs l 
    LEFT JOIN inventory_items i ON l.item_id = i.id 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC LIMIT 15");

require_once '../layout/header.php';
?>

<div class="max-w-7xl mx-auto pb-16 space-y-6" x-data="{ showAddModal: false, showAdjustModal: false, adjItem: {}, adjType: 'in' }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl text-white flex items-center justify-center text-xl shadow-lg" style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Stok Bahan Habis Pakai</h1>
                    <?php if ($low_stock_count > 0): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 animate-pulse">
                            <?= $low_stock_count; ?> Stok Menipis!
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500">Pantau ketersediaan deterjen, pewangi, plastik packing, & kertas struk thermal.</p>
            </div>
        </div>
        <button type="button" @click="showAddModal = true" class="px-4 py-2.5 btn-brand rounded-xl text-xs font-bold shadow-md flex items-center gap-2 cursor-pointer">
            <i class="fas fa-plus"></i> Tambah Jenis Bahan
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
            <i class="fas fa-circle-check text-emerald-500"></i> <span><?= $msg; ?></span>
        </div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
            <i class="fas fa-circle-exclamation text-rose-500"></i> <span><?= $err; ?></span>
        </div>
    <?php endif; ?>

    <!-- Stock Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($items_list as $it):
            $is_low = (float)$it['stock_qty'] <= (float)$it['min_stock'];
        ?>
        <div class="bg-white p-5 rounded-3xl border <?= $is_low ? 'border-rose-300 ring-2 ring-rose-500/15' : 'border-slate-200/80'; ?> shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded-md bg-slate-100 text-slate-600"><?= htmlspecialchars($it['category']); ?></span>
                    <h3 class="text-sm font-black text-slate-800 mt-1.5"><?= htmlspecialchars($it['item_name']); ?></h3>
                </div>
                <?php if ($is_low): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-100 text-rose-700 shrink-0">
                        <i class="fas fa-triangle-exclamation mr-0.5"></i> Menipis
                    </span>
                <?php else: ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 shrink-0">Aman</span>
                <?php endif; ?>
            </div>

            <div class="flex items-end justify-between border-t border-slate-100 pt-3">
                <div>
                    <span class="text-[10px] text-slate-400 block">Sisa Stok:</span>
                    <span class="text-2xl font-black font-mono <?= $is_low ? 'text-rose-600' : 'text-slate-900'; ?>">
                        <?= (float)$it['stock_qty']; ?> <span class="text-xs font-bold text-slate-400"><?= htmlspecialchars($it['unit']); ?></span>
                    </span>
                    <span class="text-[10px] text-slate-400 block">Min. Batas: <?= (float)$it['min_stock']; ?> <?= htmlspecialchars($it['unit']); ?></span>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" @click="adjItem = <?= htmlspecialchars(json_encode($it)); ?>; adjType = 'out'; showAdjustModal = true"
                            class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition">
                        - Pakai
                    </button>
                    <button type="button" @click="adjItem = <?= htmlspecialchars(json_encode($it)); ?>; adjType = 'in'; showAdjustModal = true"
                            class="px-3 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition">
                        + Masuk
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Stock Logs -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-brand"></i> Riwayat Keluar-Masuk Bahan
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-400 uppercase font-extrabold text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Nama Bahan</th>
                        <th class="py-3 px-4 text-center">Jenis</th>
                        <th class="py-3 px-4 text-right">Jumlah</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4">Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <?php if ($q_logs && mysqli_num_rows($q_logs) > 0): ?>
                        <?php while($lg = mysqli_fetch_assoc($q_logs)): ?>
                        <tr>
                            <td class="py-3 px-4 text-slate-500"><?= date('d/m H:i', strtotime($lg['created_at'])); ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars($lg['item_name'] ?? '-'); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase <?= $lg['type'] === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>">
                                    <?= $lg['type'] === 'in' ? 'Masuk' : 'Pakai'; ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold"><?= ($lg['type'] === 'in' ? '+' : '-') . (float)$lg['change_qty'] . ' ' . htmlspecialchars($lg['unit'] ?? ''); ?></td>
                            <td class="py-3 px-4 text-slate-500"><?= htmlspecialchars($lg['notes']); ?></td>
                            <td class="py-3 px-4 text-slate-600"><?= htmlspecialchars($lg['staff_name'] ?? 'Staf'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-400 italic">Belum ada riwayat perubahan stok.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Add Item -->
    <div x-show="showAddModal" style="display:none;" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div @click.away="showAddModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="font-black text-base text-slate-800">Tambah Bahan Habis Pakai</h3>
                <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="" method="POST" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-500 uppercase mb-1">Nama Bahan *</label>
                    <input type="text" name="item_name" required placeholder="Contoh: Pewangi Akasia Premium" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-500 uppercase mb-1">Kategori</label>
                        <select name="category" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                            <option value="Bahan Cuci">Bahan Cuci</option>
                            <option value="Pewangi">Pewangi</option>
                            <option value="Kemasan">Kemasan</option>
                            <option value="Operasional">Operasional</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-500 uppercase mb-1">Satuan</label>
                        <input type="text" name="unit" value="Liter" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-500 uppercase mb-1">Stok Awal</label>
                        <input type="number" name="stock_qty" step="0.5" min="0" value="10" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-500 uppercase mb-1">Batas Minimal Alert</label>
                        <input type="number" name="min_stock" step="0.5" min="0" value="3" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono font-bold">
                    </div>
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2 text-slate-600 font-bold rounded-xl hover:bg-slate-100">Batal</button>
                    <button type="submit" name="tambah_item" class="px-5 py-2.5 btn-brand font-bold rounded-xl shadow-md">Simpan Bahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Adjust Stock -->
    <div x-show="showAdjustModal" style="display:none;" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div @click.away="showAdjustModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="font-black text-base text-slate-800" x-text="(adjType === 'in' ? 'Restok Masuk: ' : 'Pemakaian: ') + (adjItem.item_name || '')"></h3>
                <button type="button" @click="showAdjustModal = false" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="" method="POST" class="space-y-3 text-xs">
                <input type="hidden" name="item_id" :value="adjItem.id">
                <input type="hidden" name="type" :value="adjType">
                <div>
                    <label class="block font-bold text-slate-500 uppercase mb-1">Jumlah (<span x-text="adjItem.unit"></span>)</label>
                    <input type="number" name="amount" step="0.5" min="0.5" value="1" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-base font-mono font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 uppercase mb-1">Catatan</label>
                    <input type="text" name="notes" :placeholder="adjType === 'in' ? 'Contoh: Beli di toko kimia' : 'Contoh: Dipakai cuci harian'" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium">
                </div>
                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" @click="showAdjustModal = false" class="px-4 py-2 text-slate-600 font-bold rounded-xl hover:bg-slate-100">Batal</button>
                    <button type="submit" name="adjust_stock"
                            :class="adjType === 'in' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
                            class="px-5 py-2.5 text-white font-bold rounded-xl shadow-md">
                        Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once '../layout/footer.php'; ?>
