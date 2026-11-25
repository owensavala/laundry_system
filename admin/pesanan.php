<?php
// admin/pesanan.php - Ultra Dynamic Order Pipeline & Kanban Management
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../includes/whatsapp.php';

$set = get_settings($conn);
$msg = '';
$err = '';

// Handle Traditional Payment Settlement Fallback
if (isset($_POST['bayar_lunas'])) {
    $order_id = (int)$_POST['order_id'];
    $method = $_POST['payment_method'] ?? 'Tunai';
    $cash_paid = max(0, (float)($_POST['cash_paid'] ?? 0));

    $q_ord = mysqli_query($conn, "SELECT total_amount FROM orders WHERE id=$order_id LIMIT 1");
    $tot = $q_ord ? (float)mysqli_fetch_assoc($q_ord)['total_amount'] : 0;
    if ($cash_paid < $tot) $cash_paid = $tot;
    $cash_change = $cash_paid - $tot;

    $stmt_pay = mysqli_prepare($conn, "UPDATE orders SET payment_status='paid', payment_method=?, cash_paid=?, cash_change=? WHERE id=?");
    mysqli_stmt_bind_param($stmt_pay, "sddi", $method, $cash_paid, $cash_change, $order_id);
    if (mysqli_stmt_execute($stmt_pay)) {
        $msg = 'Pembayaran berhasil dilunasi!';
    } else {
        $err = 'Gagal memperbarui status bayar: ' . mysqli_error($conn);
    }
}

// Fetch Active and Recent Orders for Kanban Pipeline
$q_pipeline = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.admin_id = u.id 
    ORDER BY o.created_at DESC LIMIT 150");

$orders_array = [];
$stats = [
    'active_count' => 0,
    'express_count' => 0,
    'ready_count' => 0,
    'unpaid_total' => 0,
    'pending_count' => 0,
    'processing_count' => 0,
    'ironing_count' => 0,
    'completed_count' => 0
];

while ($ord = mysqli_fetch_assoc($q_pipeline)) {
    // Fetch order details
    $oid = (int)$ord['id'];
    $q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit, s.icon, s.category 
        FROM order_details od 
        LEFT JOIN services s ON od.service_id = s.id 
        WHERE od.order_id = $oid");
    
    $items = [];
    while ($it = mysqli_fetch_assoc($q_items)) {
        $items[] = [
            'service_name' => $it['service_name'] ?? 'Layanan',
            'qty' => (float)$it['qty'],
            'unit' => $it['unit'] ?? 'kg',
            'subtotal' => (float)$it['subtotal'],
            'notes' => $it['notes'] ?? ''
        ];
    }
    $ord['items'] = $items;
    
    // Compute SLA badge
    $sla_status = 'normal';
    $sla_text = '-';
    if (!empty($ord['estimate_completed_at'])) {
        $est_time = strtotime($ord['estimate_completed_at']);
        $diff = $est_time - time();
        if ($ord['status'] === 'completed') {
            $sla_status = 'completed';
            $sla_text = 'Tuntas';
        } elseif ($diff < 0) {
            $sla_status = 'overdue';
            $sla_text = 'Terlambat ' . round(abs($diff) / 3600, 1) . ' Jam';
        } elseif ($diff <= 6 * 3600) {
            $sla_status = 'urgent';
            $sla_text = round($diff / 3600, 1) . ' Jam Lagi';
        } else {
            $sla_status = 'ontime';
            $sla_text = date('d M, H:i', $est_time);
        }
    }
    $ord['sla_status'] = $sla_status;
    $ord['sla_text'] = $sla_text;

    // WA ready direct link
    $wa_msg_preview = build_wa_message($set['wa_template_ready'], [
        'customer_name' => $ord['customer_name'],
        'order_code' => $ord['order_code'],
        'laundry_name' => $set['nama_laundry']
    ]);
    $ord['wa_ready_url'] = get_wa_direct_link($ord['customer_phone'], $wa_msg_preview);

    // Update stats
    if (in_array($ord['status'], ['pending', 'processing', 'ironing', 'ready'])) {
        $stats['active_count']++;
    }
    if (($ord['speed'] ?? '') === 'express') {
        $stats['express_count']++;
    }
    if ($ord['status'] === 'ready') {
        $stats['ready_count']++;
    }
    if ($ord['payment_status'] === 'unpaid') {
        $stats['unpaid_total'] += (float)$ord['total_amount'];
    }

    if (isset($stats[$ord['status'] . '_count'])) {
        $stats[$ord['status'] . '_count']++;
    }

    $orders_array[] = $ord;
}

require_once '../layout/header.php';
?>

<!-- Alpine.js Pipeline Workspace -->
<div x-data="orderPipelineApp()" class="max-w-7xl mx-auto pb-16">

    <!-- Top Header Bar -->
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/90 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-xl shadow-lg shadow-indigo-500/20">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Antrean & Pipeline Pesanan</h1>
                <p class="text-xs text-slate-500">Pantau pergerakan cucian secara real-time dari cuci hingga serah terima.</p>
            </div>
        </div>
        
        <!-- Controls & Mode Switcher -->
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <!-- View Switcher -->
            <div class="flex items-center bg-slate-100 p-1 rounded-2xl text-xs font-bold">
                <button type="button" @click="viewMode = 'kanban'; window.playTone('pop')" 
                        :class="viewMode === 'kanban' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" 
                        class="px-3.5 py-1.5 rounded-xl transition flex items-center gap-1.5">
                    <i class="fas fa-columns-3"></i> Kanban Board
                </button>
                <button type="button" @click="viewMode = 'table'; window.playTone('pop')" 
                        :class="viewMode === 'table' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" 
                        class="px-3.5 py-1.5 rounded-xl transition flex items-center gap-1.5">
                    <i class="fas fa-table-list"></i> Tabel List
                </button>
            </div>

            <a href="pos.php" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-indigo-200">
                <i class="fas fa-plus"></i> Kasir POS
            </a>
        </div>
    </div>

    <!-- Live KPI Summary Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        
        <div class="bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fas fa-clock-rotate-left"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Antrean Aktif</span>
                <h3 class="text-xl font-black text-slate-800" x-text="stats.active_count"></h3>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fas fa-bolt"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pesanan Express ⚡</span>
                <h3 class="text-xl font-black text-slate-800" x-text="stats.express_count"></h3>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fas fa-circle-check"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Siap Diambil</span>
                <h3 class="text-xl font-black text-slate-800" x-text="stats.ready_count"></h3>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg font-bold shrink-0">
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tagihan Belum Lunas</span>
                <h3 class="text-base font-black text-rose-600 font-mono" x-text="'Rp ' + parseInt(stats.unpaid_total).toLocaleString('id-ID')"></h3>
            </div>
        </div>

    </div>

    <!-- Search & Fast Filter Bar -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs mb-6 flex flex-col sm:flex-row items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative w-full sm:w-80">
            <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" x-model="searchQuery" placeholder="Cari Kode Nota, Nama, No HP..." 
                   class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <button type="button" x-show="searchQuery.length > 0" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                <i class="fas fa-times-circle"></i>
            </button>
        </div>

        <!-- Filter Badges -->
        <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto text-xs font-bold pb-1 sm:pb-0">
            <button type="button" @click="filterSpeed = 'all'" 
                    :class="filterSpeed === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                    class="px-3 py-1.5 rounded-xl transition">Semua Kecepatan</button>
            <button type="button" @click="filterSpeed = 'express'" 
                    :class="filterSpeed === 'express' ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                    class="px-3 py-1.5 rounded-xl transition flex items-center gap-1">
                <i class="fas fa-bolt text-[10px]"></i> Express
            </button>
            <button type="button" @click="filterPayment = 'unpaid'" 
                    :class="filterPayment === 'unpaid' ? 'bg-rose-500 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                    class="px-3 py-1.5 rounded-xl transition">Belum Lunas</button>
            <button type="button" x-show="filterSpeed !== 'all' || filterPayment !== 'all' || searchQuery.length > 0" 
                    @click="filterSpeed = 'all'; filterPayment = 'all'; searchQuery = ''" 
                    class="text-xs font-bold text-rose-500 hover:underline px-2">Reset</button>
        </div>
    </div>

    <!-- 1. KANBAN BOARD VIEW -->
    <div x-show="viewMode === 'kanban'" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 items-start">
        
        <!-- Column 1: Menunggu -->
        <div class="bg-slate-100/80 p-3.5 rounded-3xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between px-2 pt-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Menunggu</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white text-slate-600 border border-slate-200" 
                      x-text="getOrdersByStatus('pending').length"></span>
            </div>

            <!-- Cards in Menunggu -->
            <div class="space-y-3 min-h-[150px]">
                <template x-for="ord in getOrdersByStatus('pending')" :key="ord.id">
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-black text-indigo-600" x-text="ord.order_code"></span>
                            <span x-show="ord.speed === 'express'" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-100 text-amber-800">⚡ Express</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold text-slate-800 truncate" x-text="ord.customer_name"></h4>
                            <p class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                                <i class="fab fa-whatsapp text-emerald-600"></i>
                                <span x-text="ord.customer_phone"></span>
                            </p>
                        </div>
                        <div class="text-[11px] text-slate-500 border-t border-slate-100 pt-2 space-y-1">
                            <template x-for="it in ord.items.slice(0, 2)" :key="it.service_name">
                                <div class="flex justify-between">
                                    <span class="truncate max-w-[120px]" x-text="it.service_name"></span>
                                    <span class="font-bold" x-text="it.qty + ' ' + it.unit"></span>
                                </div>
                            </template>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-slate-800" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></span>
                            <button type="button" @click="advanceOrderStatus(ord.id)" 
                                    class="px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-[11px] font-bold transition flex items-center gap-1 shadow-xs">
                                <span>Cuci</span> <i class="fas fa-arrow-right text-[9px]"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="getOrdersByStatus('pending').length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                    Kosong
                </div>
            </div>
        </div>

        <!-- Column 2: Sedang Dicuci -->
        <div class="bg-slate-100/80 p-3.5 rounded-3xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between px-2 pt-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <h3 class="text-xs font-black text-amber-800 uppercase tracking-wider">Sedang Dicuci</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white text-slate-600 border border-slate-200" 
                      x-text="getOrdersByStatus('processing').length"></span>
            </div>

            <!-- Cards in Processing -->
            <div class="space-y-3 min-h-[150px]">
                <template x-for="ord in getOrdersByStatus('processing')" :key="ord.id">
                    <div class="bg-white p-4 rounded-2xl border border-amber-200 shadow-xs hover:shadow-md transition space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-black text-indigo-600" x-text="ord.order_code"></span>
                            <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                <i class="fas fa-soap mr-1"></i> Wash
                            </span>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold text-slate-800 truncate" x-text="ord.customer_name"></h4>
                            <p class="text-[10px] text-slate-400 mt-0.5" x-text="'Target: ' + ord.sla_text"></p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-slate-800" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></span>
                            <button type="button" @click="advanceOrderStatus(ord.id)" 
                                    class="px-2.5 py-1.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-[11px] font-bold transition flex items-center gap-1 shadow-xs">
                                <span>Setrika</span> <i class="fas fa-arrow-right text-[9px]"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="getOrdersByStatus('processing').length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                    Kosong
                </div>
            </div>
        </div>

        <!-- Column 3: Sedang Disetrika -->
        <div class="bg-slate-100/80 p-3.5 rounded-3xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between px-2 pt-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-500 animate-pulse"></span>
                    <h3 class="text-xs font-black text-purple-800 uppercase tracking-wider">Disetrika</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white text-slate-600 border border-slate-200" 
                      x-text="getOrdersByStatus('ironing').length"></span>
            </div>

            <!-- Cards in Ironing -->
            <div class="space-y-3 min-h-[150px]">
                <template x-for="ord in getOrdersByStatus('ironing')" :key="ord.id">
                    <div class="bg-white p-4 rounded-2xl border border-purple-200 shadow-xs hover:shadow-md transition space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-black text-indigo-600" x-text="ord.order_code"></span>
                            <span class="text-[10px] font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded">
                                <i class="fas fa-temperature-high mr-1"></i> Iron
                            </span>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold text-slate-800 truncate" x-text="ord.customer_name"></h4>
                            <p class="text-[10px] text-slate-400 mt-0.5" x-text="'Target: ' + ord.sla_text"></p>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-slate-800" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></span>
                            <button type="button" @click="advanceOrderStatus(ord.id)" 
                                    class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[11px] font-bold transition flex items-center gap-1 shadow-xs">
                                <span>Selesai</span> <i class="fas fa-check text-[9px]"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="getOrdersByStatus('ironing').length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                    Kosong
                </div>
            </div>
        </div>

        <!-- Column 4: Selesai (Siap Diambil) -->
        <div class="bg-slate-100/80 p-3.5 rounded-3xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between px-2 pt-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h3 class="text-xs font-black text-emerald-800 uppercase tracking-wider">Siap Diambil</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white text-slate-600 border border-slate-200" 
                      x-text="getOrdersByStatus('ready').length"></span>
            </div>

            <!-- Cards in Ready -->
            <div class="space-y-3 min-h-[150px]">
                <template x-for="ord in getOrdersByStatus('ready')" :key="ord.id">
                    <div class="bg-white p-4 rounded-2xl border border-emerald-300 shadow-xs hover:shadow-md transition space-y-3 ring-1 ring-emerald-500/10">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-black text-emerald-700" x-text="ord.order_code"></span>
                            <span :class="ord.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" 
                                  class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded" 
                                  x-text="ord.payment_status === 'paid' ? 'LUNAS' : 'BELUM'"></span>
                        </div>
                        <div>
                            <h4 class="text-xs font-extrabold text-slate-800 truncate" x-text="ord.customer_name"></h4>
                            <a :href="ord.wa_ready_url" target="_blank" 
                               class="text-[10px] font-bold text-emerald-700 hover:underline flex items-center gap-1 mt-1">
                                <i class="fab fa-whatsapp"></i> Chat: Siap Diambil
                            </a>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-black font-mono text-slate-800" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></span>
                            <button type="button" @click="advanceOrderStatus(ord.id)" 
                                    class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-[11px] font-bold transition flex items-center gap-1 shadow-xs">
                                <span>Serahkan</span> <i class="fas fa-hand-holding-hand text-[9px]"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <div x-show="getOrdersByStatus('ready').length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                    Kosong
                </div>
            </div>
        </div>

        <!-- Column 5: Sudah Diambil (Completed) -->
        <div class="bg-slate-100/80 p-3.5 rounded-3xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between px-2 pt-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <h3 class="text-xs font-black text-blue-800 uppercase tracking-wider">Sudah Diambil</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-white text-slate-600 border border-slate-200" 
                      x-text="getOrdersByStatus('completed').length"></span>
            </div>

            <!-- Cards in Completed -->
            <div class="space-y-3 min-h-[150px]">
                <template x-for="ord in getOrdersByStatus('completed').slice(0, 10)" :key="ord.id">
                    <div class="bg-white/80 p-3.5 rounded-2xl border border-slate-200 shadow-2xs space-y-2 opacity-90 hover:opacity-100 transition">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-slate-600" x-text="ord.order_code"></span>
                            <span class="text-[9px] font-bold bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded">Tuntas</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-700 truncate" x-text="ord.customer_name"></h4>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-100">
                            <span class="font-mono" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></span>
                            <a :href="'cetak_nota.php?id=' + ord.id" target="_blank" class="hover:text-indigo-600" title="Cetak Nota">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </div>
                </template>
                <div x-show="getOrdersByStatus('completed').length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                    Kosong
                </div>
            </div>
        </div>

    </div>

    <!-- 2. INTERACTIVE TABLE VIEW -->
    <div x-show="viewMode === 'table'" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3.5 px-4">No. Nota</th>
                        <th class="py-3.5 px-4">Pelanggan</th>
                        <th class="py-3.5 px-4">Layanan & Pewangi</th>
                        <th class="py-3.5 px-4">Estimasi Selesai</th>
                        <th class="py-3.5 px-4">Status Cucian</th>
                        <th class="py-3.5 px-4">Pembayaran</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <template x-for="ord in filteredOrders" :key="ord.id">
                        <tr class="hover:bg-indigo-50/30 transition">
                            <!-- Order Code -->
                            <td class="py-4 px-4 font-mono font-black text-indigo-700 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span x-text="ord.order_code"></span>
                                    <span x-show="ord.speed === 'express'" class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-amber-100 text-amber-800">⚡</span>
                                </div>
                                <span class="text-[10px] text-slate-400 font-sans block" x-text="ord.created_at"></span>
                            </td>

                            <!-- Customer -->
                            <td class="py-4 px-4">
                                <h4 class="font-extrabold text-slate-900" x-text="ord.customer_name"></h4>
                                <a :href="'https://wa.me/' + (ord.customer_phone || '').replace(/[^0-9]/g, '')" target="_blank" 
                                   class="text-[11px] text-emerald-600 hover:underline flex items-center gap-1">
                                    <i class="fab fa-whatsapp"></i> <span x-text="ord.customer_phone"></span>
                                </a>
                            </td>

                            <!-- Services & Perfume -->
                            <td class="py-4 px-4 space-y-1">
                                <template x-for="it in ord.items" :key="it.service_name">
                                    <div class="text-[11px] text-slate-600">
                                        <span class="font-bold" x-text="it.qty + ' ' + it.unit"></span>
                                        <span x-text="it.service_name"></span>
                                    </div>
                                </template>
                                <span class="inline-block px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-[10px] font-bold" x-text="'🌸 ' + (ord.perfume || 'Sakura')"></span>
                            </td>

                            <!-- SLA -->
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span :class="{
                                    'text-rose-600 font-bold': ord.sla_status === 'overdue',
                                    'text-amber-600 font-bold': ord.sla_status === 'urgent',
                                    'text-slate-600': ord.sla_status === 'ontime',
                                    'text-emerald-600': ord.sla_status === 'completed'
                                }" x-text="ord.sla_text"></span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span :class="getStatusBadgeClass(ord.status)" 
                                      class="px-3 py-1 rounded-full text-[11px] font-black uppercase" 
                                      x-text="getStatusLabel(ord.status)"></span>
                            </td>

                            <!-- Payment Badge & Amount -->
                            <td class="py-4 px-4 whitespace-nowrap">
                                <div class="font-mono font-black text-slate-900" x-text="'Rp ' + parseInt(ord.total_amount).toLocaleString('id-ID')"></div>
                                <template x-if="ord.payment_status === 'paid'">
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        Lunas (<span x-text="ord.payment_method"></span>)
                                    </span>
                                </template>
                                <template x-if="ord.payment_status !== 'paid'">
                                    <button type="button" @click="openModalBayar(ord)" 
                                            class="text-[10px] font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 px-2 py-0.5 rounded-full border border-rose-200 transition">
                                        Belum Lunas &bull; Bayar
                                    </button>
                                </template>
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-4 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a :href="'cetak_nota.php?id=' + ord.id" target="_blank" 
                                       class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition" title="Cetak Nota">
                                        <i class="fas fa-print"></i>
                                    </a>

                                    <template x-if="ord.status !== 'completed'">
                                        <button type="button" @click="advanceOrderStatus(ord.id)" 
                                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold transition flex items-center gap-1 shadow-2xs">
                                            <span>Proses</span> <i class="fas fa-chevron-right text-[10px]"></i>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Pelunasan Pembayaran -->
    <div x-show="showPaymentModal" style="display: none;" 
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        
        <div @click.away="showPaymentModal = false" class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-money-bill-wave text-emerald-600"></i> Pelunasan Tagihan
                </h3>
                <button type="button" @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                <span class="text-[11px] text-slate-400 font-bold uppercase">Nota & Pelanggan:</span>
                <p class="font-mono font-black text-slate-800" x-text="activeOrder.order_code + ' - ' + activeOrder.customer_name"></p>
                <div class="pt-2 flex justify-between items-center text-xs">
                    <span class="text-slate-500">Total Tagihan:</span>
                    <span class="font-mono font-black text-base text-emerald-600" x-text="'Rp ' + parseInt(activeOrder.total_amount || 0).toLocaleString('id-ID')"></span>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Metode Pembayaran</label>
                <select x-model="payMethod" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="Tunai">Tunai / Cash</option>
                    <option value="Transfer QRIS">QRIS</option>
                    <option value="Transfer BCA">Transfer Bank BCA</option>
                    <option value="Transfer Mandiri">Transfer Mandiri / BRI / BNI</option>
                </select>
            </div>

            <div x-show="payMethod === 'Tunai'" class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-400 uppercase">Uang Tunai Diterima</label>
                <input type="number" min="0" step="1000" x-model.number="payCash" 
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <div class="flex gap-1.5">
                    <button type="button" @click="payCash = activeOrder.total_amount" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold">Uang Pas</button>
                    <button type="button" @click="payCash = 50000" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold">50 rb</button>
                    <button type="button" @click="payCash = 100000" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold">100 rb</button>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" @click="showPaymentModal = false" class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-xl text-xs font-bold">Batal</button>
                <button type="button" @click="confirmPaymentSettlement()" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-200 transition">
                    Konfirmasi Lunas
                </button>
            </div>
        </div>

    </div>

</div>

<script>
function orderPipelineApp() {
    return {
        orders: <?= json_encode($orders_array); ?>,
        stats: <?= json_encode($stats); ?>,
        viewMode: 'kanban',
        searchQuery: '',
        filterSpeed: 'all',
        filterPayment: 'all',
        
        // Modal state
        showPaymentModal: false,
        activeOrder: {},
        payMethod: 'Tunai',
        payCash: 0,

        get filteredOrders() {
            return this.orders.filter(o => {
                const q = this.searchQuery.toLowerCase();
                const matchSearch = !q || 
                    (o.order_code && o.order_code.toLowerCase().includes(q)) || 
                    (o.customer_name && o.customer_name.toLowerCase().includes(q)) || 
                    (o.customer_phone && o.customer_phone.includes(q));
                
                const matchSpeed = (this.filterSpeed === 'all') || (o.speed === this.filterSpeed);
                const matchPayment = (this.filterPayment === 'all') || (o.payment_status === this.filterPayment);

                return matchSearch && matchSpeed && matchPayment;
            });
        },

        getOrdersByStatus(st) {
            return this.filteredOrders.filter(o => o.status === st);
        },

        getStatusBadgeClass(st) {
            switch(st) {
                case 'pending': return 'bg-slate-100 text-slate-600';
                case 'processing': return 'bg-amber-100 text-amber-800';
                case 'ironing': return 'bg-purple-100 text-purple-800';
                case 'ready': return 'bg-emerald-100 text-emerald-800';
                case 'completed': return 'bg-blue-100 text-blue-800';
                default: return 'bg-slate-100 text-slate-700';
            }
        },

        getStatusLabel(st) {
            switch(st) {
                case 'pending': return 'Menunggu';
                case 'processing': return 'Dicuci';
                case 'ironing': return 'Disetrika';
                case 'ready': return 'Siap Diambil';
                case 'completed': return 'Sudah Diambil';
                default: return st;
            }
        },

        async advanceOrderStatus(orderId) {
            window.playTone('pop');
            try {
                const response = await fetch('../api/update_status_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId, action: 'advance' })
                });

                const data = await response.json();
                if (data.success && data.order) {
                    const target = this.orders.find(o => o.id == orderId);
                    if (target) {
                        target.status = data.order.status;
                        if (data.order.status === 'ready' && data.order.wa_ready_url) {
                            target.wa_ready_url = data.order.wa_ready_url;
                        }
                    }
                    this.recalcStats();
                    window.playTone('success');
                    window.showToast('Status berhasil dimajukan: ' + this.getStatusLabel(data.order.status), 'success');
                } else {
                    window.playTone('error');
                    window.showToast(data.message || 'Gagal mengubah status.', 'error');
                }
            } catch (err) {
                console.error(err);
                window.location.reload();
            }
        },

        openModalBayar(order) {
            window.playTone('pop');
            this.activeOrder = order;
            this.payMethod = 'Tunai';
            this.payCash = parseFloat(order.total_amount);
            this.showPaymentModal = true;
        },

        async confirmPaymentSettlement() {
            window.playTone('beep');
            try {
                const response = await fetch('../api/update_status_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        order_id: this.activeOrder.id,
                        payment_status: 'paid',
                        payment_method: this.payMethod,
                        cash_paid: this.payCash
                    })
                });

                const data = await response.json();
                if (data.success) {
                    const target = this.orders.find(o => o.id == this.activeOrder.id);
                    if (target) {
                        target.payment_status = 'paid';
                        target.payment_method = this.payMethod;
                    }
                    this.recalcStats();
                    this.showPaymentModal = false;
                    window.playTone('success');
                    window.showToast('Pembayaran berhasil dilunasi!', 'success');
                } else {
                    window.playTone('error');
                    window.showToast(data.message || 'Gagal menyimpan pembayaran.', 'error');
                }
            } catch (err) {
                console.error(err);
                window.location.reload();
            }
        },

        recalcStats() {
            let active = 0, express = 0, ready = 0, unpaid = 0;
            this.orders.forEach(o => {
                if (['pending', 'processing', 'ironing', 'ready'].includes(o.status)) active++;
                if (o.speed === 'express') express++;
                if (o.status === 'ready') ready++;
                if (o.payment_status === 'unpaid') unpaid += parseFloat(o.total_amount);
            });
            this.stats.active_count = active;
            this.stats.express_count = express;
            this.stats.ready_count = ready;
            this.stats.unpaid_total = unpaid;
        }
    };
}
</script>

<?php require_once '../layout/footer.php'; ?>
