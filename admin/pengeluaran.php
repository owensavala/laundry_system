<?php
// admin/pengeluaran.php - Operational Expenses Management
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

$msg = '';
$err = '';

// Handle add expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengeluaran'])) {
    $ket = trim($_POST['keterangan'] ?? '');
    $jml = (float)($_POST['jumlah_biaya'] ?? 0);
    $uid = $_SESSION['user_id'] ?? 1;

    if (!empty($ket) && $jml > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pengeluaran (keterangan, jumlah_biaya, id_user, created_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            $stmt = mysqli_prepare($conn, "INSERT INTO pengeluaran (keterangan, jumlah_biaya, id_user) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sdi", $ket, $jml, $uid);
        } else {
            mysqli_stmt_bind_param($stmt, "sdi", $ket, $jml, $uid);
        }

        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Biaya operasional berhasil dicatat!';
        } else {
            $err = 'Gagal menyimpan: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Keterangan dan nominal biaya wajib diisi.';
    }
}

// Handle delete expense
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM pengeluaran WHERE id_pengeluaran='$id'");
    $msg = 'Data pengeluaran berhasil dihapus!';
}

require_once '../layout/header.php';

// Fetch recent expenses safely
$expenses = mysqli_query($conn, "SELECT p.*, u.name as user_name 
    FROM pengeluaran p 
    LEFT JOIN users u ON p.id_user = u.id 
    ORDER BY p.id_pengeluaran DESC LIMIT 30");

// Total today & this month
$today = date('Y-m-d');
$month_start = date('Y-m-01');

$q_tot_today = mysqli_query($conn, "SELECT SUM(jumlah_biaya) as t FROM pengeluaran WHERE DATE(COALESCE(created_at, NOW())) = '$today'");
$tot_today = (float)(mysqli_fetch_assoc($q_tot_today)['t'] ?? 0);

$q_tot_month = mysqli_query($conn, "SELECT SUM(jumlah_biaya) as t FROM pengeluaran WHERE DATE(COALESCE(created_at, NOW())) >= '$month_start'");
$tot_month = (float)(mysqli_fetch_assoc($q_tot_month)['t'] ?? 0);
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Biaya Operasional Toko</h1>
        <p class="text-slate-400 text-sm">Pencatatan pengeluaran harian seperti sabun, parfum, plastik, dan token listrik.</p>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= $msg; ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-red-500"></i>
        <span><?= $err; ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Input Form (Col 1) -->
    <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 h-fit space-y-4">
        <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-3">
            <i class="fas fa-receipt text-rose-500"></i> Catat Pengeluaran Baru
        </h3>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Keterangan Biaya *</label>
                <input type="text" name="keterangan" required placeholder="Contoh: Sabun Cuci 5L / Token Listrik" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Jumlah Biaya (Rp) *</label>
                <input type="number" name="jumlah_biaya" required min="500" step="500" placeholder="50000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <button type="submit" name="tambah_pengeluaran" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-extrabold py-3.5 rounded-xl shadow-lg shadow-rose-100 transition flex items-center justify-center gap-2">
                <i class="fas fa-plus-circle"></i>
                <span>Simpan Pengeluaran</span>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-100 space-y-2 text-xs">
            <div class="flex justify-between items-center text-slate-500">
                <span>Pengeluaran Hari Ini:</span>
                <span class="font-bold text-rose-600">Rp <?= number_format($tot_today, 0, ',', '.'); ?></span>
            </div>
            <div class="flex justify-between items-center text-slate-500">
                <span>Pengeluaran Bulan Ini:</span>
                <span class="font-bold text-rose-700">Rp <?= number_format($tot_month, 0, ',', '.'); ?></span>
            </div>
        </div>
    </div>

    <!-- Table of Recent Expenses (Col 2) -->
    <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-extrabold text-slate-800 text-base">Riwayat Pengeluaran Terakhir</h3>
            <span class="text-xs text-slate-400">30 Catatan Terakhir</span>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Tanggal & Waktu</th>
                        <th class="px-6 py-4">Keterangan Biaya</th>
                        <th class="px-6 py-4">Petugas</th>
                        <th class="px-6 py-4 text-right">Nominal (Rp)</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $count = 0;
                    while($e = mysqli_fetch_assoc($expenses)): 
                        $count++;
                        $tgl_display = !empty($e['created_at']) ? date('d/m/y H:i', strtotime($e['created_at'])) : date('d/m/y');
                    ?>
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">
                            <?= $tgl_display; ?>
                        </td>
                        <td class="px-6 py-4 font-semibold text-slate-800">
                            <?= htmlspecialchars($e['keterangan']); ?>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            <?= htmlspecialchars($e['user_name'] ?? 'Petugas'); ?>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-rose-600">
                            Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.'); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <a href="?hapus=<?= $e['id_pengeluaran']; ?>" onclick="return confirm('Hapus catatan pengeluaran ini?')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-500 hover:text-rose-600 inline-flex items-center justify-center text-xs transition">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($count === 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-slate-400">
                            Belum ada catatan pengeluaran operasional.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once '../layout/footer.php'; ?>