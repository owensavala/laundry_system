<?php
// admin/pesanan.php - Order Status & Payment Management
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../includes/whatsapp.php';

$set = get_settings($conn);
$msg = '';
$err = '';

// Handle Payment Settlement
if (isset($_POST['bayar_lunas'])) {
    $order_id = (int)$_POST['order_id'];
    $method = $_POST['payment_method'] ?? 'Tunai';

    $stmt_pay = mysqli_prepare($conn, "UPDATE orders SET payment_status='paid', payment_method=? WHERE id=?");
    mysqli_stmt_bind_param($stmt_pay, "si", $method, $order_id);
    if (mysqli_stmt_execute($stmt_pay)) {
        $msg = 'Pembayaran berhasil dilunasi!';
    } else {
        $err = 'Gagal memperbarui status bayar: ' . mysqli_error($conn);
    }
}

// Filters
$status_filter = $_GET['status'] ?? 'active';
$payment_filter = $_GET['payment'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if ($status_filter === 'active') {
    $where .= " AND o.status IN ('pending', 'processing', 'ironing', 'ready')";
} elseif ($status_filter !== 'all' && !empty($status_filter)) {
    $st_safe = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND o.status = '$st_safe'";
}

if ($payment_filter !== 'all' && !empty($payment_filter)) {
    $p_safe = mysqli_real_escape_string($conn, $payment_filter);
    $where .= " AND o.payment_status = '$p_safe'";
}

if (!empty($search)) {
    $s_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_code LIKE '%$s_safe%' OR o.customer_name LIKE '%$s_safe%' OR o.customer_phone LIKE '%$s_safe%')";
}

// Pagination & Order query
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
    ORDER BY FIELD(o.status, 'pending', 'processing', 'ironing', 'ready', 'completed'), o.created_at DESC
    LIMIT $per_page OFFSET $offset");

require_once '../layout/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Antrean & Status Pesanan Cucian</h1>
        <p class="text-slate-400 text-sm">Update progres cucian bertahap, status pembayaran lunas, dan kirim notifikasi.</p>
    </div>
    <a href="pos.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-100 transition flex items-center gap-2">
        <i class="fas fa-plus"></i> Input Pesanan Baru
    </a>
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

<!-- Filter & Search Bar -->
<div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mb-6 space-y-4">
    
    <!-- Status Tabs (Horizontal Scrollable on Mobile, Wrap on Desktop) -->
    <div class="flex gap-2 border-b border-slate-100 pb-3 text-xs font-bold overflow-x-auto no-scrollbar flex-nowrap sm:flex-wrap">
        <a href="?status=active" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'active' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Semua Aktif
        </a>
        <a href="?status=pending" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'pending' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Menunggu Antrean
        </a>
        <a href="?status=processing" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'processing' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Sedang Dicuci
        </a>
        <a href="?status=ironing" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'ironing' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Sedang Disetrika
        </a>
        <a href="?status=ready" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'ready' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Selesai (Siap Diambil)
        </a>
        <a href="?status=completed" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'completed' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Sudah Diambil
        </a>
        <a href="?status=all" class="px-3.5 py-2 rounded-xl transition shrink-0 <?= $status_filter === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
            Semua Transaksi
        </a>
    </div>

    <!-- Search Form -->
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter); ?>">
        
        <div class="relative flex-1">
            <i class="fas fa-search absolute left-4 top-3 text-slate-400 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari Kode Nota, Nama Pelanggan, atau No HP..." 
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <select name="payment" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all" <?= $payment_filter === 'all' ? 'selected' : ''; ?>>Semua Pembayaran</option>
            <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : ''; ?>>Lunas Saja</option>
            <option value="unpaid" <?= $payment_filter === 'unpaid' ? 'selected' : ''; ?>>Belum Lunas</option>
        </select>

        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition">
            Filter
        </button>
        <?php if (!empty($search) || $payment_filter !== 'all'): ?>
            <a href="pesanan.php?status=<?= $status_filter; ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center">
                Reset
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders List -->
<div class="space-y-4">
    <?php 
    $total_rendered = 0;
    while($row = mysqli_fetch_assoc($orders)): 
        $total_rendered++;
        $st = $row['status'];
        
        // Fetch item details for this order
        $q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit 
            FROM order_details od 
            LEFT JOIN services s ON od.service_id = s.id 
            WHERE od.order_id = '{$row['id']}'");
        
        // Build WA message preview
        $wa_msg_preview = build_wa_message($set['wa_template_ready'], [
            'customer_name' => $row['customer_name'],
            'order_code' => $row['order_code'],
            'laundry_name' => $set['nama_laundry']
        ]);
        $wa_ready_url = get_wa_direct_link($row['customer_phone'], $wa_msg_preview);
    ?>
    <div class="bg-white rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 hover:border-slate-200 transition">
        
        <!-- Header Row -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-soap"></i>
                </span>
                <div>
                    <span class="font-mono text-sm font-black text-indigo-600"><?= htmlspecialchars($row['order_code']); ?></span>
                    <span class="text-xs text-slate-400 ml-2"><i class="far fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($row['created_at'])); ?></span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Status Badge -->
                <?php
                $badge = 'bg-slate-100 text-slate-600';
                $lbl = 'Menunggu';
                if ($st === 'processing') { $badge = 'bg-amber-100 text-amber-800'; $lbl = 'Sedang Dicuci'; }
                elseif ($st === 'ironing') { $badge = 'bg-purple-100 text-purple-800'; $lbl = 'Sedang Disetrika'; }
                elseif ($st === 'ready') { $badge = 'bg-emerald-100 text-emerald-800'; $lbl = 'Selesai (Siap Diambil)'; }
                elseif ($st === 'completed') { $badge = 'bg-blue-100 text-blue-800'; $lbl = 'Sudah Diambil'; }
                ?>
                <span class="px-3 py-1 rounded-full text-xs font-black uppercase <?= $badge; ?>">
                    <?= $lbl; ?>
                </span>

                <!-- Payment Status Badge -->
                <?php if ($row['payment_status'] === 'paid'): ?>
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i class="fas fa-check mr-1"></i> Lunas (<?= htmlspecialchars($row['payment_method']); ?>)
                    </span>
                <?php else: ?>
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase bg-rose-50 text-rose-700 border border-rose-200">
                        Belum Lunas
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Body: Customer info & Items -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-4 border-b border-slate-100 text-sm">
            
            <!-- Customer -->
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pelanggan</p>
                <h4 class="font-extrabold text-slate-800 text-base mt-0.5"><?= htmlspecialchars($row['customer_name']); ?></h4>
                <p class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                    <i class="fab fa-whatsapp text-emerald-600"></i>
                    <a href="https://wa.me/<?= format_wa_phone($row['customer_phone']); ?>" target="_blank" class="hover:underline">
                        <?= htmlspecialchars($row['customer_phone'] ?? '-'); ?>
                    </a>
                </p>
                <?php if (!empty($row['notes'])): ?>
                    <p class="mt-2 text-xs bg-amber-50 text-amber-800 p-2 rounded-xl border border-amber-200">
                        <i class="fas fa-info-circle mr-1"></i> <strong>Catatan:</strong> <?= htmlspecialchars($row['notes']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Rincian Layanan</p>
                <ul class="space-y-1.5 text-xs text-slate-700">
                    <?php while($item = mysqli_fetch_assoc($q_items)): ?>
                    <li class="flex justify-between items-center bg-slate-50 p-2 rounded-lg">
                        <span><?= htmlspecialchars($item['service_name'] ?? 'Layanan Laundry'); ?> (<?= $item['qty']; ?> <?= $item['unit'] ?? 'kg'; ?>)</span>
                        <span class="font-mono font-bold text-indigo-600">Rp <?= number_format($item['subtotal'], 0, ',', '.'); ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>

            <!-- Total & Due Date -->
            <div class="flex flex-col justify-between">
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Estimasi Target Selesai</p>
                    <p class="text-xs font-bold text-slate-700 mt-0.5">
                        <i class="far fa-calendar-check text-indigo-500 mr-1"></i>
                        <?= $row['estimate_completed_at'] ? date('d M Y, H:i', strtotime($row['estimate_completed_at'])) : '-'; ?>
                    </p>
                </div>
                <div class="mt-3">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Tagihan</p>
                    <h3 class="text-2xl font-black text-slate-900 font-mono">Rp <?= number_format($row['total_amount'], 0, ',', '.'); ?></h3>
                </div>
            </div>

        </div>

        <!-- Footer Actions Bar -->
        <div class="pt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            
            <!-- Quick Actions: Print Receipt & WA -->
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <a href="cetak_nota.php?id=<?= $row['id']; ?>" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <i class="fas fa-print"></i> Cetak Struk
                </a>

                <?php if ($st === 'ready'): ?>
                    <a href="<?= $wa_ready_url; ?>" target="_blank" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fab fa-whatsapp"></i> WA: Cucian Siap
                    </a>
                <?php endif; ?>

                <?php if ($row['payment_status'] === 'unpaid'): ?>
                    <button onclick="openModalBayar(<?= $row['id']; ?>, '<?= $row['order_code']; ?>', <?= $row['total_amount']; ?>)" 
                            class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fas fa-money-bill-wave"></i> Lunasi Tagihan
                    </button>
                <?php endif; ?>
            </div>

            <!-- Status Transition Buttons -->
            <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto justify-start sm:justify-end">
                <span class="text-[11px] text-slate-400 font-bold uppercase mr-1">Ubah Status:</span>
                
                <?php if ($st === 'pending'): ?>
                    <a href="update_status.php?id=<?= $row['id']; ?>&status=processing" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-1">
                        <i class="fas fa-tshirt"></i> Mulai Cuci
                    </a>
                <?php elseif ($st === 'processing'): ?>
                    <a href="update_status.php?id=<?= $row['id']; ?>&status=ironing" class="px-3.5 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1">
                        <i class="fas fa-temperature-high"></i> Setrika
                    </a>
                <?php elseif ($st === 'ironing'): ?>
                    <a href="update_status.php?id=<?= $row['id']; ?>&status=ready" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-md shadow-emerald-100">
                        <i class="fas fa-check-circle"></i> Selesai (Siap Diambil)
                    </a>
                <?php elseif ($st === 'ready'): ?>
                    <a href="update_status.php?id=<?= $row['id']; ?>&status=completed" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1">
                        <i class="fas fa-shopping-bag"></i> Serahkan ke Pelanggan
                    </a>
                <?php else: ?>
                    <span class="text-xs text-slate-400 font-bold py-1 px-2"><i class="fas fa-check-double text-emerald-500 mr-1"></i>Transaksi Tuntas</span>
                <?php endif; ?>

                <?php if ($st !== 'completed'): ?>
                    <!-- Quick Dropdown to jump directly if needed -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs transition">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" class="absolute right-0 bottom-full mb-1 w-44 bg-white border border-slate-200 rounded-2xl shadow-xl py-1 text-xs font-medium z-10">
                            <a href="update_status.php?id=<?= $row['id']; ?>&status=pending" class="block px-4 py-2 hover:bg-slate-50 text-slate-700">Set: Menunggu</a>
                            <a href="update_status.php?id=<?= $row['id']; ?>&status=processing" class="block px-4 py-2 hover:bg-slate-50 text-amber-700">Set: Dicuci</a>
                            <a href="update_status.php?id=<?= $row['id']; ?>&status=ironing" class="block px-4 py-2 hover:bg-slate-50 text-purple-700">Set: Disetrika</a>
                            <a href="update_status.php?id=<?= $row['id']; ?>&status=ready" class="block px-4 py-2 hover:bg-slate-50 text-emerald-700">Set: Selesai</a>
                            <a href="update_status.php?id=<?= $row['id']; ?>&status=completed" class="block px-4 py-2 hover:bg-slate-50 text-blue-700">Set: Sudah Diambil</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
    <?php endwhile; ?>

    <?php if ($total_rendered === 0): ?>
    <div class="bg-white rounded-3xl p-12 text-center text-slate-400 shadow-sm border border-slate-100">
        <i class="fas fa-inbox text-4xl text-slate-300 block mb-3"></i>
        <p class="font-bold text-slate-600 text-base">Tidak ada pesanan ditemukan.</p>
        <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau tab filter status di atas.</p>
    </div>
    <?php endif; ?>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500">
        <div>
            Menampilkan antrean <strong><?= min($offset + 1, $total_rows); ?></strong> - <strong><?= min($offset + $per_page, $total_rows); ?></strong> dari <strong><?= number_format($total_rows); ?></strong> pesanan
        </div>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1; ?>&status=<?= urlencode($status_filter); ?>&payment=<?= urlencode($payment_filter); ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">&larr; Prev</a>
            <?php endif; ?>
            
            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <a href="?page=<?= $p; ?>&status=<?= urlencode($status_filter); ?>&payment=<?= urlencode($payment_filter); ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-slate-100 hover:bg-slate-200 font-medium'; ?> transition"><?= $p; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1; ?>&status=<?= urlencode($status_filter); ?>&payment=<?= urlencode($payment_filter); ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Pelunasan Pembayaran -->
<div id="modalBayar" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-black text-slate-800">Pelunasan Tagihan</h3>
            <button onclick="document.getElementById('modalBayar').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="order_id" id="bayar_order_id">
            
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <p class="text-xs text-slate-400">Kode Transaksi</p>
                <p class="font-mono font-bold text-slate-800" id="bayar_code"></p>
                <p class="text-xs text-slate-400 mt-2">Total yang Harus Dibayar</p>
                <p class="font-mono font-black text-xl text-emerald-600" id="bayar_total"></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Pilih Metode Pembayaran *</label>
                <select name="payment_method" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="Tunai">Tunai / Cash</option>
                    <option value="Transfer QRIS">QRIS</option>
                    <option value="Transfer BCA">Transfer Bank BCA</option>
                    <option value="Transfer Mandiri">Transfer Bank Mandiri / BNI / BRI</option>
                </select>
            </div>

            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalBayar').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-slate-600 font-bold hover:bg-slate-100 text-sm">Batal</button>
                <button type="submit" name="bayar_lunas" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm shadow-lg shadow-emerald-100">
                    Konfirmasi Lunas
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModalBayar(id, code, total) {
    document.getElementById('bayar_order_id').value = id;
    document.getElementById('bayar_code').innerText = code;
    document.getElementById('bayar_total').innerText = 'Rp ' + total.toLocaleString('id-ID');
    document.getElementById('modalBayar').classList.remove('hidden');
}
</script>

<?php require_once '../layout/footer.php'; ?>
