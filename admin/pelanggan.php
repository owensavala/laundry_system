<?php
// admin/pelanggan.php - Customer Relationship Management (CRM) & Directory
require_once '../includes/auth_check.php';
check_auth(['superadmin', 'admin']);

$msg = '';
$err = '';

// Handle Create New Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pelanggan'])) {
    $nama = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!empty($nama) && !empty($phone)) {
        // Clean phone number
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = '62' . substr($clean_phone, 1);
        }

        // Check if phone already registered
        $cek = mysqli_query($conn, "SELECT id FROM users WHERE phone = '$clean_phone' AND role = 'customer' LIMIT 1");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $err = "Nomor WhatsApp $phone sudah terdaftar atas pelanggan lain.";
        } else {
            $dummy_email = 'cust_' . time() . '_' . rand(100, 999) . '@laundry.local';
            $default_pass = password_hash('customer123', PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, phone, role, address, created_at) VALUES (?, ?, ?, ?, 'customer', ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sssss", $nama, $dummy_email, $default_pass, $clean_phone, $address);
            
            if (mysqli_stmt_execute($stmt)) {
                $msg = "Pelanggan baru '$nama' berhasil didaftarkan!";
            } else {
                $err = "Gagal mendaftarkan pelanggan: " . mysqli_error($conn);
            }
        }
    } else {
        $err = "Nama lengkap dan nomor WhatsApp wajib diisi!";
    }
}

// Handle Update Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pelanggan'])) {
    $cust_id = (int)($_POST['customer_id'] ?? 0);
    $nama = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($cust_id > 0 && !empty($nama) && !empty($phone)) {
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = '62' . substr($clean_phone, 1);
        }

        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ? AND role = 'customer'");
        mysqli_stmt_bind_param($stmt, "sssi", $nama, $clean_phone, $address, $cust_id);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Data pelanggan berhasil diperbarui!";
        } else {
            $err = "Gagal memperbarui data: " . mysqli_error($conn);
        }
    } else {
        $err = "Data pelanggan tidak valid.";
    }
}

// Handle Top-up Saldo Deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_deposit'])) {
    $cust_id = (int)($_POST['customer_id'] ?? 0);
    $amount = max(0, (float)($_POST['deposit_amount'] ?? 0));
    if ($cust_id > 0 && $amount > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET deposit_balance = COALESCE(deposit_balance, 0) + ? WHERE id = ? AND role = 'customer'");
        mysqli_stmt_bind_param($stmt, "di", $amount, $cust_id);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Saldo deposit sebesar Rp " . number_format($amount, 0, ',', '.') . " berhasil ditambahkan!";
        }
    } else {
        $err = "Nominal top-up deposit tidak valid.";
    }
}

// Handle Redeem Loyalty Points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_points'])) {
    $cust_id = (int)($_POST['customer_id'] ?? 0);
    $pts = max(1, (int)($_POST['points_used'] ?? 10));
    $q_chk = mysqli_query($conn, "SELECT loyalty_points FROM users WHERE id = $cust_id LIMIT 1");
    $cur_pts = $q_chk ? (int)(mysqli_fetch_assoc($q_chk)['loyalty_points'] ?? 0) : 0;
    if ($cust_id > 0 && $cur_pts >= $pts) {
        mysqli_query($conn, "UPDATE users SET loyalty_points = loyalty_points - $pts WHERE id = $cust_id");
        $msg = "Berhasil menukar $pts Poin Loyalitas pelanggan!";
    } else {
        $err = "Poin loyalitas tidak mencukupi (Saat ini: $cur_pts Poin).";
    }
}

// Search Filter
$search = trim($_GET['search'] ?? '');
$search_cond = "";
if (!empty($search)) {
    $esc = mysqli_real_escape_string($conn, $search);
    $search_cond = "AND (u.name LIKE '%$esc%' OR u.phone LIKE '%$esc%' OR u.address LIKE '%$esc%')";
}

// Pagination & Query Customers with LTV metrics
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$q_count = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users u WHERE u.role = 'customer' $search_cond");
$total_rows = (int)(mysqli_fetch_assoc($q_count)['cnt'] ?? 0);
$total_pages = max(1, ceil($total_rows / $per_page));

$query_customers = "SELECT 
    u.id, 
    u.name, 
    u.phone, 
    u.address, 
    COALESCE(u.deposit_balance, 0) as deposit_balance,
    COALESCE(u.loyalty_points, 0) as loyalty_points,
    u.created_at,
    COUNT(o.id) as total_orders,
    COALESCE(SUM(o.total_amount), 0) as total_spent,
    MAX(o.created_at) as last_order_date
FROM users u
LEFT JOIN orders o ON (o.customer_id = u.id OR (u.phone != '' AND o.customer_phone = u.phone))
WHERE u.role = 'customer' $search_cond
GROUP BY u.id
ORDER BY total_spent DESC, total_orders DESC, u.name ASC
LIMIT $per_page OFFSET $offset";

$res_customers = mysqli_query($conn, $query_customers);

// Overall CRM Metrics
$total_cust_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
$total_cust_count = mysqli_fetch_assoc($total_cust_q)['count'] ?? 0;

$repeat_cust_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM (
    SELECT customer_phone FROM orders WHERE customer_phone != '' GROUP BY customer_phone HAVING COUNT(*) > 1
) as repeats");
$repeat_cust_count = mysqli_fetch_assoc($repeat_cust_q)['count'] ?? 0;

$total_ltv_q = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'");
$total_ltv_amount = mysqli_fetch_assoc($total_ltv_q)['total'] ?? 0;

require_once '../layout/header.php';
?>

<!-- Header & Actions -->
<div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black text-slate-800">Direktori Pelanggan (CRM)</h1>
        <p class="text-slate-400 text-sm">Kelola data pelanggan, riwayat loyalitas transaksi, dan kirim pesan WhatsApp dengan 1 klik.</p>
    </div>
    
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" onclick="openAddModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-100 transition flex items-center gap-2">
            <i class="fas fa-user-plus"></i>
            <span>Tambah Pelanggan Baru</span>
        </button>
    </div>
</div>

<?php if ($msg): ?>
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-500"></i>
        <span><?= htmlspecialchars($msg); ?></span>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="mb-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 rounded-xl flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-500"></i>
        <span><?= htmlspecialchars($err); ?></span>
    </div>
<?php endif; ?>

<!-- Quick Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Pelanggan</span>
            <h3 class="text-2xl font-black text-slate-800 mt-0.5"><?= number_format($total_cust_count); ?></h3>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-repeat"></i>
        </div>
        <div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Pelanggan Repeat (Setia)</span>
            <h3 class="text-2xl font-black text-emerald-600 mt-0.5"><?= number_format($repeat_cust_count); ?></h3>
        </div>
    </div>

    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-hand-holding-dollar"></i>
        </div>
        <div>
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Total Omset Transaksi</span>
            <h3 class="text-2xl font-black text-slate-800 font-mono mt-0.5">Rp <?= number_format($total_ltv_amount, 0, ',', '.'); ?></h3>
        </div>
    </div>
</div>

<!-- Search Bar & Table Card -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    
    <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
        <h3 class="font-bold text-base text-slate-800">Daftar Data Pelanggan</h3>
        
        <form action="" method="GET" class="flex items-center gap-2 max-w-sm w-full">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama, no. WA, alamat..." class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button type="submit" class="px-3.5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition">
                Cari
            </button>
            <?php if (!empty($search)): ?>
                <a href="pelanggan.php" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50/75 text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                <tr>
                    <th class="p-3">Pelanggan</th>
                    <th class="p-3">WhatsApp</th>
                    <th class="p-3">Alamat</th>
                    <th class="p-3 text-center">Order</th>
                    <th class="p-3 text-right">Total LTV</th>
                    <th class="p-3">Terakhir</th>
                    <th class="p-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (mysqli_num_rows($res_customers) > 0): ?>
                    <?php while ($c = mysqli_fetch_assoc($res_customers)): 
                        $total_orders = (int)$c['total_orders'];
                        $total_spent = (float)$c['total_spent'];

                        // Determine Badge
                        if ($total_orders >= 10) {
                            $badge = '<span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[10px] font-black"><i class="fas fa-crown text-[9px] mr-1"></i>VIP</span>';
                        } elseif ($total_orders >= 4) {
                            $badge = '<span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-full text-[10px] font-black"><i class="fas fa-star text-[9px] mr-1"></i>Loyal</span>';
                        } else {
                            $badge = '<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold">Baru</span>';
                        }

                        $wa_clean = preg_replace('/[^0-9]/', '', $c['phone'] ?? '');
                        if (substr($wa_clean, 0, 1) === '0') $wa_clean = '62' . substr($wa_clean, 1);
                        $greeting = "Halo Kak " . $c['name'] . ", terima kasih telah mempercayakan pakaian Anda di " . $sys_settings['nama_laundry'] . ". Apakah ada yang bisa kami bantu?";
                        $wa_chat_url = "https://wa.me/" . $wa_clean . "?text=" . rawurlencode($greeting);

                        $pos_url = "../admin/pos.php?customer_id=" . $c['id'] . "&name=" . urlencode($c['name']) . "&phone=" . urlencode($c['phone']) . "&address=" . urlencode($c['address']);
                    ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
                                    <?= substr($c['name'], 0, 1); ?>
                                </span>
                                <div>
                                    <div class="font-extrabold text-slate-900 flex items-center gap-1.5">
                                        <?= htmlspecialchars($c['name']); ?>
                                        <?= $badge; ?>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                                            Saldo: Rp <?= number_format($c['deposit_balance'] ?? 0, 0, ',', '.'); ?>
                                        </span>
                                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">
                                            <i class="fas fa-star text-[9px] mr-0.5"></i><?= (int)($c['loyalty_points'] ?? 0); ?> Poin
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 font-mono font-medium text-slate-700 whitespace-nowrap">
                            <?= htmlspecialchars($c['phone'] ?: '-'); ?>
                        </td>
                        <td class="p-3 text-slate-600 max-w-[160px] truncate" title="<?= htmlspecialchars($c['address']); ?>">
                            <?= htmlspecialchars($c['address'] ?: '-'); ?>
                        </td>
                        <td class="p-3 text-center whitespace-nowrap">
                            <span class="px-2 py-1 bg-slate-100 text-slate-800 rounded-xl font-bold font-mono">
                                <?= $total_orders; ?>x
                            </span>
                        </td>
                        <td class="p-3 text-right font-mono font-bold text-indigo-600 whitespace-nowrap">
                            Rp <?= number_format($total_spent, 0, ',', '.'); ?>
                        </td>
                        <td class="p-3 text-slate-500 whitespace-nowrap">
                            <?= !empty($c['last_order_date']) ? date('d/m/y H:i', strtotime($c['last_order_date'])) : '-'; ?>
                        </td>
                        <td class="p-3 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <!-- 1-Click WhatsApp -->
                                <?php if (!empty($c['phone'])): ?>
                                    <a href="<?= $wa_chat_url; ?>" target="_blank" class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white flex items-center justify-center transition shadow-sm" title="Chat WhatsApp">
                                        <i class="fab fa-whatsapp text-sm"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- 1-Click POS Order Creation -->
                                <a href="<?= $pos_url; ?>" class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition shadow-sm" title="Buat Pesanan Baru di POS">
                                    <i class="fas fa-cart-plus text-xs"></i>
                                </a>

                                <!-- Top-up Deposit & Poin Button -->
                                <button type="button" 
                                        onclick="openDepositModal(<?= $c['id']; ?>, '<?= addslashes($c['name']); ?>', <?= (float)($c['deposit_balance'] ?? 0); ?>, <?= (int)($c['loyalty_points'] ?? 0); ?>)" 
                                        class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white flex items-center justify-center transition shadow-sm" title="Top-up Saldo & Tukar Poin">
                                    <i class="fas fa-wallet text-xs"></i>
                                </button>

                                <!-- Edit Customer Button -->
                                <button type="button" 
                                        onclick="openEditModal(<?= $c['id']; ?>, '<?= addslashes($c['name']); ?>', '<?= addslashes($c['phone']); ?>', '<?= addslashes($c['address']); ?>')" 
                                        class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center justify-center transition" title="Edit Data">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            <i class="fas fa-users text-3xl mb-2 text-slate-300 block"></i>
                            Belum ada data pelanggan yang cocok dengan pencarian.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500">
        <div>
            Menampilkan <strong><?= min($offset + 1, $total_rows); ?></strong> - <strong><?= min($offset + $per_page, $total_rows); ?></strong> dari <strong><?= number_format($total_rows); ?></strong> pelanggan
        </div>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">&larr; Prev</a>
            <?php endif; ?>
            
            <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                <a href="?page=<?= $p; ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-slate-100 hover:bg-slate-200 font-medium'; ?> transition"><?= $p; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 font-bold transition">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Pelanggan -->
<div id="modalAdd" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl relative">
        <button type="button" onclick="closeAddModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>

        <h3 class="font-black text-lg text-slate-800 mb-1 flex items-center gap-2">
            <i class="fas fa-user-plus text-indigo-600"></i> Tambah Pelanggan Baru
        </h3>
        <p class="text-xs text-slate-400 mb-4">Daftarkan profil pelanggan baru ke database sistem.</p>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Lengkap *</label>
                <input type="text" name="name" required placeholder="Contoh: Ibu Linda / Pak Rudi" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor WhatsApp / HP *</label>
                <input type="tel" name="phone" required placeholder="08123456789" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <span class="text-[10px] text-slate-400">Nomor ini akan dipakai untuk pengiriman nota & status via WhatsApp.</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Alamat Pelanggan</label>
                <textarea name="address" rows="2" placeholder="Jl. Mawar No. 4, RT 01/02..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-100">Batal</button>
                <button type="submit" name="tambah_pelanggan" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition">Simpan Pelanggan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Pelanggan -->
<div id="modalEdit" class="hidden fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl relative">
        <button type="button" onclick="closeEditModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>

        <h3 class="font-black text-lg text-slate-800 mb-1 flex items-center gap-2">
            <i class="fas fa-user-pen text-indigo-600"></i> Edit Data Pelanggan
        </h3>
        <p class="text-xs text-slate-400 mb-4">Perbarui informasi kontak dan alamat pelanggan.</p>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" id="editCustId" name="customer_id">

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Lengkap *</label>
                <input type="text" id="editCustName" name="name" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor WhatsApp / HP *</label>
                <input type="tel" id="editCustPhone" name="phone" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Alamat Pelanggan</label>
                <textarea id="editCustAddress" name="address" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-100">Batal</button>
                <button type="submit" name="edit_pelanggan" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Top-up Saldo Deposit & Tukar Poin -->
<div id="modalDeposit" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
            <h3 class="font-black text-lg text-slate-800"><i class="fas fa-wallet text-amber-500 mr-2"></i>Deposit & Poin Pelanggan</h3>
            <button type="button" onclick="closeDepositModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>

        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex justify-between items-center text-xs">
            <div>
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Pelanggan</span>
                <strong id="depCustName" class="text-sm text-slate-800"></strong>
            </div>
            <div class="text-right">
                <span id="depCustBal" class="block font-mono font-black text-emerald-600"></span>
                <span id="depCustPts" class="block font-bold text-amber-600"></span>
            </div>
        </div>

        <form action="" method="POST" class="space-y-3 border-b border-slate-100 pb-4">
            <input type="hidden" id="depCustId" name="customer_id">
            <label class="block text-xs font-bold text-slate-600 uppercase">Top-up Saldo Deposit (Rp)</label>
            <div class="flex gap-2">
                <input type="number" name="deposit_amount" min="10000" step="10000" value="50000" required class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold">
                <button type="submit" name="topup_deposit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm">
                    + Top-Up
                </button>
            </div>
        </form>

        <form action="" method="POST" class="space-y-3">
            <input type="hidden" id="ptsCustId" name="customer_id">
            <label class="block text-xs font-bold text-slate-600 uppercase">Tukar Poin Loyalitas (Reward)</label>
            <div class="flex gap-2">
                <input type="number" name="points_used" min="1" value="10" required class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold">
                <button type="submit" name="redeem_points" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-sm">
                    Tukar Poin
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDepositModal(id, name, bal, pts) {
        document.getElementById('depCustId').value = id;
        document.getElementById('ptsCustId').value = id;
        document.getElementById('depCustName').innerText = name;
        document.getElementById('depCustBal').innerText = 'Saldo: Rp ' + Number(bal).toLocaleString('id-ID');
        document.getElementById('depCustPts').innerText = pts + ' Poin Loyalitas';
        document.getElementById('modalDeposit').classList.remove('hidden');
    }
    function closeDepositModal() {
        document.getElementById('modalDeposit').classList.add('hidden');
    }
    function openAddModal() {
        document.getElementById('modalAdd').classList.remove('hidden');
    }
    function closeAddModal() {
        document.getElementById('modalAdd').classList.add('hidden');
    }
    function openEditModal(id, name, phone, address) {
        document.getElementById('editCustId').value = id;
        document.getElementById('editCustName').value = name;
        document.getElementById('editCustPhone').value = phone;
        document.getElementById('editCustAddress').value = address;
        document.getElementById('modalEdit').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('modalEdit').classList.add('hidden');
    }
</script>

<?php require_once '../layout/footer.php'; ?>
