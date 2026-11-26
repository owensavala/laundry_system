<?php
// superadmin/laporan.php - Financial Reporting & Export (PDF/Excel)
require_once '../includes/auth_check.php';
check_auth(['superadmin']);

$set = get_settings($conn);

// Filters & Quick Presets
$preset = $_GET['preset'] ?? '';
if ($preset === 'today') {
    $start_date = date('Y-m-d');
    $end_date   = date('Y-m-d');
} elseif ($preset === '7days') {
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date   = date('Y-m-d');
} elseif ($preset === 'month') {
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-d');
} elseif ($preset === 'last_month') {
    $start_date = date('Y-m-d', strtotime('first day of last month'));
    $end_date   = date('Y-m-d', strtotime('last day of last month'));
} elseif ($preset === 'year') {
    $start_date = date('Y-01-01');
    $end_date   = date('Y-m-d');
} else {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date   = $_GET['end_date'] ?? date('Y-m-d');
}

// Validate dates strictly (YYYY-MM-DD) & whitelist payment_status
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}
$pay_status = $_GET['payment_status'] ?? 'all';
if (!in_array($pay_status, ['all', 'paid', 'unpaid'])) {
    $pay_status = 'all';
}

// Build query safely
$where = "WHERE DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'";
if ($pay_status !== 'all') {
    $where .= " AND o.payment_status = '$pay_status'";
}

// Check CSV Export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Laporan_Keuangan_" . $start_date . "_sd_" . $end_date . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen("php://output", "w");
    // UTF-8 BOM for Microsoft Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [$set['nama_laundry'] . " - LAPORAN KEUANGAN TRANSAKSI"]);
    fputcsv($output, ["Periode: " . date('d/m/Y', strtotime($start_date)) . " s/d " . date('d/m/Y', strtotime($end_date))]);
    fputcsv($output, []);
    fputcsv($output, ["No.", "Kode Transaksi", "Tanggal", "Nama Pelanggan", "No. HP", "Kasir / Petugas", "Status Cucian", "Status Pembayaran", "Metode Pembayaran", "Diskon (Rp)", "Total Bayar (Rp)"]);

    $q_export = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
        FROM orders o 
        LEFT JOIN users u ON o.admin_id = u.id 
        $where 
        ORDER BY o.created_at ASC");
    $no = 1;
    $total_omset = 0;
    while ($row = mysqli_fetch_assoc($q_export)) {
        $total_omset += (float)$row['total_amount'];
        fputcsv($output, [
            $no++,
            $row['order_code'],
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['customer_name'],
            $row['customer_phone'] ?? '',
            $row['cashier_name'] ?? 'Kasir',
            strtoupper($row['status']),
            ($row['payment_status'] === 'paid' ? 'LUNAS' : 'BELUM LUNAS'),
            $row['payment_method'],
            number_format($row['discount_amount'] ?? 0, 0, ',', ''),
            number_format($row['total_amount'], 0, ',', '')
        ]);
    }
    fputcsv($output, []);
    fputcsv($output, ["", "", "", "", "", "", "", "", "", "TOTAL OMSET (Rp):", number_format($total_omset, 0, ',', '')]);
    fclose($output);
    exit();
}

// Check Excel Export request
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Laporan_Keuangan_" . $start_date . "_sd_" . $end_date . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $q_export = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
        FROM orders o 
        LEFT JOIN users u ON o.admin_id = u.id 
        $where 
        ORDER BY o.created_at ASC");
    ?>
    <table border="1">
        <tr>
            <th colspan="7" style="font-size:16px; font-weight:bold; text-align:center;"><?= htmlspecialchars($set['nama_laundry']); ?> - LAPORAN KEUANGAN TRANSAKSI</th>
        </tr>
        <tr>
            <th colspan="7" style="text-align:center;">Periode: <?= date('d/m/Y', strtotime($start_date)); ?> s/d <?= date('d/m/Y', strtotime($end_date)); ?></th>
        </tr>
        <tr>
            <th>No.</th>
            <th>Kode Transaksi</th>
            <th>Tanggal</th>
            <th>Nama Pelanggan</th>
            <th>Kasir / Petugas</th>
            <th>Status Pembayaran</th>
            <th>Total Bayar (Rp)</th>
        </tr>
        <?php
        $no = 1;
        $total_omset = 0;
        while ($row = mysqli_fetch_assoc($q_export)) {
            $total_omset += (float)$row['total_amount'];
            echo "<tr>";
            echo "<td>" . $no++ . "</td>";
            echo "<td>" . $row['order_code'] . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['cashier_name'] ?? 'Kasir') . "</td>";
            echo "<td>" . ($row['payment_status'] === 'paid' ? 'LUNAS' : 'BELUM LUNAS') . " (" . $row['payment_method'] . ")</td>";
            echo "<td align='right'>" . number_format($row['total_amount'], 0, ',', '') . "</td>";
            echo "</tr>";
        }
        ?>
        <tr>
            <th colspan="6" align="right">TOTAL KESELURUHAN (Rp)</th>
            <th align="right"><?= number_format($total_omset, 0, ',', ''); ?></th>
        </tr>
    </table>
    <?php
    exit();
}

require_once '../layout/header.php';

// Fetch summary metrics
$q_sum_income = mysqli_query($conn, "SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
    COALESCE(SUM(CASE WHEN payment_status='unpaid' THEN total_amount ELSE 0 END), 0) as unpaid_amount,
    COALESCE(SUM(total_amount), 0) as total_gross
    FROM orders o $where");
$summary = mysqli_fetch_assoc($q_sum_income);

// Payment breakdown
$q_breakdown = mysqli_query($conn, "SELECT 
    COALESCE(SUM(CASE WHEN payment_method='Tunai' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as cash_total,
    COALESCE(SUM(CASE WHEN payment_method LIKE '%QRIS%' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as qris_total,
    COALESCE(SUM(CASE WHEN payment_method LIKE '%Transfer%' AND payment_method NOT LIKE '%QRIS%' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as transfer_total,
    COALESCE(SUM(CASE WHEN payment_method='Saldo Deposit' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) as deposit_total
    FROM orders o $where");
$pay_breakdown = mysqli_fetch_assoc($q_breakdown);

// Fetch operational expenses in that range
$q_sum_exp = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah_biaya), 0) as total_expense FROM pengeluaran WHERE DATE(COALESCE(created_at, NOW())) BETWEEN '$start_date' AND '$end_date'");
$total_expense = (float)(mysqli_fetch_assoc($q_sum_exp)['total_expense'] ?? 0);

$net_profit = (float)$summary['paid_amount'] - $total_expense;

// Fetch order records
$q_list = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.admin_id = u.id 
    $where 
    ORDER BY o.created_at DESC");
?>

<!-- Print-Only Header -->
<div class="hidden print:block text-center pb-4 mb-6 border-b-2 border-slate-900">
    <h1 class="text-2xl font-black uppercase tracking-wider text-black"><?= htmlspecialchars($set['nama_laundry']); ?></h1>
    <p class="text-xs text-black"><?= htmlspecialchars($set['alamat']); ?> | Telp/WA: <?= htmlspecialchars($set['whatsapp']); ?></p>
    <h2 class="text-lg font-bold underline mt-3 uppercase text-black">LAPORAN KEUANGAN TRANSAKSI</h2>
    <p class="text-xs text-black">Periode: <?= date('d F Y', strtotime($start_date)); ?> s/d <?= date('d F Y', strtotime($end_date)); ?></p>
</div>

<!-- Controls & Filters (No-Print) -->
<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 no-print">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Laporan Keuangan & Transaksi</h1>
        <p class="text-slate-400 text-sm">Rekapitulasi omset pendapatan, beban operasional, dan laba bersih.</p>
    </div>
    
    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
        <a href="?start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>&payment_status=<?= $pay_status; ?>&export=excel" 
           class="flex-1 sm:flex-initial justify-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-100 transition flex items-center gap-2">
            <i class="fas fa-file-excel"></i> Excel (.xls)
        </a>
        <a href="?start_date=<?= $start_date; ?>&end_date=<?= $end_date; ?>&payment_status=<?= $pay_status; ?>&export=csv" 
           class="flex-1 sm:flex-initial justify-center px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-bold shadow-md shadow-teal-100 transition flex items-center gap-2">
            <i class="fas fa-file-csv"></i> CSV
        </a>
        <button onclick="window.print()" 
                class="flex-1 sm:flex-initial justify-center px-4 py-2.5 btn-brand rounded-xl text-xs font-bold shadow-md transition flex items-center gap-2">
            <i class="fas fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<!-- Filter Bar (No-Print) -->
<div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-8 no-print">
    <!-- Quick Presets Bar -->
    <div class="flex flex-wrap items-center gap-1.5 pb-4 mb-4 border-b border-slate-100 text-xs">
        <span class="text-slate-400 font-bold uppercase text-[10px] mr-1">Preset Cepat:</span>
        <a href="?preset=today&payment_status=<?= $pay_status; ?>" class="px-3 py-1.5 rounded-lg <?= $preset === 'today' ? 'btn-brand font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?> transition">Hari Ini</a>
        <a href="?preset=7days&payment_status=<?= $pay_status; ?>" class="px-3 py-1.5 rounded-lg <?= $preset === '7days' ? 'btn-brand font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?> transition">7 Hari Terakhir</a>
        <a href="?preset=month&payment_status=<?= $pay_status; ?>" class="px-3 py-1.5 rounded-lg <?= ($preset === 'month' || (empty($preset) && empty($_GET['start_date']))) ? 'btn-brand font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?> transition">Bulan Ini</a>
        <a href="?preset=last_month&payment_status=<?= $pay_status; ?>" class="px-3 py-1.5 rounded-lg <?= $preset === 'last_month' ? 'btn-brand font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?> transition">Bulan Lalu</a>
        <a href="?preset=year&payment_status=<?= $pay_status; ?>" class="px-3 py-1.5 rounded-lg <?= $preset === 'year' ? 'btn-brand font-bold' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'; ?> transition">Tahun Ini</a>
    </div>

    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date); ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status Pembayaran</label>
            <select name="payment_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="all" <?= $pay_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                <option value="paid" <?= $pay_status === 'paid' ? 'selected' : ''; ?>>Lunas (Paid)</option>
                <option value="unpaid" <?= $pay_status === 'unpaid' ? 'selected' : ''; ?>>Belum Lunas (Unpaid)</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 btn-brand font-bold py-2 px-4 rounded-xl text-xs transition">
                <i class="fas fa-filter mr-1"></i> Terapkan
            </button>
            <a href="laporan.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2 px-3 rounded-xl text-xs transition flex items-center justify-center">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Stat Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Pendapatan (Lunas)</p>
        <p class="text-xl md:text-2xl font-black text-emerald-600 mt-1">Rp <?= number_format($summary['paid_amount'], 0, ',', '.'); ?></p>
        <p class="text-[10px] text-slate-400 mt-1"><?= (int)$summary['total_orders']; ?> Total Pesanan</p>
        <div class="mt-2 pt-2 border-t border-slate-100 flex flex-wrap gap-x-3 text-[10px] text-slate-500 font-mono">
            <span>Tunai: Rp <?= number_format($pay_breakdown['cash_total'] ?? 0, 0, ',', '.'); ?></span>
            <span>QRIS: Rp <?= number_format($pay_breakdown['qris_total'] ?? 0, 0, ',', '.'); ?></span>
            <span>Transfer: Rp <?= number_format($pay_breakdown['transfer_total'] ?? 0, 0, ',', '.'); ?></span>
            <?php if (($pay_breakdown['deposit_total'] ?? 0) > 0): ?>
                <span>Deposit: Rp <?= number_format($pay_breakdown['deposit_total'], 0, ',', '.'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Piutang / Belum Lunas</p>
        <p class="text-xl md:text-2xl font-black text-rose-500 mt-1">Rp <?= number_format($summary['unpaid_amount'], 0, ',', '.'); ?></p>
        <p class="text-[10px] text-slate-400 mt-1">Belum diselesaikan kasir</p>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Biaya Operasional</p>
        <p class="text-xl md:text-2xl font-black text-slate-800 mt-1">Rp <?= number_format($total_expense, 0, ',', '.'); ?></p>
        <p class="text-[10px] text-slate-400 mt-1">Sabun, listrik, operasional</p>
    </div>

    <div class="brand-gradient-hero text-white p-5 rounded-2xl shadow-lg">
        <p class="text-[11px] font-bold text-white/80 uppercase tracking-wider">Laba Bersih (Net Profit)</p>
        <p class="text-xl md:text-2xl font-black text-white mt-1">Rp <?= number_format($net_profit, 0, ',', '.'); ?></p>
        <p class="text-[10px] text-white/80 mt-1">Omset Masuk - Biaya</p>
    </div>
</div>

<!-- Table Data -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden print:border-none print:shadow-none">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-sm print:text-xs">
            <thead class="bg-slate-50 text-slate-400 text-xs uppercase font-bold tracking-wider print:bg-slate-200 print:text-black">
                <tr>
                    <th class="px-5 py-3.5">Kode Transaksi</th>
                    <th class="px-5 py-3.5">Tanggal</th>
                    <th class="px-5 py-3.5">Pelanggan</th>
                    <th class="px-5 py-3.5">Kasir</th>
                    <th class="px-5 py-3.5 text-center">Status Cucian</th>
                    <th class="px-5 py-3.5 text-center">Pembayaran</th>
                    <th class="px-5 py-3.5 text-right">Nominal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 print:divide-slate-300">
                <?php 
                $count = 0;
                while($d = mysqli_fetch_assoc($q_list)): 
                    $count++;
                ?>
                <tr class="hover:bg-slate-50/70 transition">
                    <td class="px-5 py-3.5 font-mono font-bold text-indigo-600 print:text-black">
                        <?= htmlspecialchars($d['order_code']); ?>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-500 print:text-black">
                        <?= date('d/m/y H:i', strtotime($d['created_at'])); ?>
                    </td>
                    <td class="px-5 py-3.5 font-semibold text-slate-800 print:text-black">
                        <?= htmlspecialchars($d['customer_name']); ?>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-500 print:text-black">
                        <?= htmlspecialchars($d['cashier_name'] ?? 'Kasir'); ?>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-700 print:border print:border-black">
                            <?= $d['status']; ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <?php if ($d['payment_status'] === 'paid'): ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 print:border-black">
                                Lunas (<?= htmlspecialchars($d['payment_method']); ?>)
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200 print:border-black">
                                Belum Lunas
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-right font-bold text-slate-900 font-mono print:text-black">
                        Rp <?= number_format($d['total_amount'], 0, ',', '.'); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if ($count === 0): ?>
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-400 text-sm">
                        Tidak ada transaksi ditemukan pada rentang tanggal tersebut.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Print-Only Footer Sign-Off -->
<div class="hidden print:grid grid-cols-2 gap-8 mt-12 pt-6 text-xs text-black">
    <div>
        <p>Catatan Tambahan:</p>
        <p class="text-slate-500 italic mt-1">- Laporan dicetak otomatis oleh sistem <?= htmlspecialchars($set['nama_laundry']); ?>.</p>
        <p class="text-slate-500 italic">- Waktu cetak: <?= date('d F Y, H:i:s'); ?></p>
    </div>
    <div class="text-right pr-6">
        <p>Penanggung Jawab / Pemilik,</p>
        <div class="h-16"></div>
        <p class="font-bold underline">( <?= htmlspecialchars($_SESSION['name']); ?> )</p>
        <p class="text-[10px]">Superadmin</p>
    </div>
</div>

<?php require_once '../layout/footer.php'; ?>
