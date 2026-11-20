<?php
// superadmin/index.php - Dashboard Analytics for Owner
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

require_once '../layout/header.php';

// Date helpers
$today = date('Y-m-d');
$month_start = date('Y-m-01');

// 1. Consolidated Financial & Order Stats (Single Query Optimization)
$q_stats = mysqli_query($conn, "SELECT 
    COALESCE(SUM(CASE WHEN DATE(created_at) = '$today' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_today,
    COALESCE(SUM(CASE WHEN DATE(created_at) >= '$month_start' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_month,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END), 0) as income_all,
    COUNT(*) as total_orders,
    COALESCE(SUM(CASE WHEN status IN ('pending', 'processing', 'ironing') THEN 1 ELSE 0 END), 0) as active_orders,
    COALESCE(SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END), 0) as ready_orders,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders
    FROM orders");
$order_stats = mysqli_fetch_assoc($q_stats);

$income_today = (float)($order_stats['income_today'] ?? 0);
$income_month = (float)($order_stats['income_month'] ?? 0);
$income_all = (float)($order_stats['income_all'] ?? 0);

// Expenses
$q_exp = mysqli_query($conn, "SELECT SUM(jumlah_biaya) as total FROM pengeluaran WHERE DATE(COALESCE(created_at, NOW())) >= '$month_start'");
$expense_month = (float)(mysqli_fetch_assoc($q_exp)['total'] ?? 0);
$profit_month = $income_month - $expense_month;

// 3. Chart: 7-day revenue (Single Query Group By Optimization)
$chart_start = date('Y-m-d', strtotime('-6 days'));
$chart_data_raw = [];
$q_7d = mysqli_query($conn, "SELECT DATE(created_at) as dt, COALESCE(SUM(total_amount), 0) as total 
    FROM orders 
    WHERE DATE(created_at) >= '$chart_start' AND payment_status='paid' 
    GROUP BY DATE(created_at)");
if ($q_7d) {
    while ($row7 = mysqli_fetch_assoc($q_7d)) {
        $chart_data_raw[$row7['dt']] = (float)$row7['total'];
    }
}

$chart_labels = [];
$chart_incomes = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($d));
    $chart_incomes[] = $chart_data_raw[$d] ?? 0;
}

// 4. Cashier Performance
$q_cashier = mysqli_query($conn, "SELECT u.name, u.email, COUNT(o.id) as total_tx, COALESCE(SUM(o.total_amount), 0) as total_omset
    FROM users u
    LEFT JOIN orders o ON u.id = o.admin_id
    WHERE u.role IN ('admin', 'superadmin')
    GROUP BY u.id
    ORDER BY total_omset DESC");

// 5. Recent Orders
$q_recent = mysqli_query($conn, "SELECT o.*, u.name as customer_acc_name 
    FROM orders o 
    LEFT JOIN users u ON o.customer_id = u.id 
    ORDER BY o.created_at DESC LIMIT 8");

// 6. Top 5 Layanan Terlaris
$q_top_services = mysqli_query($conn, "SELECT s.service_name, COALESCE(SUM(od.qty), 0) as total_qty, COALESCE(SUM(od.subtotal), 0) as total_omset 
    FROM order_details od 
    JOIN services s ON od.service_id = s.id 
    GROUP BY s.id 
    ORDER BY total_omset DESC 
    LIMIT 5");
$service_labels = [];
$service_omsets = [];
while($ts = mysqli_fetch_assoc($q_top_services)) {
    $service_labels[] = $ts['service_name'];
    $service_omsets[] = (float)$ts['total_omset'];
}

// 7. Payment Methods Distribution
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

// 8. Average Turnaround Time
$q_turnaround = mysqli_query($conn, "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(ready_at, completed_at))) as avg_hours 
    FROM orders 
    WHERE (ready_at IS NOT NULL OR completed_at IS NOT NULL)");
$avg_hours_val = mysqli_fetch_assoc($q_turnaround)['avg_hours'];
$avg_turnaround_hours = $avg_hours_val ? round((float)$avg_hours_val, 1) : 24.0;
?>

<!-- Welcome Banner -->
<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-indigo-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl relative overflow-hidden">
    <div class="relative z-10">
        <span class="inline-block px-3 py-1 bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">
            Superadmin & Business Intelligence
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Selamat Datang, <?= htmlspecialchars($_SESSION['name']); ?>! 👋</h1>
        <p class="text-slate-300 text-sm mt-1">Pantau seluruh pergerakan omset, operasional cucian, dan performa tim kasir.</p>
    </div>
    <div class="flex flex-wrap gap-2 relative z-10 w-full sm:w-auto">
        <a href="laporan.php" class="flex-1 sm:flex-initial justify-center px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 backdrop-blur-md">
            <i class="fas fa-file-invoice"></i> Laporan Keuangan
        </a>
        <a href="../admin/pos.php" class="flex-1 sm:flex-initial justify-center px-4 py-2.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-indigo-500/30">
            <i class="fas fa-plus"></i> Transaksi Baru
        </a>
    </div>
    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-indigo-600/20 rounded-full blur-3xl"></div>
</div>

<!-- Key Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pendapatan Hari Ini</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">Rp <?= number_format($income_today, 0, ',', '.'); ?></h3>
            <p class="text-[11px] text-emerald-600 font-bold mt-1"><i class="fas fa-calendar-day mr-1"></i><?= date('d M Y'); ?></p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-coins"></i>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Pendapatan Bulan Ini</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">Rp <?= number_format($income_month, 0, ',', '.'); ?></h3>
            <p class="text-[11px] text-indigo-600 font-bold mt-1"><i class="fas fa-chart-line mr-1"></i>Periode <?= date('F Y'); ?></p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-wallet"></i>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Laba Bersih Bulan Ini</p>
            <h3 class="text-2xl font-black <?= $profit_month >= 0 ? 'text-emerald-700' : 'text-rose-600'; ?> mt-1">
                Rp <?= number_format($profit_month, 0, ',', '.'); ?>
            </h3>
            <p class="text-[11px] text-slate-400 mt-1">Biaya: Rp <?= number_format($expense_month, 0, ',', '.'); ?></p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-sack-dollar"></i>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cucian Aktif & Antrean</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1"><?= (int)($order_stats['active_orders'] ?? 0); ?> Order</h3>
            <p class="text-[11px] text-sky-600 font-bold mt-1"><?= (int)($order_stats['ready_orders'] ?? 0); ?> Siap Diambil</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-tshirt"></i>
        </div>
    </div>
</div>

<!-- Charts & Cashier Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Revenue Chart -->
    <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="font-extrabold text-slate-800 text-lg">Tren Pendapatan 7 Hari Terakhir</h3>
                <p class="text-xs text-slate-400">Total pemasukan transaksi lunas harian</p>
            </div>
            <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-bold">
                Total: Rp <?= number_format(array_sum($chart_incomes), 0, ',', '.'); ?>
            </span>
        </div>
        <div class="h-72">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Cashier Performance -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col">
        <h3 class="font-extrabold text-slate-800 text-lg mb-1">Performa Kasir & Tim</h3>
        <p class="text-xs text-slate-400 mb-4">Total pesanan dan omset per karyawan</p>

        <div class="space-y-4 flex-1 overflow-y-auto">
            <?php 
            $rank = 1;
            while($c = mysqli_fetch_assoc($q_cashier)): 
            ?>
            <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-xl <?= $rank == 1 ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-700'; ?> flex items-center justify-center text-xs font-black">
                        <?= $rank++; ?>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 leading-tight"><?= htmlspecialchars($c['name']); ?></h4>
                        <p class="text-[11px] text-slate-400"><?= (int)$c['total_tx']; ?> Transaksi</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs font-black text-indigo-600 block">Rp <?= number_format($c['total_omset'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <div class="mt-4 pt-4 border-t border-slate-100 text-center">
            <a href="karyawan.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
                Kelola Tim Karyawan &rarr;
            </a>
        </div>
    </div>
</div>

<!-- Business Deep-Dive Analytics (Top Layanan & Metode Bayar) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    
    <!-- Top Services Chart -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-extrabold text-slate-800 text-lg">Top 5 Layanan Terlaris</h3>
                    <p class="text-xs text-slate-400">Berdasarkan total perolehan omset transaksi</p>
                </div>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold">
                    <i class="fas fa-tags"></i>
                </span>
            </div>
            <div class="h-64">
                <canvas id="serviceChart"></canvas>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span><i class="fas fa-info-circle text-indigo-500 mr-1"></i> Data dihitung dari seluruh detail item pesanan</span>
            <a href="layanan.php" class="font-bold text-indigo-600 hover:underline">Kelola Layanan &rarr;</a>
        </div>
    </div>

    <!-- Payment Methods & SLA Turnaround -->
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
        <div>
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="font-extrabold text-slate-800 text-lg">Metode Bayar & Kecepatan Layanan</h3>
                    <p class="text-xs text-slate-400">Preferensi bayar pelanggan dan durasi pengerjaan cuci</p>
                </div>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">
                    <i class="fas fa-credit-card"></i>
                </span>
            </div>

            <!-- Turnaround Time Card -->
            <div class="mb-5 p-4 rounded-2xl bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-100/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider block">Rata-rata Durasi Selesai (Turnaround SLA)</span>
                    <h4 class="text-xl font-black text-indigo-950 mt-0.5"><?= $avg_turnaround_hours; ?> Jam</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5">Dari pakaian diterima kasir hingga selesai dicuci dan siap diambil</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white text-indigo-600 flex items-center justify-center text-xl shadow-sm shrink-0">
                    <i class="fas fa-stopwatch"></i>
                </div>
            </div>

            <div class="h-44">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400 flex justify-between items-center">
            <span>Metode: Tunai, QRIS, & Transfer</span>
            <a href="laporan.php" class="font-bold text-indigo-600 hover:underline">Laporan Lengkap &rarr;</a>
        </div>
    </div>

</div>

<!-- Recent Orders Table -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-slate-800 text-lg">Transaksi Terbaru</h3>
            <p class="text-xs text-slate-400">Daftar transaksi yang baru saja masuk</p>
        </div>
        <a href="transaksi.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">
            Lihat Semua Transaksi &rarr;
        </a>
    </div>

    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Kode Nota</th>
                    <th class="px-6 py-4">Pelanggan</th>
                    <th class="px-6 py-4">Tanggal</th>
                    <th class="px-6 py-4 text-right">Total</th>
                    <th class="px-6 py-4 text-center">Status Cucian</th>
                    <th class="px-6 py-4 text-center">Pembayaran</th>
                    <th class="px-6 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while($row = mysqli_fetch_assoc($q_recent)): ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-6 py-4 font-mono font-bold text-indigo-600">
                        <?= htmlspecialchars($row['order_code']); ?>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($row['customer_name']); ?></p>
                        <?php if(!empty($row['customer_phone'])): ?>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($row['customer_phone']); ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-500">
                        <?= date('d/m/y H:i', strtotime($row['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-slate-900">
                        Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php
                        $st = $row['status'];
                        $badge = 'bg-slate-100 text-slate-600';
                        $lbl = 'Menunggu';
                        if ($st === 'processing') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Dicuci'; }
                        elseif ($st === 'ironing') { $badge = 'bg-purple-100 text-purple-800'; $lbl = 'Disetrika'; }
                        elseif ($st === 'ready') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Selesai'; }
                        elseif ($st === 'completed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Sudah Diambil'; }
                        ?>
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
                    <td class="px-6 py-4 text-center">
                        <a href="../admin/cetak_nota.php?id=<?= $row['id']; ?>" target="_blank" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-lg font-bold transition">
                            <i class="fas fa-print mr-1"></i> Struk
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($chart_incomes); ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#4f46e5',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return 'Rp ' + val.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Services Bar Chart
    const sCtx = document.getElementById('serviceChart');
    if (sCtx) {
        new Chart(sCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($service_labels); ?>,
                datasets: [{
                    label: 'Omset Layanan (Rp)',
                    data: <?= json_encode($service_omsets); ?>,
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.85)',
                        'rgba(16, 185, 129, 0.85)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(14, 165, 233, 0.85)',
                        'rgba(168, 85, 247, 0.85)'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return 'Rp ' + v.toLocaleString('id-ID'); }
                        }
                    }
                }
            }
        });
    }

    // Payment Methods Doughnut Chart
    const pCtx = document.getElementById('paymentChart');
    if (pCtx) {
        new Chart(pCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pay_labels); ?>,
                datasets: [{
                    data: <?= json_encode($pay_counts); ?>,
                    backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#ec4899', '#8b5cf6'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
});
</script>

<?php require_once '../layout/footer.php'; ?>
