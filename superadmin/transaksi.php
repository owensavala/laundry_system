<?php
// superadmin/transaksi.php - All Transactions Monitor for Superadmin
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

require_once '../layout/header.php';

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$where = "WHERE 1=1";

if (!empty($search)) {
    $s_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_code LIKE '%$s_safe%' OR o.customer_name LIKE '%$s_safe%' OR o.customer_phone LIKE '%$s_safe%')";
}
if (!empty($status_filter)) {
    $st_safe = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND o.status = '$st_safe'";
}

// Delete
if (isset($_GET['hapus'])) {
    $del_id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM order_details WHERE order_id='$del_id'");
    mysqli_query($conn, "DELETE FROM orders WHERE id='$del_id'");
    echo "<script>alert('Transaksi berhasil dihapus!'); window.location='transaksi.php';</script>";
    exit();
}

// Pagination & Query
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$q_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders o $where");
$total_rows = (int)(mysqli_fetch_assoc($q_count)['total'] ?? 0);
$total_pages = max(1, ceil($total_rows / $per_page));

$orders = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.admin_id = u.id 
    $where 
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset");
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Semua Transaksi & Pesanan</h1>
        <p class="text-slate-400 text-sm">Monitoring lengkap seluruh pesanan masuk, antrean cucian, dan status pembayaran.</p>
    </div>
    <a href="../admin/pos.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-100 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Buat Pesanan Baru (POS)
    </a>
</div>

<!-- Search & Filter Bar -->
<div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mb-6">
    <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
        <div class="sm:col-span-2 relative">
            <i class="fas fa-search absolute left-4 top-3.5 text-slate-400 text-sm"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari Kode Nota (misal: LND-...), Nama Pelanggan, atau No. HP..." class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex gap-2">
            <select name="status" class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Semua Status Cucian</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : ''; ?>>Menunggu Antrean</option>
                <option value="processing" <?= $status_filter === 'processing' ? 'selected' : ''; ?>>Sedang Dicuci</option>
                <option value="ironing" <?= $status_filter === 'ironing' ? 'selected' : ''; ?>>Sedang Disetrika</option>
                <option value="ready" <?= $status_filter === 'ready' ? 'selected' : ''; ?>>Selesai (Siap Diambil)</option>
                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : ''; ?>>Sudah Diambil</option>
            </select>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-xl font-bold text-sm transition">
                Filter
            </button>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="transaksi.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-3 rounded-xl font-bold text-sm transition flex items-center justify-center">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Kode Nota</th>
                    <th class="px-6 py-4">Pelanggan & Kontak</th>
                    <th class="px-6 py-4">Waktu Masuk</th>
                    <th class="px-6 py-4">Kasir</th>
                    <th class="px-6 py-4 text-center">Status Cucian</th>
                    <th class="px-6 py-4 text-center">Pembayaran</th>
                    <th class="px-6 py-4 text-right">Total</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php 
                $count = 0;
                while($row = mysqli_fetch_assoc($orders)): 
                    $count++;
                    $st = $row['status'];
                    $badge = 'bg-slate-100 text-slate-600';
                    $lbl = 'Menunggu';
                    if ($st === 'processing') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Dicuci'; }
                    elseif ($st === 'ironing') { $badge = 'bg-purple-100 text-purple-800'; $lbl = 'Disetrika'; }
                    elseif ($st === 'ready') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Selesai'; }
                    elseif ($st === 'completed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Sudah Diambil'; }
                ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 font-mono font-bold text-indigo-600">
                        <?= htmlspecialchars($row['order_code']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($row['customer_name']); ?></p>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($row['customer_phone'] ?? '-'); ?></p>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d/m/y H:i', strtotime($row['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-600 font-medium">
                        <?= htmlspecialchars($row['cashier_name'] ?? 'Kasir'); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase <?= $badge; ?>">
                            <?= $lbl; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($row['payment_status'] === 'paid'): ?>
                            <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Lunas (<?= htmlspecialchars($row['payment_method']); ?>)
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200">
                                Belum Lunas
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-slate-900 font-mono">
                        Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="../admin/cetak_nota.php?id=<?= $row['id']; ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 transition flex items-center justify-center text-xs" title="Cetak Struk">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="?hapus=<?= $row['id']; ?>" onclick="return confirm('Hapus transaksi <?= $row['order_code']; ?>?')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 transition flex items-center justify-center text-xs" title="Hapus Transaksi">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($count === 0): ?>
                <tr>
                    <td colspan="8" class="px-6 py-10 text-center text-slate-400">
                        Tidak ada transaksi yang cocok dengan kriteria pencarian.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500">
        <div>
            Menampilkan data <strong><?= min($offset + 1, $total_rows); ?></strong> - <strong><?= min($offset + $per_page, $total_rows); ?></strong> dari <strong><?= number_format($total_rows); ?></strong> transaksi
        </div>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">&larr; Prev</a>
            <?php endif; ?>
            
            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <a href="?page=<?= $p; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>" class="px-3 py-1.5 rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-slate-100 hover:bg-slate-200 font-medium'; ?> transition"><?= $p; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status_filter); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../layout/footer.php'; ?>
