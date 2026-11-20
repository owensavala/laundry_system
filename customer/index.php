<?php
// customer/index.php - Customer Portal & Laundry History
require_once '../includes/auth_check.php';
check_auth(['customer']);

require_once '../layout/header.php';

$cust_id = (int)$_SESSION['user_id'];
$cust_phone = $_SESSION['phone'] ?? '';

// Fetch all orders related to this customer (by customer_id OR matching customer_phone)
$where_cust = "customer_id = '$cust_id'";
if (!empty($cust_phone)) {
    $where_cust .= " OR customer_phone = '$cust_phone'";
}

$q_my_orders = mysqli_query($conn, "SELECT * FROM orders WHERE $where_cust ORDER BY created_at DESC");

// Stats for customer
$q_active = mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE ($where_cust) AND status != 'completed'");
$active_count = (int)(mysqli_fetch_assoc($q_active)['c'] ?? 0);

$q_total_spent = mysqli_query($conn, "SELECT SUM(total_amount) as s, COUNT(*) as c FROM orders WHERE ($where_cust) AND payment_status='paid'");
$spent_data = mysqli_fetch_assoc($q_total_spent);
$total_spent = (float)($spent_data['s'] ?? 0);
$total_done = (int)($spent_data['c'] ?? 0);
?>

<!-- Customer Hero Card -->
<div class="mb-8 bg-gradient-to-r from-indigo-700 via-indigo-800 to-slate-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl relative overflow-hidden flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div class="relative z-10">
        <span class="inline-block px-3 py-1 bg-white/10 text-indigo-200 rounded-full text-xs font-semibold uppercase tracking-wider mb-2">
            Portal Pelanggan
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Halo, <?= htmlspecialchars($_SESSION['name']); ?>! 👋</h1>
        <p class="text-indigo-100 text-sm mt-1">Pantau status pencucian pakaian Anda secara langsung atau minta penjemputan ke rumah.</p>
    </div>
    <div class="relative z-10">
        <a href="pickup.php" class="px-5 py-3 bg-white text-indigo-900 hover:bg-indigo-50 rounded-2xl text-xs font-extrabold shadow-lg transition flex items-center gap-2">
            <i class="fas fa-truck-pickup text-sm"></i> Minta Jemput Cucian (Pickup)
        </a>
    </div>
    <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-indigo-500/20 rounded-full blur-2xl"></div>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cucian Aktif</p>
            <h3 class="text-2xl font-black text-slate-800 mt-1"><?= $active_count; ?> Pesanan</h3>
            <span class="text-[11px] text-indigo-600 font-bold">Sedang diproses</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-soap"></i>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Selesai Dicuci</p>
            <h3 class="text-2xl font-black text-emerald-600 mt-1"><?= $total_done; ?> Pesanan</h3>
            <span class="text-[11px] text-slate-400 font-bold">Total riwayat</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-check-double"></i>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between">
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Transaksi</p>
            <h3 class="text-2xl font-black text-slate-900 mt-1">Rp <?= number_format($total_spent, 0, ',', '.'); ?></h3>
            <span class="text-[11px] text-slate-400 font-bold">Akumulasi pembayaran</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0">
            <i class="fas fa-receipt"></i>
        </div>
    </div>
</div>

<!-- History List -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="p-6 border-b border-slate-100 flex justify-between items-center">
        <div>
            <h3 class="font-extrabold text-slate-800 text-base">Riwayat & Status Pesanan Cucian</h3>
            <p class="text-xs text-slate-400">Daftar cucian pakaian Anda beserta rincian biaya dan status progres</p>
        </div>
    </div>

    <div class="divide-y divide-slate-100">
        <?php 
        $has_history = false;
        while($order = mysqli_fetch_assoc($q_my_orders)): 
            $has_history = true;
            $st = $order['status'];
            
            // Progress steps logic
            $step = 1;
            if ($st === 'processing') $step = 2;
            elseif ($st === 'ironing') $step = 3;
            elseif ($st === 'ready') $step = 4;
            elseif ($st === 'completed') $step = 5;

            // Items query
            $q_it = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit 
                FROM order_details od 
                LEFT JOIN services s ON od.service_id = s.id 
                WHERE od.order_id = '{$order['id']}'");
        ?>
        <div class="p-6 sm:p-8 hover:bg-slate-50/50 transition">
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                <div>
                    <span class="font-mono text-base font-black text-indigo-600"><?= htmlspecialchars($order['order_code']); ?></span>
                    <span class="text-xs text-slate-400 ml-2"><i class="far fa-clock mr-1"></i><?= date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-black uppercase 
                        <?= $order['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                        <?= $order['payment_status'] === 'paid' ? 'Lunas' : 'Belum Lunas'; ?>
                    </span>
                    <span class="font-mono font-black text-slate-800 text-base">Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Visual Progress Stepper -->
            <div class="mb-6 px-2">
                <div class="relative flex justify-between items-center">
                    <!-- Progress Line -->
                    <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -z-10 rounded-full"></div>
                    <div class="absolute top-1/2 left-0 h-1 bg-indigo-600 -z-10 rounded-full transition-all duration-700" style="width: <?= ($step - 1) * 25; ?>%;"></div>

                    <!-- Step 1: Menunggu -->
                    <div class="flex flex-col items-center bg-white px-2">
                        <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 1 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <p class="text-[10px] sm:text-xs font-bold mt-1.5 <?= $step >= 1 ? 'text-indigo-600' : 'text-slate-400'; ?>">Menunggu</p>
                    </div>

                    <!-- Step 2: Dicuci -->
                    <div class="flex flex-col items-center bg-white px-2">
                        <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 2 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-soap"></i>
                        </div>
                        <p class="text-[10px] sm:text-xs font-bold mt-1.5 <?= $step >= 2 ? 'text-indigo-600' : 'text-slate-400'; ?>">Dicuci</p>
                    </div>

                    <!-- Step 3: Disetrika -->
                    <div class="flex flex-col items-center bg-white px-2">
                        <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 3 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-temperature-high"></i>
                        </div>
                        <p class="text-[10px] sm:text-xs font-bold mt-1.5 <?= $step >= 3 ? 'text-indigo-600' : 'text-slate-400'; ?>">Disetrika</p>
                    </div>

                    <!-- Step 4: Selesai -->
                    <div class="flex flex-col items-center bg-white px-2">
                        <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 4 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-check"></i>
                        </div>
                        <p class="text-[10px] sm:text-xs font-bold mt-1.5 <?= $step >= 4 ? 'text-indigo-600' : 'text-slate-400'; ?>">Selesai (Siap)</p>
                    </div>

                    <!-- Step 5: Diambil -->
                    <div class="flex flex-col items-center bg-white px-2">
                        <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 5 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <p class="text-[10px] sm:text-xs font-bold mt-1.5 <?= $step >= 5 ? 'text-indigo-600' : 'text-slate-400'; ?>">Sudah Diambil</p>
                    </div>
                </div>
            </div>

            <!-- Items and Target Date Details -->
            <div class="bg-slate-50 p-4 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs">
                <div>
                    <span class="text-slate-400 font-bold uppercase block mb-1">Item Layanan:</span>
                    <div class="flex flex-wrap gap-2">
                        <?php while($it = mysqli_fetch_assoc($q_it)): ?>
                            <span class="bg-white border border-slate-200 px-3 py-1 rounded-lg font-medium text-slate-700">
                                <?= htmlspecialchars($it['service_name'] ?? 'Layanan'); ?> (<?= $it['qty']; ?> <?= $it['unit'] ?? 'kg'; ?>)
                            </span>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-slate-400 font-bold uppercase block">Estimasi Selesai:</span>
                    <span class="font-extrabold text-slate-800">
                        <?= $order['estimate_completed_at'] ? date('d M Y, H:i', strtotime($order['estimate_completed_at'])) : '-'; ?>
                    </span>
                </div>
            </div>

        </div>
        <?php endwhile; ?>

        <?php if (!$has_history): ?>
        <div class="p-12 text-center text-slate-400">
            <i class="fas fa-tshirt text-4xl text-slate-300 block mb-3"></i>
            <h4 class="font-bold text-slate-700 text-base">Belum Ada Riwayat Cucian</h4>
            <p class="text-xs text-slate-400 mt-1">Anda belum memiliki transaksi laundry tercatat atau pakaian belum diinput oleh kasir.</p>
            <div class="mt-4">
                <a href="pickup.php" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-2">
                    <i class="fas fa-truck-pickup"></i> Minta Jemput Cucian Sekarang
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../layout/footer.php'; ?>
