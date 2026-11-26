<?php
// admin/shift.php - Buka & Tutup Shift Kasir (Rekapitulasi Uang Laci & Non-Tunai)
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

$user_id = (int)($_SESSION['user_id'] ?? 1);
$msg = '';
$err = '';

// Check if there is an active open shift for current user (or overall)
$q_open = mysqli_query($conn, "SELECT s.*, u.name as cashier_name FROM cashier_shifts s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'open' ORDER BY s.opened_at DESC LIMIT 1");
$open_shift = ($q_open && mysqli_num_rows($q_open) > 0) ? mysqli_fetch_assoc($q_open) : null;

// Handle Open Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buka_shift'])) {
    if ($open_shift) {
        $err = 'Masih ada shift kasir yang terbuka! Tutup shift aktif terlebih dahulu.';
    } else {
        $opening_cash = max(0, (float)($_POST['opening_cash'] ?? 0));
        $stmt = mysqli_prepare($conn, "INSERT INTO cashier_shifts (user_id, opened_at, opening_cash, status) VALUES (?, NOW(), ?, 'open')");
        mysqli_stmt_bind_param($stmt, "id", $user_id, $opening_cash);
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Shift kasir berhasil dibuka! Selamat bertugas.';
            $q_open = mysqli_query($conn, "SELECT s.*, u.name as cashier_name FROM cashier_shifts s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'open' ORDER BY s.opened_at DESC LIMIT 1");
            $open_shift = ($q_open && mysqli_num_rows($q_open) > 0) ? mysqli_fetch_assoc($q_open) : null;
        } else {
            $err = 'Gagal membuka shift: ' . mysqli_error($conn);
        }
    }
}

// Compute live sales during open shift
$live_cash_sales = 0;
$live_qris_sales = 0;
$live_transfer_sales = 0;
$live_tx_count = 0;
$expected_cash = 0;

if ($open_shift) {
    $opened_at = mysqli_real_escape_string($conn, $open_shift['opened_at']);
    $q_sales = mysqli_query($conn, "SELECT 
        COUNT(*) as tx_cnt,
        COALESCE(SUM(CASE WHEN payment_method = 'Tunai' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as cash_tot,
        COALESCE(SUM(CASE WHEN payment_method LIKE '%QRIS%' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as qris_tot,
        COALESCE(SUM(CASE WHEN payment_method LIKE '%Transfer Bank%' AND payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as tf_tot
        FROM orders WHERE created_at >= '$opened_at'");
    if ($q_sales && $rs = mysqli_fetch_assoc($q_sales)) {
        $live_tx_count = (int)$rs['tx_cnt'];
        $live_cash_sales = (float)$rs['cash_tot'];
        $live_qris_sales = (float)$rs['qris_tot'];
        $live_transfer_sales = (float)$rs['tf_tot'];
    }
    $expected_cash = (float)$open_shift['opening_cash'] + $live_cash_sales;
}

// Handle Close Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tutup_shift']) && $open_shift) {
    $shift_id = (int)$open_shift['id'];
    $actual_cash = max(0, (float)($_POST['actual_cash'] ?? 0));
    $notes = trim($_POST['notes'] ?? '');

    $stmt_close = mysqli_prepare($conn, "UPDATE cashier_shifts SET closed_at = NOW(), expected_cash = ?, actual_cash = ?, qris_total = ?, transfer_total = ?, notes = ?, status = 'closed' WHERE id = ?");
    mysqli_stmt_bind_param($stmt_close, "ddddsi", $expected_cash, $actual_cash, $live_qris_sales, $live_transfer_sales, $notes, $shift_id);
    if (mysqli_stmt_execute($stmt_close)) {
        $msg = 'Shift kasir berhasil ditutup & direkap!';
        $open_shift = null;
    } else {
        $err = 'Gagal menutup shift: ' . mysqli_error($conn);
    }
}

// Fetch Shift History
$q_history = mysqli_query($conn, "SELECT s.*, u.name as cashier_name FROM cashier_shifts s LEFT JOIN users u ON s.user_id = u.id WHERE s.status = 'closed' ORDER BY s.closed_at DESC LIMIT 20");

require_once '../layout/header.php';
?>

<div class="max-w-6xl mx-auto pb-16 space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl text-white flex items-center justify-center text-xl shadow-lg" style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));">
                <i class="fas fa-cash-register"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Buka & Tutup Kasir (Shift Closing)</h1>
                <p class="text-xs text-slate-500">Rekap uang tunai laci, QRIS, & Transfer Bank setiap pergantian shift kasir.</p>
            </div>
        </div>
        <a href="pos.php" class="px-4 py-2.5 btn-brand rounded-xl text-xs font-bold shadow-md flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Kembali ke POS
        </a>
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

    <!-- Active Shift or Open Shift Card -->
    <?php if (!$open_shift): ?>
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200/80 shadow-xs max-w-xl">
            <h2 class="text-base font-black text-slate-800 mb-1 flex items-center gap-2">
                <i class="fas fa-door-open text-emerald-600"></i> Buka Shift Kasir Baru
            </h2>
            <p class="text-xs text-slate-400 mb-5">Masukkan modal uang tunai awal di laci kasir sebelum memulai transaksi hari ini.</p>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1.5">Modal Awal Uang Tunai di Laci (Rp)</label>
                    <input type="number" name="opening_cash" min="0" step="1000" value="100000" required
                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-base font-mono font-black focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                </div>
                <button type="submit" name="buka_shift" class="w-full py-3.5 btn-brand rounded-2xl font-black text-xs sm:text-sm shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-play-circle"></i> Mulai & Buka Shift Kasir
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Live Shift Summary (7 cols) -->
            <div class="lg:col-span-7 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 inline-flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Shift Aktif
                        </span>
                        <h2 class="text-base font-black text-slate-800 mt-1">Petugas: <?= htmlspecialchars($open_shift['cashier_name'] ?? 'Kasir'); ?></h2>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        <span>Dibuka:</span>
                        <strong class="block text-slate-700"><?= date('d M Y, H:i', strtotime($open_shift['opened_at'])); ?></strong>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block">Modal Awal</span>
                        <strong class="text-sm font-mono font-black text-slate-800">Rp <?= number_format($open_shift['opening_cash'], 0, ',', '.'); ?></strong>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-100">
                        <span class="text-[10px] font-bold text-emerald-700 uppercase block">Masuk Tunai</span>
                        <strong class="text-sm font-mono font-black text-emerald-800">Rp <?= number_format($live_cash_sales, 0, ',', '.'); ?></strong>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-indigo-50 border border-indigo-100">
                        <span class="text-[10px] font-bold text-indigo-700 uppercase block">Masuk QRIS</span>
                        <strong class="text-sm font-mono font-black text-indigo-800">Rp <?= number_format($live_qris_sales, 0, ',', '.'); ?></strong>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-sky-50 border border-sky-100">
                        <span class="text-[10px] font-bold text-sky-700 uppercase block">Transfer Bank</span>
                        <strong class="text-sm font-mono font-black text-sky-800">Rp <?= number_format($live_transfer_sales, 0, ',', '.'); ?></strong>
                    </div>
                </div>

                <div class="p-5 rounded-2xl brand-gradient-soft border border-pink-100 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-extrabold uppercase text-slate-600 block">Total Uang Tunai Seharusnya di Laci</span>
                        <p class="text-[11px] text-slate-500">Modal Awal + Pemasukan Tunai (<?= $live_tx_count; ?> transaksi tercatat)</p>
                    </div>
                    <span class="text-2xl font-black font-mono text-brand">Rp <?= number_format($expected_cash, 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Close Shift Form (5 cols) -->
            <div class="lg:col-span-5 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4"
                 x-data="{ actualCash: <?= $expected_cash; ?>, expected: <?= $expected_cash; ?> }">
                <h2 class="text-base font-black text-slate-800 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i class="fas fa-lock text-rose-500"></i> Tutup Shift & Setor Kas
                </h2>

                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Uang Tunai Fisik Aktual di Laci (Rp)</label>
                        <input type="number" name="actual_cash" x-model.number="actualCash" min="0" step="1000" required
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-base font-mono font-black focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex justify-between items-center text-xs">
                        <span class="font-bold text-slate-600">Selisih Kas:</span>
                        <span class="font-mono font-black"
                              :class="(actualCash - expected) === 0 ? 'text-emerald-600' : ((actualCash - expected) > 0 ? 'text-indigo-600' : 'text-rose-600')"
                              x-text="((actualCash - expected) >= 0 ? '+ ' : '- ') + 'Rp ' + Math.abs(actualCash - expected).toLocaleString('id-ID')">
                        </span>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Catatan Tutup Shift (Opsional)</label>
                        <textarea name="notes" rows="2" placeholder="Contoh: Setoran tunai diserahkan ke owner..."
                                  class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400"></textarea>
                    </div>

                    <button type="submit" name="tutup_shift" onclick="return confirm('Yakin ingin menutup shift kasir saat ini?')"
                            class="w-full py-3.5 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-black text-xs sm:text-sm shadow-lg transition flex items-center justify-center gap-2">
                        <i class="fas fa-check-double"></i> Konfirmasi Tutup Shift
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Shift History Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-base font-black text-slate-800 flex items-center gap-2">
                <i class="fas fa-history text-brand"></i> Riwayat Tutup Shift Kasir
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-400 uppercase font-extrabold text-[10px] border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-4">Kasir</th>
                        <th class="py-3.5 px-4">Waktu Buka - Tutup</th>
                        <th class="py-3.5 px-4 text-right">Modal Awal</th>
                        <th class="py-3.5 px-4 text-right">Seharusnya (Laci)</th>
                        <th class="py-3.5 px-4 text-right">Fisik Aktual</th>
                        <th class="py-3.5 px-4 text-right">Selisih</th>
                        <th class="py-3.5 px-4 text-right">QRIS / Transfer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <?php if ($q_history && mysqli_num_rows($q_history) > 0): ?>
                        <?php while($h = mysqli_fetch_assoc($q_history)):
                            $diff = (float)$h['actual_cash'] - (float)$h['expected_cash'];
                        ?>
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($h['cashier_name'] ?? 'Kasir'); ?></td>
                            <td class="py-3.5 px-4 text-[11px] text-slate-500">
                                <?= date('d/m H:i', strtotime($h['opened_at'])); ?> &rarr; <?= date('d/m H:i', strtotime($h['closed_at'])); ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono">Rp <?= number_format($h['opening_cash'], 0, ',', '.'); ?></td>
                            <td class="py-3.5 px-4 text-right font-mono">Rp <?= number_format($h['expected_cash'], 0, ',', '.'); ?></td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">Rp <?= number_format($h['actual_cash'], 0, ',', '.'); ?></td>
                            <td class="py-3.5 px-4 text-right font-mono font-black <?= $diff == 0 ? 'text-emerald-600' : ($diff > 0 ? 'text-indigo-600' : 'text-rose-600'); ?>">
                                <?= ($diff >= 0 ? '+' : '-') . 'Rp ' . number_format(abs($diff), 0, ',', '.'); ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-[11px] text-slate-500">
                                QRIS: Rp <?= number_format($h['qris_total'], 0, ',', '.'); ?><br>
                                TF: Rp <?= number_format($h['transfer_total'], 0, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 italic">Belum ada riwayat shift yang ditutup.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once '../layout/footer.php'; ?>
