<?php
// admin/index.php - Operational Dashboard for Admin/Cashier
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../layout/header.php';

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// 1. Consolidated Operational Stats (Single Query Optimization)
$q_stats = mysqli_query($conn, "SELECT 
    COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as count_pending,
    COALESCE(SUM(CASE WHEN status IN ('processing', 'ironing') THEN 1 ELSE 0 END), 0) as count_processing,
    COALESCE(SUM(CASE WHEN status='ready' THEN 1 ELSE 0 END), 0) as count_ready,
    COALESCE(SUM(CASE WHEN DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_count,
    COALESCE(SUM(CASE WHEN DATE(created_at) = '$today' THEN total_amount ELSE 0 END), 0) as today_total
    FROM orders");
$stats_row = mysqli_fetch_assoc($q_stats);

$count_pending = (int)($stats_row['count_pending'] ?? 0);
$count_processing = (int)($stats_row['count_processing'] ?? 0);
$count_ready = (int)($stats_row['count_ready'] ?? 0);
$today_stat = [
    'c' => (int)($stats_row['today_count'] ?? 0),
    'total' => (float)($stats_row['today_total'] ?? 0)
];

// 2. Orders Due Today or Overdue (Jatuh tempo)
$q_due = mysqli_query($conn, "SELECT * FROM orders 
    WHERE status IN ('pending', 'processing', 'ironing') 
    AND estimate_completed_at IS NOT NULL 
    AND estimate_completed_at <= DATE_ADD('$now', INTERVAL 12 HOUR)
    ORDER BY estimate_completed_at ASC LIMIT 5");

// 3. Pending Pickup Requests
$q_pickup = mysqli_query($conn, "SELECT * FROM pickup_requests WHERE status='pending' ORDER BY created_at DESC LIMIT 3");
$count_pickup_pending = mysqli_num_rows($q_pickup);

// 4. Queue of active laundry today
$q_queue = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.admin_id = u.id 
    WHERE o.status != 'completed' 
    ORDER BY FIELD(o.status, 'pending', 'processing', 'ironing', 'ready'), o.created_at ASC 
    LIMIT 10");
?>

<!-- Operational Top Header -->
<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0 font-bold">
            <i class="fas fa-desktop"></i>
        </div>
        <div>
            <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Dashboard Operasional Harian</span>
            <h1 class="text-xl sm:text-2xl font-black text-slate-800">Halo, <?= htmlspecialchars($_SESSION['name']); ?></h1>
            <p class="text-xs text-slate-400 mt-0.5"><i class="far fa-calendar-alt mr-1"></i><?= date('l, d F Y'); ?></p>
        </div>
    </div>
    <div class="flex gap-2 w-full sm:w-auto">
        <a href="pos.php" class="flex-1 sm:flex-initial px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-100 transition flex items-center justify-center gap-2">
            <i class="fas fa-plus-circle"></i> Input Pesanan Baru (POS)
        </a>
    </div>
</div>

<!-- Alert for Pending Pickup Requests if any -->
<?php if ($count_pickup_pending > 0): ?>
<div class="mb-6 bg-amber-50 border border-amber-200 text-amber-900 p-4 sm:p-5 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-200 text-amber-800 flex items-center justify-center shrink-0 text-base">
            <i class="fas fa-motorcycle"></i>
        </div>
        <div>
            <h4 class="font-bold text-sm">Ada <?= $count_pickup_pending; ?> Permintaan Jemput (Pickup) Baru dari Pelanggan!</h4>
            <p class="text-xs text-amber-700">Segera konfirmasi dan jemput cucian ke alamat pelanggan.</p>
        </div>
    </div>
    <a href="pickup.php" class="w-full sm:w-auto text-center px-4 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl transition whitespace-nowrap shadow-sm">
        Buka Permintaan Pickup &rarr;
    </a>
</div>
<?php endif; ?>

<!-- Quick Stat Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Antrean Baru</p>
            <h3 class="text-2xl font-black text-slate-800 mt-1"><?= $count_pending; ?></h3>
            <span class="text-[10px] text-amber-600 font-bold">Menunggu Dicuci</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg shrink-0">
            <i class="fas fa-hourglass-start"></i>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Sedang Dikerjakan</p>
            <h3 class="text-2xl font-black text-slate-800 mt-1"><?= $count_processing; ?></h3>
            <span class="text-[10px] text-indigo-600 font-bold">Cuci & Setrika</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shrink-0">
            <i class="fas fa-tshirt"></i>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Siap Diambil</p>
            <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= $count_ready; ?></h3>
            <span class="text-[10px] text-emerald-600 font-bold">Menunggu Pelanggan</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shrink-0">
            <i class="fas fa-check-circle"></i>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Transaksi Hari Ini</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1"><?= (int)$today_stat['c']; ?></h3>
            <span class="text-[10px] text-slate-500 font-bold">Rp <?= number_format($today_stat['total'], 0, ',', '.'); ?></span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg shrink-0">
            <i class="fas fa-receipt"></i>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <!-- Active Queue Table (Col 2) -->
    <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h3 class="font-extrabold text-slate-800 text-base">Antrean Cucian Aktif</h3>
                <p class="text-xs text-slate-400">Pekerjaan yang belum selesai atau sedang menunggu diambil</p>
            </div>
            <a href="pesanan.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                Kelola Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Nota</th>
                        <th class="px-5 py-3.5">Pelanggan</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Bayar</th>
                        <th class="px-5 py-3.5 text-right">Update Status Cepat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $has_queue = false;
                    while($q = mysqli_fetch_assoc($q_queue)): 
                        $has_queue = true;
                        $st = $q['status'];
                    ?>
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-5 py-3.5 font-mono font-bold text-indigo-600 text-xs">
                            <?= htmlspecialchars($q['order_code']); ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($q['customer_name']); ?></p>
                            <p class="text-[11px] text-slate-400"><?= htmlspecialchars($q['customer_phone'] ?? '-'); ?></p>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php
                            $badge = 'bg-slate-100 text-slate-600';
                            $lbl = 'Menunggu';
                            if ($st === 'processing') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Dicuci'; }
                            elseif ($st === 'ironing') { $badge = 'bg-purple-100 text-purple-800'; $lbl = 'Disetrika'; }
                            elseif ($st === 'ready') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Selesai'; }
                            ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase <?= $badge; ?>">
                                <?= $lbl; ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <?php if ($q['payment_status'] === 'paid'): ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700">Lunas</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-50 text-rose-700">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="inline-flex gap-1">
                                <?php if ($st === 'pending'): ?>
                                    <a href="update_status.php?id=<?= $q['id']; ?>&status=processing&redirect=index" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition">
                                        Mulai Cuci
                                    </a>
                                <?php elseif ($st === 'processing'): ?>
                                    <a href="update_status.php?id=<?= $q['id']; ?>&status=ironing&redirect=index" class="px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-xs font-bold transition">
                                        Setrika
                                    </a>
                                <?php elseif ($st === 'ironing'): ?>
                                    <a href="update_status.php?id=<?= $q['id']; ?>&status=ready&redirect=index" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition">
                                        Selesai (Siap)
                                    </a>
                                <?php elseif ($st === 'ready'): ?>
                                    <a href="update_status.php?id=<?= $q['id']; ?>&status=completed&redirect=index" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition">
                                        Serahkan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_queue): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-xs">
                            <i class="fas fa-check-circle text-2xl text-emerald-400 block mb-2"></i>
                            Tidak ada antrean cucian aktif saat ini. Semua pesanan sudah selesai!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Due Today / Jatuh Tempo Alert (Col 1) -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 flex flex-col">
        <div class="mb-4">
            <h3 class="font-extrabold text-slate-800 text-base flex items-center gap-2">
                <i class="fas fa-bell text-rose-500"></i> Cucian Jatuh Tempo
            </h3>
            <p class="text-xs text-slate-400">Pesanan yang harus selesai hari ini</p>
        </div>

        <div class="space-y-3 flex-1 overflow-y-auto">
            <?php 
            $has_due = false;
            while($d = mysqli_fetch_assoc($q_due)): 
                $has_due = true;
                $is_overdue = strtotime($d['estimate_completed_at']) < time();
            ?>
            <div class="p-3.5 rounded-2xl border <?= $is_overdue ? 'bg-rose-50/60 border-rose-200' : 'bg-slate-50 border-slate-100'; ?>">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="font-mono text-xs font-bold <?= $is_overdue ? 'text-rose-600' : 'text-indigo-600'; ?>">
                            <?= htmlspecialchars($d['order_code']); ?>
                        </span>
                        <h4 class="text-xs font-bold text-slate-800 mt-0.5"><?= htmlspecialchars($d['customer_name']); ?></h4>
                    </div>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded <?= $is_overdue ? 'bg-rose-200 text-rose-800' : 'bg-amber-100 text-amber-800'; ?>">
                        <?= $is_overdue ? 'Lewat Batas' : 'Hari Ini'; ?>
                    </span>
                </div>
                <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-200/60 text-[11px] text-slate-500">
                    <span>Target: <?= date('d/m H:i', strtotime($d['estimate_completed_at'])); ?></span>
                    <a href="update_status.php?id=<?= $d['id']; ?>&status=ready&redirect=index" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                        Tandai Selesai &rarr;
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
            <?php if (!$has_due): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i class="fas fa-shield-heart text-2xl text-indigo-300 block mb-2"></i>
                    Bagus! Tidak ada cucian yang melewati jatuh tempo saat ini.
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100">
            <a href="pesanan.php" class="block w-full py-2.5 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl text-center transition">
                Buka Seluruh Antrean Cucian
            </a>
        </div>
    </div>

</div>

<?php require_once '../layout/footer.php'; ?>