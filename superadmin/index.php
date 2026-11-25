<?php
// superadmin/index.php - Executive Dashboard & Business Intelligence
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

$set = get_settings($conn);
$msg = '';
$err = '';

// Handle Quick Expense Submission from Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengeluaran'])) {
    $ket = trim($_POST['keterangan'] ?? '');
    $biaya = max(0, (float)($_POST['jumlah_biaya'] ?? 0));
    $user_id = $_SESSION['user_id'] ?? 1;

    if (!empty($ket) && $biaya > 0) {
        $stmt_exp = mysqli_prepare($conn, "INSERT INTO pengeluaran (keterangan, jumlah_biaya, id_user, created_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt_exp, "sdi", $ket, $biaya, $user_id);
        if (mysqli_stmt_execute($stmt_exp)) {
            $msg = 'Pengeluaran operasional berhasil dicatat!';
        } else {
            $err = 'Gagal menyimpan pengeluaran: ' . mysqli_error($conn);
        }
    } else {
        $err = 'Keterangan dan nominal pengeluaran wajib diisi!';
    }
}

// Date helpers
$today = date('Y-m-d');
$month_start = date('Y-m-01');

// 1. Consolidated Financial & Order Stats
$q_stats = mysqli_query($conn, "SELECT 
    COALESCE(SUM(CASE WHEN DATE(created_at) = '$today' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_today,
    COALESCE(SUM(CASE WHEN DATE(created_at) = '$today' AND payment_status='paid' THEN 1 ELSE 0 END), 0) as tx_today,
    COALESCE(SUM(CASE WHEN DATE(created_at) >= '$month_start' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_month,
    COALESCE(SUM(CASE WHEN DATE(created_at) >= '$month_start' AND payment_status='paid' THEN 1 ELSE 0 END), 0) as tx_month,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_all,
    COUNT(*) as total_orders,
    COALESCE(SUM(CASE WHEN status IN ('pending', 'processing', 'ironing') THEN 1 ELSE 0 END), 0) as active_orders,
    COALESCE(SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END), 0) as ready_orders,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders,
    COALESCE(SUM(CASE WHEN speed = 'express' THEN 1 ELSE 0 END), 0) as express_orders
    FROM orders");
$order_stats = mysqli_fetch_assoc($q_stats);

$income_today = (float)($order_stats['income_today'] ?? 0);
$tx_today = (int)($order_stats['tx_today'] ?? 0);
$income_month = (float)($order_stats['income_month'] ?? 0);
$tx_month = (int)($order_stats['tx_month'] ?? 0);
$income_all = (float)($order_stats['income_all'] ?? 0);

// Expenses
$q_exp = mysqli_query($conn, "SELECT SUM(jumlah_biaya) as total FROM pengeluaran WHERE DATE(COALESCE(created_at, NOW())) >= '$month_start'");
$expense_month = (float)(mysqli_fetch_assoc($q_exp)['total'] ?? 0);
$profit_month = $income_month - $expense_month;

// 2. Chart: 7-day revenue & order count
$chart_start = date('Y-m-d', strtotime('-6 days'));
$chart_data_raw = [];
$chart_tx_raw = [];
$q_7d = mysqli_query($conn, "SELECT DATE(created_at) as dt, COALESCE(SUM(total_amount), 0) as total, COUNT(*) as tx_count 
    FROM orders 
    WHERE DATE(created_at) >= '$chart_start' AND payment_status='paid' 
    GROUP BY DATE(created_at)");
if ($q_7d) {
    while ($row7 = mysqli_fetch_assoc($q_7d)) {
        $chart_data_raw[$row7['dt']] = (float)$row7['total'];
        $chart_tx_raw[$row7['dt']] = (int)$row7['tx_count'];
    }
}

$chart_labels = [];
$chart_incomes = [];
$chart_txs = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($d));
    $chart_incomes[] = $chart_data_raw[$d] ?? 0;
    $chart_txs[] = $chart_tx_raw[$d] ?? 0;
}

// 3. Cashier Leaderboard Performance
$q_cashier = mysqli_query($conn, "SELECT u.name, u.email, COUNT(o.id) as total_tx, COALESCE(SUM(o.total_amount), 0) as total_omset
    FROM users u
    LEFT JOIN orders o ON u.id = o.admin_id
    WHERE u.role IN ('admin', 'superadmin')
    GROUP BY u.id
    ORDER BY total_omset DESC");

$cashier_list = [];
$max_omset = 1;
while ($c = mysqli_fetch_assoc($q_cashier)) {
    $c['total_omset'] = (float)$c['total_omset'];
    $c['total_tx'] = (int)$c['total_tx'];
    if ($c['total_omset'] > $max_omset) $max_omset = $c['total_omset'];
    $cashier_list[] = $c;
}

// 4. Top 5 Layanan Terlaris
$q_top_services = mysqli_query($conn, "SELECT s.service_name, s.unit, COALESCE(SUM(od.qty), 0) as total_qty, COALESCE(SUM(od.subtotal), 0) as total_omset 
    FROM order_details od 
    JOIN services s ON od.service_id = s.id 
    GROUP BY s.id 
    ORDER BY total_omset DESC 
    LIMIT 5");
$service_labels = [];
$service_omsets = [];
$service_quantities = [];
while($ts = mysqli_fetch_assoc($q_top_services)) {
    $service_labels[] = $ts['service_name'];
    $service_omsets[] = (float)$ts['total_omset'];
    $service_quantities[] = (float)$ts['total_qty'] . ' ' . $ts['unit'];
}

// 5. Payment Methods Distribution
$q_pay_methods = mysqli_query($conn, "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    WHERE payment_status = 'paid' 
    GROUP BY payment_method");
$pay_labels = [];
$pay_counts = [];
while($pm = mysqli_fetch_assoc($q_pay_methods)) {
    $pay_labels[] = !empty($pm['payment_method']) ? $pm['payment_method'] : 'Tunai';
    $pay_counts[] = (int)$pm['count'];
}

// 6. Recent Orders
$q_recent = mysqli_query($conn, "SELECT o.*, u.name as customer_acc_name 
    FROM orders o 
    LEFT JOIN users u ON o.customer_id = u.id 
    ORDER BY o.created_at DESC LIMIT 8");

// 7. Average SLA Turnaround Time
$q_turnaround = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(ready_at, completed_at))) as avg_hours 
    FROM orders 
    WHERE (ready_at IS NOT NULL OR completed_at IS NOT NULL)");
$avg_hours_val = mysqli_fetch_assoc($q_turnaround)['avg_hours'];
$avg_turnaround_hours = $avg_hours_val ? round((float)$avg_hours_val, 1) : 24.0;

require_once '../layout/header.php';
?>

<div x-data="{ showExpenseModal: false }" class="max-w-7xl mx-auto pb-16 space-y-8">

    <!-- Executive Hero Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-tr from-slate-950 via-indigo-950 to-indigo-900 text-white p-6 sm:p-8 shadow-xl border border-indigo-900/60">
        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[11px] font-extrabold uppercase bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 tracking-wider">
                        Superadmin & Business Intelligence
                    </span>
                    <span class="text-xs text-indigo-300 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Analytics
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">
                    Ringkasan Bisnis, <?= htmlspecialchars($_SESSION['name'] ?? 'Owner'); ?> 👋
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 max-w-2xl">
                    Pantau kinerja operasional harian, rekapitulasi laba-rugi, efisiensi pengerjaan cuci, dan produktivitas tim kasir secara real-time.
                </p>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                <button type="button" @click="showExpenseModal = true; window.playTone('pop')" 
                        class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 backdrop-blur-md border border-white/10 shadow-xs">
                    <i class="fas fa-file-invoice-dollar text-amber-300"></i> Catat Biaya
                </button>

                <a href="laporan.php" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 backdrop-blur-md border border-white/10 shadow-xs">
                    <i class="fas fa-chart-pie text-sky-300"></i> Laporan Keuangan
                </a>

                <a href="../admin/pos.php" class="px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-violet-500 hover:from-indigo-600 hover:to-violet-600 text-white rounded-xl text-xs font-black transition flex items-center gap-2 shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-plus"></i> Transaksi Kasir
                </a>
            </div>
        </div>

        <!-- Decorative background glow blobs -->
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute left-1/3 -top-12 w-48 h-48 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Alert Banners -->
    <?php if ($msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm p-4 rounded-2xl flex items-center gap-2.5 shadow-xs">
            <i class="fas fa-circle-check text-emerald-500 text-base"></i>
            <span><?= $msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 text-xs sm:text-sm p-4 rounded-2xl flex items-center gap-2.5 shadow-xs">
            <i class="fas fa-circle-exclamation text-red-500 text-base"></i>
            <span><?= $err; ?></span>
        </div>
    <?php endif; ?>

    <!-- 4 High-Impact KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        
        <!-- Card 1: Omset Hari Ini -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Omset Hari Ini</span>
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shadow-2xs">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
            <div>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    Rp <?= number_format($income_today, 0, ',', '.'); ?>
                </h3>
                <p class="text-[11px] font-bold text-emerald-700 mt-1 flex items-center gap-1">
                    <i class="fas fa-arrow-trend-up"></i>
                    <span><?= $tx_today; ?> Transaksi Lunas Hari Ini</span>
                </p>
            </div>
        </div>

        <!-- Card 2: Omset Bulan Ini -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Omset Bulan Ini</span>
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold shadow-2xs">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    Rp <?= number_format($income_month, 0, ',', '.'); ?>
                </h3>
                <p class="text-[11px] font-bold text-indigo-700 mt-1 flex items-center gap-1">
                    <i class="fas fa-calendar-check"></i>
                    <span><?= date('F Y'); ?> &bull; <?= $tx_month; ?> Transaksi</span>
                </p>
            </div>
        </div>

        <!-- Card 3: Laba Bersih Bulan Ini -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Laba Bersih Bulan Ini</span>
                <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold shadow-2xs">
                    <i class="fas fa-sack-dollar"></i>
                </div>
            </div>
            <div>
                <h3 class="text-2xl sm:text-3xl font-black font-mono tracking-tight <?= $profit_month >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>">
                    Rp <?= number_format($profit_month, 0, ',', '.'); ?>
                </h3>
                <p class="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                    <span>Pengeluaran:</span>
                    <strong class="text-rose-600 font-mono">Rp <?= number_format($expense_month, 0, ',', '.'); ?></strong>
                </p>
            </div>
        </div>

        <!-- Card 4: Antrean & SLA Pengerjaan -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Operasional Antrean</span>
                <div class="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-bold shadow-2xs">
                    <i class="fas fa-shirt"></i>
                </div>
            </div>
            <div>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    <?= (int)($order_stats['active_orders'] ?? 0); ?> <span class="text-base font-bold text-slate-400">Cucian</span>
                </h3>
                <p class="text-[11px] font-bold text-sky-700 mt-1 flex items-center justify-between">
                    <span><?= (int)($order_stats['ready_orders'] ?? 0); ?> Siap Diambil</span>
                    <span class="text-slate-400 font-normal">SLA: ~<?= $avg_turnaround_hours; ?> Jam</span>
                </p>
            </div>
        </div>

    </div>

    <!-- Charts & Analytics Dual Row -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- 7-Day Revenue & Transaction Area Chart (8 cols) -->
        <div class="lg:col-span-8 bg-white p-6 sm:p-7 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-chart-line text-indigo-600"></i> Tren Omset 7 Hari Terakhir
                    </h2>
                    <p class="text-xs text-slate-400">Pertumbuhan pendapatan transaksi lunas harian</p>
                </div>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 font-mono font-extrabold text-xs rounded-xl border border-indigo-100">
                    Total: Rp <?= number_format(array_sum($chart_incomes), 0, ',', '.'); ?>
                </span>
            </div>

            <div class="h-72 w-full">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Cashier Hall of Fame / Leaderboard (4 cols) -->
        <div class="lg:col-span-4 bg-white p-6 sm:p-7 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between space-y-6">
            <div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-trophy text-amber-500"></i> Performa Kasir
                        </h2>
                        <p class="text-xs text-slate-400">Produktivitas dan omset per karyawan</p>
                    </div>
                </div>

                <div class="space-y-3.5">
                    <?php 
                    $rank = 1;
                    foreach ($cashier_list as $c): 
                        $pct = $max_omset > 0 ? round(($c['total_omset'] / $max_omset) * 100) : 0;
                    ?>
                    <div class="p-3.5 rounded-2xl bg-slate-50/80 border border-slate-200/70 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black shadow-2xs
                                    <?= $rank === 1 ? 'bg-amber-400 text-amber-950' : ($rank === 2 ? 'bg-slate-300 text-slate-800' : 'bg-orange-200 text-orange-900'); ?>">
                                    <?= $rank++; ?>
                                </span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-800 leading-tight"><?= htmlspecialchars($c['name']); ?></h4>
                                    <span class="text-[10px] text-slate-400"><?= $c['total_tx']; ?> Transaksi</span>
                                </div>
                            </div>
                            <span class="font-mono font-black text-xs text-indigo-700">
                                Rp <?= number_format($c['total_omset'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        
                        <!-- Progress bar -->
                        <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-indigo-600 h-full rounded-full" style="width: <?= $pct; ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 text-center">
                <a href="karyawan.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                    <span>Kelola Akun & Hak Akses Karyawan</span>
                    <i class="fas fa-arrow-right text-[10px]"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- Secondary Analytics Row: Top Services & Payment Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Top Services Horizontal Bar Chart -->
        <div class="bg-white p-6 sm:p-7 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-tags text-indigo-600"></i> Top 5 Layanan Terlaris
                    </h2>
                    <p class="text-xs text-slate-400">Layanan dengan kontribusi omset tertinggi</p>
                </div>
                <a href="layanan.php" class="text-xs font-bold text-indigo-600 hover:underline">Kelola &rarr;</a>
            </div>

            <div class="h-64">
                <canvas id="serviceChart"></canvas>
            </div>
        </div>

        <!-- Payment Method & Turnaround Time -->
        <div class="bg-white p-6 sm:p-7 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-credit-card text-emerald-600"></i> Metode Pembayaran Pelanggan
                    </h2>
                    <p class="text-xs text-slate-400">Preferensi transaksi: Tunai, QRIS, & Transfer</p>
                </div>
                <a href="laporan.php" class="text-xs font-bold text-indigo-600 hover:underline">Laporan &rarr;</a>
            </div>

            <div class="h-64 flex items-center justify-center">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Recent Transactions Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-receipt text-indigo-600"></i> Transaksi Terkini
                </h2>
                <p class="text-xs text-slate-400">Order yang baru saja masuk dan diproses kasir</p>
            </div>
            <a href="transaksi.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                Lihat Semua &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-400 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-5">Kode Nota</th>
                        <th class="py-3.5 px-5">Pelanggan</th>
                        <th class="py-3.5 px-5">Waktu</th>
                        <th class="py-3.5 px-5 text-right">Total</th>
                        <th class="py-3.5 px-5 text-center">Status Cucian</th>
                        <th class="py-3.5 px-5 text-center">Pembayaran</th>
                        <th class="py-3.5 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <?php while($row = mysqli_fetch_assoc($q_recent)): ?>
                    <tr class="hover:bg-indigo-50/20 transition">
                        <td class="py-4 px-5 font-mono font-black text-indigo-700 whitespace-nowrap">
                            <?= htmlspecialchars($row['order_code']); ?>
                            <?php if(($row['speed'] ?? '') === 'express'): ?>
                                <span class="ml-1 text-[9px] px-1 py-0.5 rounded font-black bg-amber-100 text-amber-800">⚡</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-5">
                            <h4 class="font-extrabold text-slate-900"><?= htmlspecialchars($row['customer_name']); ?></h4>
                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars($row['customer_phone']); ?></span>
                        </td>
                        <td class="py-4 px-5 text-slate-500 whitespace-nowrap">
                            <?= date('d M, H:i', strtotime($row['created_at'])); ?>
                        </td>
                        <td class="py-4 px-5 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                            Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?>
                        </td>
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            <?php
                            $st = $row['status'];
                            $badge = 'bg-slate-100 text-slate-600';
                            $lbl = 'Menunggu';
                            if ($st === 'processing') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Dicuci'; }
                            elseif ($st === 'ironing') { $badge = 'bg-purple-100 text-purple-800'; $lbl = 'Disetrika'; }
                            elseif ($st === 'ready') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Selesai'; }
                            elseif ($st === 'completed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Sudah Diambil'; }
                            ?>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?= $badge; ?>">
                                <?= $lbl; ?>
                            </span>
                        </td>
                        <td class="py-4 px-5 text-center whitespace-nowrap">
                            <?php if ($row['payment_status'] === 'paid'): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Lunas (<?= htmlspecialchars($row['payment_method']); ?>)
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    Belum Lunas
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-5 text-right whitespace-nowrap">
                            <a href="../admin/cetak_nota.php?id=<?= $row['id']; ?>" target="_blank" 
                               class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-xs transition inline-flex items-center gap-1">
                                <i class="fas fa-print"></i> Struk
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Expense Recording Modal -->
    <div x-show="showExpenseModal" style="display: none;" 
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        
        <div @click.away="showExpenseModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-amber-500"></i> Catat Pengeluaran Operasional
                </h3>
                <button type="button" @click="showExpenseModal = false" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="tambah_pengeluaran" value="1">

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Keterangan Biaya *</label>
                    <input type="text" name="keterangan" required placeholder="Contoh: Beli Deterjen & Pewangi 10L, Listrik PLN..." 
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Jumlah Biaya (Rp) *</label>
                    <input type="number" name="jumlah_biaya" required min="1000" step="1000" placeholder="Contoh: 150000" 
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="showExpenseModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-black shadow-md shadow-amber-200 transition">
                        Simpan Biaya
                    </button>
                </div>
            </form>
        </div>

    </div>

</div>

<!-- Chart.js Modern Initializer -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Revenue 7-Day Area Chart
    const revCanvas = document.getElementById('revenueChart');
    if (revCanvas) {
        const ctx = revCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.35)');
        gradient.addColorStop(1, 'rgba(79, 70, 229, 0.00)');

        new Chart(revCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Omset Transaksi',
                    data: <?= json_encode($chart_incomes); ?>,
                    borderColor: '#4f46e5',
                    borderWidth: 3,
                    fill: true,
                    backgroundColor: gradient,
                    tension: 0.38,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' Omset: Rp ' + parseInt(ctx.raw).toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return 'Rp ' + (v/1000) + 'k'; }
                        },
                        grid: { color: 'rgba(241, 245, 249, 1)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Top Services Bar Chart
    const svcCanvas = document.getElementById('serviceChart');
    if (svcCanvas) {
        new Chart(svcCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($service_labels); ?>,
                datasets: [{
                    label: 'Total Omset',
                    data: <?= json_encode($service_omsets); ?>,
                    backgroundColor: [
                        '#4f46e5',
                        '#06b6d4',
                        '#10b981',
                        '#f59e0b',
                        '#ec4899'
                    ],
                    borderRadius: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' Omset: Rp ' + parseInt(ctx.raw).toLocaleString('id-ID'); }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return 'Rp ' + (v/1000) + 'k'; }
                        },
                        grid: { color: 'rgba(241, 245, 249, 1)' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 3. Payment Methods Doughnut Chart
    const payCanvas = document.getElementById('paymentChart');
    if (payCanvas) {
        new Chart(payCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pay_labels); ?>,
                datasets: [{
                    data: <?= json_encode($pay_counts); ?>,
                    backgroundColor: ['#10b981', '#6366f1', '#06b6d4', '#f59e0b', '#ec4899'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: { size: 11, weight: 'bold' }
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }
});
</script>

<?php require_once '../layout/footer.php'; ?>
