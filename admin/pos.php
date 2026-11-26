<?php
// admin/pos.php - Ultra Modern POS Order Entry System
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../includes/whatsapp.php';

$set = get_settings($conn);
$msg = '';
$err = '';
$new_order_data = null;

// Fetch all services
$q_services = mysqli_query($conn, "SELECT * FROM services ORDER BY category ASC, service_type ASC, service_name ASC");
$service_list = [];
while ($s = mysqli_fetch_assoc($q_services)) {
    if (empty($s['category'])) {
        $s['category'] = ($s['service_type'] === 'kiloan') ? 'Kiloan' : 'Satuan';
    }
    if (empty($s['icon'])) {
        $s['icon'] = 'fa-shirt';
    }
    $service_list[] = $s;
}

// Prefill from Pickup Request if directed from admin/pickup.php
$init_pickup_id = (int)($_GET['pickup_id'] ?? 0);
$init_name = trim($_GET['name'] ?? '');
$init_phone = trim($_GET['phone'] ?? '');
$init_address = trim($_GET['address'] ?? '');
$init_notes = trim($_GET['notes'] ?? '');

// Fetch existing customers for autocomplete
$q_customers = mysqli_query($conn, "SELECT id, name, phone, address, COALESCE(deposit_balance, 0) as deposit_balance, COALESCE(loyalty_points, 0) as loyalty_points FROM users WHERE role='customer' ORDER BY name ASC LIMIT 150");
$customer_list = [];
while ($c = mysqli_fetch_assoc($q_customers)) {
    $customer_list[] = $c;
}

// Handle Traditional Form Submission Fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $pickup_id = !empty($_POST['pickup_id']) ? (int)$_POST['pickup_id'] : null;

    $payment_status = in_array($_POST['payment_status'] ?? '', ['paid', 'unpaid']) ? $_POST['payment_status'] : 'unpaid';
    $payment_method = trim($_POST['payment_method'] ?? 'Tunai');
    $notes = trim($_POST['notes'] ?? '');
    $perfume = trim($_POST['perfume'] ?? 'Sakura Blossom');
    $speed = in_array($_POST['speed'] ?? '', ['regular', 'express']) ? $_POST['speed'] : 'regular';
    $discount_amount = max(0, (float)($_POST['discount_amount'] ?? 0));
    $cash_paid = max(0, (float)($_POST['cash_paid'] ?? 0));
    $admin_id = $_SESSION['user_id'];

    $services_data = json_decode($_POST['items_json'] ?? '[]', true);

    if (empty($customer_name) || empty($customer_phone)) {
        $err = 'Nama pelanggan dan nomor HP/WhatsApp wajib diisi!';
    } elseif (empty($services_data)) {
        $err = 'Mohon pilih minimal satu layanan laundry!';
    } else {
        // Customer Sync
        $c_phone_safe = mysqli_real_escape_string($conn, $customer_phone);
        $c_name_safe = mysqli_real_escape_string($conn, $customer_name);
        $c_addr_safe = mysqli_real_escape_string($conn, $customer_address);
        
        $customer_id = null;
        $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE phone='$c_phone_safe' LIMIT 1");
        if ($cek_user && mysqli_num_rows($cek_user) > 0) {
            $customer_id = (int)mysqli_fetch_assoc($cek_user)['id'];
            mysqli_query($conn, "UPDATE users SET name='$c_name_safe', address='$c_addr_safe' WHERE id=$customer_id");
        } else {
            $c_email = 'cust_' . preg_replace('/[^0-9]/', '', $c_phone_safe) . '@customer.local';
            $dummy_pw = password_hash('customer123', PASSWORD_DEFAULT);
            mysqli_query($conn, "INSERT INTO users (name, email, password, phone, role, address) 
                VALUES ('$c_name_safe', '$c_email', '$dummy_pw', '$c_phone_safe', 'customer', '$c_addr_safe')");
            $customer_id = (int)mysqli_insert_id($conn);
        }

        // Sequential Order Code: LND-YYYYMMDD-XXX
        $today_prefix = "LND-" . date('Ymd') . "-";
        $q_seq = mysqli_query($conn, "SELECT order_code FROM orders WHERE order_code LIKE '$today_prefix%' ORDER BY id DESC LIMIT 1");
        $next_num = 1;
        if ($q_seq && mysqli_num_rows($q_seq) > 0) {
            $last_code = mysqli_fetch_assoc($q_seq)['order_code'];
            $parts = explode('-', $last_code);
            $last_seq = (int)end($parts);
            if ($last_seq >= 1) $next_num = $last_seq + 1;
        }
        $order_code = $today_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        while (true) {
            $check_unique = mysqli_query($conn, "SELECT id FROM orders WHERE order_code = '$order_code' LIMIT 1");
            if (!$check_unique || mysqli_num_rows($check_unique) == 0) break;
            $next_num++;
            $order_code = $today_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        }

        // Totals
        $gross_total = 0;
        $max_duration = 48;
        foreach ($services_data as $item) {
            $sub = (float)$item['qty'] * (float)$item['price'];
            $gross_total += $sub;
            if ((int)($item['duration_hours'] ?? 48) > $max_duration) {
                $max_duration = (int)$item['duration_hours'];
            }
        }

        if ($speed === 'express') {
            $gross_total = round($gross_total * 1.25);
            $max_duration = max(12, round($max_duration / 2));
        }

        $total_amount = max(0, $gross_total - $discount_amount);
        $cash_change = max(0, $cash_paid - $total_amount);

        $created_at = date('Y-m-d H:i:s');
        $estimate_completed_at = date('Y-m-d H:i:s', strtotime("+$max_duration hours"));

        $stmt_ord = mysqli_prepare($conn, "INSERT INTO orders 
            (order_code, customer_id, pickup_id, customer_name, customer_phone, admin_id, status, payment_status, payment_method, total_amount, discount_amount, cash_paid, cash_change, notes, perfume, speed, estimate_completed_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        mysqli_stmt_bind_param($stmt_ord, "siisssssddddsssss", 
            $order_code, $customer_id, $pickup_id, $customer_name, $customer_phone, $admin_id, 
            $payment_status, $payment_method, $total_amount, $discount_amount, $cash_paid, $cash_change, $notes, $perfume, $speed, $estimate_completed_at, $created_at);

        if (mysqli_stmt_execute($stmt_ord)) {
            $order_id = mysqli_insert_id($conn);

            if ($pickup_id && $pickup_id > 0) {
                mysqli_query($conn, "UPDATE pickup_requests SET status='picked_up', order_id='$order_id' WHERE id='$pickup_id'");
            }

            foreach ($services_data as $item) {
                $sid = (int)$item['id'];
                $qty = (float)$item['qty'];
                $sub = $qty * (float)$item['price'];
                $item_notes = trim($item['notes'] ?? '');
                $stmt_det = mysqli_prepare($conn, "INSERT INTO order_details (order_id, service_id, qty, subtotal, notes) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_det, "iidds", $order_id, $sid, $qty, $sub, $item_notes);
                mysqli_stmt_execute($stmt_det);
            }

            // WhatsApp Notification
            $tracking_url = base_url("index.php?kode=" . $order_code);
            $wa_text = build_wa_message($set['wa_template_order'], [
                'customer_name' => $customer_name,
                'order_code' => $order_code,
                'total_amount' => $total_amount,
                'link_tracking' => $tracking_url,
                'laundry_name' => $set['nama_laundry']
            ]);
            $wa_send_result = send_wa_notification($conn, $customer_phone, $wa_text);

            $new_order_data = [
                'id' => $order_id,
                'order_code' => $order_code,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'total_amount' => $total_amount,
                'discount_amount' => $discount_amount,
                'cash_paid' => $cash_paid,
                'cash_change' => $cash_change,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'perfume' => $perfume,
                'speed' => $speed,
                'items' => $services_data,
                'created_at' => date('H:i'),
                'wa_direct_url' => $wa_send_result['direct_url'] ?? '',
                'wa_auto_sent' => $wa_send_result['success'] ?? false
            ];

            $msg = "Pesanan berhasil dibuat dengan kode: <strong>$order_code</strong>!";
        } else {
            $err = 'Gagal menyimpan pesanan: ' . mysqli_error($conn);
        }
    }
}

require_once '../layout/header.php';
?>

<!-- Alpine.js POS App -->
<div x-data="posApp()" class="max-w-7xl mx-auto pb-16">

    <!-- Top Navigation & Cashier Bar -->
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/90 backdrop-blur-md p-4 sm:p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center text-xl shadow-lg shadow-indigo-500/20">
                <i class="fas fa-cash-register"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Point of Sale (POS)</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200/60 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Kasir Aktif
                    </span>
                </div>
                <p class="text-xs text-slate-500">Petugas: <strong class="text-slate-700"><?= htmlspecialchars($_SESSION['name'] ?? 'Kasir'); ?></strong> &bull; <?= htmlspecialchars($set['nama_laundry']); ?></p>
            </div>
        </div>
        
        <!-- Action Buttons & Shortcuts Hint -->
        <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
            <div class="hidden xl:flex items-center gap-1.5 text-[11px] text-slate-500 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                <i class="fas fa-keyboard text-indigo-500 mr-1"></i>
                <span><kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded text-[10px] font-mono font-bold">F2</kbd> Cari Pelanggan</span>
                <span>&bull; <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded text-[10px] font-mono font-bold">F8</kbd> Uang Pas</span>
                <span>&bull; <kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded text-[10px] font-mono font-bold">Ctrl+Enter</kbd> Simpan</span>
            </div>

            <a href="pesanan.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                <i class="fas fa-layer-group text-indigo-600"></i> Antrean Cucian
            </a>
            
            <button type="button" @click="resetCart()" class="px-3 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition flex items-center gap-1.5" title="Reset Formulir">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
        </div>
    </div>

    <!-- Error Banner -->
    <?php if ($err): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm px-4 py-3 rounded-2xl flex items-center gap-2 shadow-xs">
            <i class="fas fa-circle-exclamation text-red-500 text-base"></i>
            <span><?= $err; ?></span>
        </div>
    <?php endif; ?>

    <!-- Main Dual Column Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT COLUMN: Service Catalog & Visual Grid (7 cols) -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Catalog Search & Filter Controls -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-boxes-stacked text-indigo-600"></i> Katalog Layanan
                        </h2>
                        <p class="text-xs text-slate-400">Klik layanan untuk menambahkan ke keranjang pesanan</p>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" x-model="searchService" placeholder="Cari nama layanan..." 
                               class="w-full pl-8 pr-8 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                        <button type="button" x-show="searchService.length > 0" @click="searchService = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-1 no-scrollbar text-xs font-bold">
                    <button type="button" @click="selectedCategory = 'all'" 
                            :class="selectedCategory === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                            class="px-3.5 py-1.5 rounded-xl transition shrink-0 flex items-center gap-1.5">
                        <i class="fas fa-grid-2"></i> Semua (<span x-text="allServices.length"></span>)
                    </button>
                    <template x-for="cat in availableCategories" :key="cat">
                        <button type="button" @click="selectedCategory = cat" 
                                :class="selectedCategory === cat ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" 
                                class="px-3.5 py-1.5 rounded-xl transition shrink-0 capitalize flex items-center gap-1.5">
                            <span x-text="cat"></span>
                            <span class="text-[10px] opacity-70" x-text="'(' + countByCategory(cat) + ')'"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Service Card Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3.5">
                <template x-for="svc in displayedServices" :key="svc.id">
                    <div @click="addOrIncrement(svc)" 
                         class="group relative bg-white p-4 rounded-2xl border transition-all duration-200 cursor-pointer hover:shadow-lg hover:-translate-y-0.5 flex flex-col justify-between select-none"
                         :class="getItemQty(svc.id) > 0 ? 'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/20' : 'border-slate-200/80 hover:border-indigo-300'">
                        
                        <!-- Top Row: Icon + In-cart Badge -->
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shadow-xs transition group-hover:scale-110"
                                 :class="svc.service_type === 'kiloan' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                                <i :class="'fas ' + (svc.icon || 'fa-shirt')"></i>
                            </div>

                            <!-- Cart quantity badge -->
                            <div x-show="getItemQty(svc.id) > 0" 
                                 class="px-2 py-0.5 bg-indigo-600 text-white text-[11px] font-black rounded-lg shadow-xs flex items-center gap-1 animate-pulse">
                                <i class="fas fa-check text-[9px]"></i>
                                <span x-text="getItemQty(svc.id) + ' ' + svc.unit"></span>
                            </div>
                        </div>

                        <!-- Service Title & Category -->
                        <div class="mb-3">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-0.5" x-text="svc.category || svc.service_type"></span>
                            <h3 class="text-xs font-bold text-slate-800 line-clamp-2 leading-snug group-hover:text-indigo-600 transition" x-text="svc.service_name"></h3>
                        </div>

                        <!-- Price & Duration -->
                        <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-1.5">
                            <div class="whitespace-nowrap">
                                <span class="text-[10px] text-slate-400 block">Tarif:</span>
                                <span class="text-xs font-extrabold font-mono text-indigo-700" x-text="'Rp ' + parseInt(svc.price).toLocaleString('id-ID') + '/' + svc.unit"></span>
                            </div>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md flex items-center gap-1 whitespace-nowrap shrink-0">
                                <i class="far fa-clock text-[9px] text-slate-400"></i>
                                <span x-text="svc.duration_hours + ' Jam'"></span>
                            </span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty Service Search State -->
            <div x-show="displayedServices.length === 0" class="bg-white p-8 rounded-3xl border border-slate-200 text-center">
                <i class="fas fa-box-open text-slate-300 text-3xl mb-2"></i>
                <p class="text-xs font-bold text-slate-600">Tidak ada layanan yang sesuai pencarian "<span x-text="searchService"></span>"</p>
                <button type="button" @click="searchService = ''; selectedCategory = 'all'" class="mt-2 text-xs font-bold text-indigo-600 hover:underline">
                    Tampilkan Semua Layanan
                </button>
            </div>

            <!-- Quick Service Tips -->
            <div class="p-4 rounded-2xl bg-indigo-50/60 border border-indigo-100 text-indigo-900 flex items-start gap-3">
                <i class="fas fa-lightbulb text-indigo-600 text-sm mt-0.5"></i>
                <div class="text-xs">
                    <strong class="font-bold">Tips Kasir Cepat:</strong> Klik sekali untuk menambahkan item ke keranjang. Untuk cucian kiloan (contoh: 2.5 kg), sesuaikan jumlah desimal langsung di kolom keranjang sebelah kanan.
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Customer, Cart, Speed, Perfume & Payment (5 cols) -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Card 1: Data Pelanggan -->
            <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-user-circle text-indigo-600"></i> Data Pelanggan
                    </h2>
                    
                    <!-- Customer Type Switcher -->
                    <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl text-[11px] font-bold">
                        <button type="button" @click="isNewCustomer = false" 
                                :class="!isNewCustomer ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-500 hover:text-slate-700'" 
                                class="px-2.5 py-1 rounded-lg transition">Cari</button>
                        <button type="button" @click="isNewCustomer = true; clearCustomer()" 
                                :class="isNewCustomer ? 'bg-white text-indigo-600 shadow-xs' : 'text-slate-500 hover:text-slate-700'" 
                                class="px-2.5 py-1 rounded-lg transition">+ Baru</button>
                    </div>
                </div>

                <!-- Existing Customer Autocomplete -->
                <div x-show="!isNewCustomer" class="relative">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Cari Pelanggan (Nama / No. HP)</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="customerSearchInput" x-model="customerSearch" @input="filterCustomers()" 
                               placeholder="Ketik minimal 2 huruf / angka..." 
                               class="w-full pl-8 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <button type="button" x-show="customerSearch.length > 0" @click="clearCustomer()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>

                    <!-- Dropdown Results -->
                    <div x-show="customerResults.length > 0" @click.away="customerResults = []" 
                         class="absolute z-30 left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden max-h-56 overflow-y-auto">
                        <template x-for="c in customerResults" :key="c.id">
                            <div @click="selectCustomer(c)" class="p-3 hover:bg-indigo-50 cursor-pointer border-b border-slate-100 last:border-b-0 transition flex items-center justify-between">
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-800" x-text="c.name"></h4>
                                    <p class="text-[11px] text-slate-500 flex items-center gap-1.5">
                                        <i class="fab fa-whatsapp text-emerald-600"></i> <span x-text="c.phone"></span>
                                    </p>
                                </div>
                                <span class="text-[10px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md">Pilih</span>
                            </div>
                        </template>
                    </div>

                    <!-- Selected Customer Preview Pill -->
                    <div x-show="selectedCustomerId" class="mt-3 p-3 rounded-2xl bg-indigo-50/70 border border-indigo-200/70 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider block">Pelanggan Terpilih:</span>
                            <h4 class="text-xs font-black text-slate-800" x-text="customerName"></h4>
                            <p class="text-[11px] text-slate-600" x-text="customerPhone"></p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                    Saldo: Rp <span x-text="parseInt(selectedDeposit || 0).toLocaleString('id-ID')"></span>
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                    <i class="fas fa-star text-[9px] mr-0.5"></i><span x-text="selectedPoints || 0"></span> Poin
                                </span>
                            </div>
                        </div>
                        <button type="button" @click="clearCustomer()" class="text-rose-500 hover:text-rose-700 text-xs font-bold px-2 py-1 bg-white rounded-lg shadow-2xs">
                            Ganti
                        </button>
                    </div>
                </div>

                <!-- New Customer Walk-in Form -->
                <div x-show="isNewCustomer" class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Nama Lengkap *</label>
                        <input type="text" id="newCustNameInput" x-model="customerName" placeholder="Contoh: Ibu Rina Melati" 
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Nomor WhatsApp *</label>
                        <input type="tel" x-model="customerPhone" placeholder="08xxxxxxxxxx" 
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Alamat (Opsional)</label>
                        <input type="text" x-model="customerAddress" placeholder="Jl. Anggrek No. 12" 
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Card 2: Keranjang Layanan -->
            <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-shopping-basket text-indigo-600"></i>
                        <h2 class="text-sm font-extrabold text-slate-800">Keranjang Cucian</h2>
                    </div>
                    <span class="text-xs font-bold bg-slate-100 text-slate-600 px-2.5 py-0.5 rounded-full" x-text="items.length + ' Item'"></span>
                </div>

                <!-- Empty State -->
                <div x-show="items.length === 0" class="text-center py-8 text-slate-400 space-y-2">
                    <i class="fas fa-cart-shopping text-3xl opacity-40"></i>
                    <p class="text-xs">Keranjang masih kosong.<br>Klik layanan di katalog kiri untuk menambahkan.</p>
                </div>

                <!-- Items List -->
                <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="p-3.5 rounded-2xl bg-slate-50/80 border border-slate-200/80 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <h4 class="text-xs font-black text-slate-800" x-text="item.name"></h4>
                                    <p class="text-[10px] text-slate-400 font-mono" x-text="'Rp ' + item.price.toLocaleString('id-ID') + '/' + item.unit"></p>
                                </div>
                                <button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-700 text-xs p-1" title="Hapus">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>

                            <!-- Stepper & Subtotal Row -->
                            <div class="flex items-center justify-between pt-1 border-t border-slate-200/60">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" @click="stepQty(index, -1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs">
                                        <i class="fas fa-minus text-[10px]"></i>
                                    </button>
                                    <input type="number" step="0.1" min="0.1" x-model.number="item.qty" 
                                           class="w-16 text-center py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    <button type="button" @click="stepQty(index, 1)" class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs">
                                        <i class="fas fa-plus text-[10px]"></i>
                                    </button>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase" x-text="item.unit"></span>
                                </div>

                                <div class="text-right">
                                    <span class="text-xs font-black font-mono text-indigo-700" x-text="'Rp ' + (item.qty * item.price).toLocaleString('id-ID')"></span>
                                </div>
                            </div>

                            <!-- Item Notes (expandable) -->
                            <div>
                                <input type="text" x-model="item.notes" placeholder="Catatan item (cth: noda minyak di kerah)..." 
                                       class="w-full px-2.5 py-1 text-[11px] bg-white border border-slate-200/80 rounded-lg placeholder-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Custom / Add More Dropdown (if wanted) -->
                <div class="pt-2 flex justify-between items-center text-xs border-t border-slate-100">
                    <span class="text-slate-400 font-medium">Estimasi Selesai:</span>
                    <span class="font-extrabold text-slate-700" x-text="getEstimateLabel()"></span>
                </div>
            </div>

            <!-- Card 3: Pewangi & Kecepatan Layanan -->
            <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fas fa-spray-can-sparkles text-indigo-600"></i> Preferensi Pewangi & Layanan
                </h2>

                <!-- Fragrance Selector -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Pilihan Aroma Parfum</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="p in perfumes" :key="p.name">
                            <button type="button" @click="selectedPerfume = p.name; window.playTone('pop')" 
                                    :class="selectedPerfume === p.name ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-200' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'" 
                                    class="p-2.5 rounded-xl text-left text-xs font-bold transition flex items-center gap-2">
                                <span class="text-base" x-text="p.emoji"></span>
                                <span class="truncate" x-text="p.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Speed Toggle -->
                <div class="pt-2 border-t border-slate-100">
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2">Kecepatan Pengerjaan</label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" @click="speed = 'regular'; window.playTone('pop')" 
                                :class="speed === 'regular' ? 'bg-white border-indigo-600 text-indigo-800 shadow-md ring-2 ring-indigo-100' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                class="p-3 rounded-2xl border text-left transition space-y-0.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs">
                                <i class="far fa-clock text-indigo-600"></i> Reguler
                            </div>
                            <p class="text-[10px] text-slate-400">Standar 24-48 Jam Sesuai Layanan</p>
                        </button>

                        <button type="button" @click="speed = 'express'; window.playTone('pop')" 
                                :class="speed === 'express' ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20 ring-2 ring-amber-300' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                class="p-3 rounded-2xl border text-left transition space-y-0.5">
                            <div class="flex items-center gap-1.5 font-bold text-xs" :class="speed === 'express' ? 'text-white' : 'text-amber-600'">
                                <i class="fas fa-bolt"></i> Express ⚡
                            </div>
                            <p class="text-[10px]" :class="speed === 'express' ? 'text-amber-100' : 'text-slate-400'">Prioritas +25% Biaya (2x Lebih Cepat)</p>
                        </button>
                    </div>
                </div>

                <!-- Rack Location & Condition Photo (Optional) -->
                <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Kode Rak / Keranjang (Opsional)</label>
                        <input type="text" x-model="rackLocation" placeholder="Contoh: Rak A-03" 
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Foto Kondisi Awal (Sepatu/Gorden)</label>
                        <div class="flex items-center gap-2">
                            <input type="file" accept="image/*" capture="environment" @change="handlePhotoUpload($event)" 
                                   class="block w-full text-[11px] text-slate-500 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-indigo-50 file:text-indigo-700 cursor-pointer">
                            <button type="button" x-show="itemPhoto" @click="itemPhoto = ''" class="text-rose-500 text-xs" title="Hapus Foto"><i class="fas fa-times-circle"></i></button>
                        </div>
                        <img x-show="itemPhoto" :src="itemPhoto" class="mt-2 w-16 h-16 rounded-xl object-cover border border-slate-200">
                    </div>
                </div>
            </div>

            <!-- Card 4: Ringkasan Biaya, Pembayaran & Checkout -->
            <div class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-5">
                <h2 class="text-sm font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-3">
                    <i class="fas fa-credit-card text-indigo-600"></i> Pembayaran & Selesai
                </h2>

                <!-- Financial Breakdown -->
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Subtotal Layanan:</span>
                        <span class="font-mono font-bold text-slate-800" x-text="'Rp ' + grossTotal.toLocaleString('id-ID')"></span>
                    </div>

                    <div x-show="speed === 'express'" class="flex justify-between text-amber-600 font-bold">
                        <span>Biaya Express (+25%):</span>
                        <span class="font-mono" x-text="'+ Rp ' + expressSurcharge.toLocaleString('id-ID')"></span>
                    </div>

                    <div class="flex justify-between items-center text-slate-500">
                        <span>Potongan / Diskon (Rp):</span>
                        <input type="number" min="0" step="1000" x-model.number="discountAmount" placeholder="0" 
                               class="w-28 px-2.5 py-1 text-right bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-rose-600 focus:bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Grand Total Banner -->
                <div class="p-5 rounded-2xl brand-gradient-hero text-white shadow-xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-white/80 uppercase tracking-wider">Total Pembayaran</p>
                        <h3 class="text-2xl sm:text-3xl font-black font-mono tracking-tight" x-text="'Rp ' + grandTotal.toLocaleString('id-ID')"></h3>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] badge-tagline px-2.5 py-0.5 rounded-full font-black uppercase" x-text="paymentStatus === 'paid' ? 'Lunas' : 'Belum Lunas'"></span>
                    </div>
                </div>

                <!-- Payment Status & Method -->
                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Status Pembayaran</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="paymentStatus = 'paid'; window.playTone('pop')" 
                                    :class="paymentStatus === 'paid' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'bg-slate-50 text-slate-700 border border-slate-200'" 
                                    class="py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5">
                                <i class="fas fa-check-circle"></i> Lunas (Dibayar)
                            </button>
                            <button type="button" @click="paymentStatus = 'unpaid'; window.playTone('pop')" 
                                    :class="paymentStatus === 'unpaid' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20' : 'bg-slate-50 text-slate-700 border border-slate-200'" 
                                    class="py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5">
                                <i class="fas fa-clock"></i> Belum Lunas
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1.5">Metode Pembayaran</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button type="button" @click="paymentMethod = 'Tunai'; window.playTone('pop')" 
                                    :class="paymentMethod === 'Tunai' ? 'bg-indigo-50 border-indigo-500 text-indigo-700 font-extrabold' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                    class="p-2 border rounded-xl text-xs text-center transition">
                                <i class="fas fa-money-bill-wave block text-base mb-1"></i> Tunai
                            </button>
                            <button type="button" @click="paymentMethod = 'Transfer QRIS'; window.playTone('pop')" 
                                    :class="paymentMethod === 'Transfer QRIS' ? 'bg-indigo-50 border-indigo-500 text-indigo-700 font-extrabold' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                    class="p-2 border rounded-xl text-xs text-center transition">
                                <i class="fas fa-qrcode block text-base mb-1"></i> QRIS
                            </button>
                            <button type="button" @click="paymentMethod = 'Transfer Bank'; window.playTone('pop')" 
                                    :class="paymentMethod === 'Transfer Bank' ? 'bg-indigo-50 border-indigo-500 text-indigo-700 font-extrabold' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                    class="p-2 border rounded-xl text-xs text-center transition">
                                <i class="fas fa-university block text-base mb-1"></i> Transfer
                            </button>
                            <button type="button" @click="paymentMethod = 'Saldo Deposit'; paymentStatus = 'paid'; window.playTone('pop')" 
                                    :class="paymentMethod === 'Saldo Deposit' ? 'bg-emerald-50 border-emerald-500 text-emerald-700 font-extrabold' : 'bg-slate-50 border-slate-200 text-slate-600'" 
                                    class="p-2 border rounded-xl text-xs text-center transition">
                                <i class="fas fa-wallet block text-base mb-1"></i> Deposit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cash Tender & Change Calculator (When Cash Selected) -->
                <div x-show="paymentMethod === 'Tunai' && paymentStatus === 'paid'" class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="text-[11px] font-bold text-slate-600 uppercase">Uang Diterima (Rp)</label>
                        <span class="text-[10px] text-slate-400">Tekan F8 untuk uang pas</span>
                    </div>

                    <input type="number" id="cashPaidInput" min="0" step="1000" x-model.number="cashPaid" placeholder="0" 
                           class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-black font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">

                    <!-- Quick Cash Shortcut Buttons -->
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="cashPaid = grandTotal; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-indigo-700 rounded-lg shadow-2xs">Uang Pas</button>
                        <button type="button" @click="cashPaid = 10000; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-2xs">10 rb</button>
                        <button type="button" @click="cashPaid = 20000; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-2xs">20 rb</button>
                        <button type="button" @click="cashPaid = 50000; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-2xs">50 rb</button>
                        <button type="button" @click="cashPaid = 100000; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-2xs">100 rb</button>
                        <button type="button" @click="cashPaid = 200000; window.playTone('beep')" class="px-2.5 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-2xs">200 rb</button>
                    </div>

                    <!-- Change Display -->
                    <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-xs">
                        <span class="font-bold text-slate-600">Uang Kembalian:</span>
                        <span class="font-mono font-black text-sm" 
                              :class="cashChange >= 0 ? 'text-emerald-600' : 'text-rose-600'" 
                              x-text="cashChange >= 0 ? ('Rp ' + cashChange.toLocaleString('id-ID')) : ('Kurang Rp ' + Math.abs(cashChange).toLocaleString('id-ID'))">
                        </span>
                    </div>
                </div>

                <!-- Notes for Order -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Catatan Keseluruhan Cucian</label>
                    <textarea x-model="orderNotes" rows="2" placeholder="Contoh: Baju putih jangan dicampur, jemput jam 5 sore..." 
                              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <!-- Submit Button -->
                <button type="button" @click="processOrder()" :disabled="isSubmitting || items.length === 0 || grandTotal <= 0" 
                        class="w-full py-4 rounded-2xl btn-brand disabled:opacity-50 font-extrabold text-sm shadow-xl transition-all duration-200 flex items-center justify-center gap-2 group cursor-pointer">
                    <template x-if="!isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check-circle group-hover:scale-110 transition"></i>
                            <span>Proses & Simpan Pesanan (Ctrl+Enter)</span>
                        </div>
                    </template>
                    <template x-if="isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-circle-notch fa-spin"></i>
                            <span>Menyimpan Pesanan...</span>
                        </div>
                    </template>
                </button>

            </div>

        </div>

    </div>

    <!-- Hidden Form for Traditional Fallback -->
    <form id="posForm" action="" method="POST" style="display:none;">
        <input type="hidden" name="submit_order" value="1">
        <input type="hidden" name="customer_name" :value="customerName">
        <input type="hidden" name="customer_phone" :value="customerPhone">
        <input type="hidden" name="customer_address" :value="customerAddress">
        <input type="hidden" name="pickup_id" :value="pickupId">
        <input type="hidden" name="payment_status" :value="paymentStatus">
        <input type="hidden" name="payment_method" :value="paymentMethod">
        <input type="hidden" name="notes" :value="orderNotes">
        <input type="hidden" name="perfume" :value="selectedPerfume">
        <input type="hidden" name="speed" :value="speed">
        <input type="hidden" name="discount_amount" :value="discountAmount">
        <input type="hidden" name="cash_paid" :value="cashPaid">
        <input type="hidden" name="items_json" :value="JSON.stringify(items)">
    </form>

    <!-- Thermal Receipt Modal Preview -->
    <div x-show="showReceiptModal" style="display: none;" 
         class="fixed inset-0 z-[100] overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="showReceiptModal = false" 
             class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
            
            <!-- Modal Header -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fas fa-receipt text-indigo-400"></i>
                    <h3 class="text-sm font-extrabold">Struk Transaksi Kasir</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="receiptPaper = (receiptPaper === '58mm' ? '80mm' : '58mm')" 
                            class="px-2 py-0.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[10px] font-mono">
                        <span x-text="receiptPaper"></span>
                    </button>
                    <button type="button" @click="showReceiptModal = false" class="text-slate-400 hover:text-white text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Receipt Content (Thermal Paper Style) -->
            <div class="p-6 bg-slate-100 flex justify-center">
                <div :class="receiptPaper === '58mm' ? 'max-w-[280px]' : 'max-w-[340px]'" 
                     class="w-full bg-white p-5 rounded shadow-sm text-slate-800 font-mono text-[11px] leading-relaxed border-t-4 border-indigo-600">
                    
                    <div class="text-center pb-3 border-b border-dashed border-slate-300 space-y-0.5">
                        <h4 class="font-black text-sm tracking-tight uppercase"><?= htmlspecialchars($brand_theme['nama_laundry']); ?></h4>
                        <?php if (!empty($brand_theme['tagline'])): ?>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-600"><?= htmlspecialchars($brand_theme['tagline']); ?></p>
                        <?php endif; ?>
                        <p class="text-[10px] text-slate-500"><?= htmlspecialchars($brand_theme['alamat']); ?></p>
                        <p class="text-[10px] text-slate-500">WA: <?= htmlspecialchars($brand_theme['whatsapp']); ?><?= !empty($brand_theme['instagram']) ? ' | IG: ' . htmlspecialchars($brand_theme['instagram']) : ''; ?></p>
                    </div>

                    <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1 text-[10px]">
                        <div class="flex justify-between">
                            <span>No. Nota:</span>
                            <span class="font-bold" x-text="completedOrder.order_code"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pelanggan:</span>
                            <span class="font-bold truncate max-w-[150px]" x-text="completedOrder.customer_name"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Waktu:</span>
                            <span x-text="completedOrder.created_at || 'Baru Saja'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Parfum:</span>
                            <span class="font-bold" x-text="completedOrder.perfume"></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kecepatan:</span>
                            <span class="font-bold uppercase" x-text="completedOrder.speed"></span>
                        </div>
                        <div x-show="completedOrder.rack_location" class="flex justify-between">
                            <span>Lokasi Rak:</span>
                            <span class="font-bold" x-text="completedOrder.rack_location"></span>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="py-2.5 border-b border-dashed border-slate-300 space-y-1.5 text-[10px]">
                        <template x-for="item in completedOrder.items" :key="item.service_id || item.id">
                            <div>
                                <div class="flex justify-between font-bold">
                                    <span class="truncate max-w-[140px]" x-text="item.service_name || item.name"></span>
                                    <span x-text="'Rp ' + (item.subtotal || (item.qty * item.price)).toLocaleString('id-ID')"></span>
                                </div>
                                <div class="flex justify-between text-slate-500 text-[9px]">
                                    <span x-text="item.qty + ' ' + (item.unit || 'kg') + ' x Rp ' + parseInt(item.price).toLocaleString('id-ID')"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Total & Payment -->
                    <div class="pt-2.5 space-y-1 text-[11px]">
                        <div class="flex justify-between font-black text-xs">
                            <span>TOTAL:</span>
                            <span x-text="'Rp ' + parseInt(completedOrder.total_amount || 0).toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between text-slate-600 text-[10px]">
                            <span>Status:</span>
                            <span class="font-bold uppercase" x-text="completedOrder.payment_status === 'paid' ? 'LUNAS' : 'BELUM LUNAS'"></span>
                        </div>
                        <div class="flex justify-between text-slate-600 text-[10px]">
                            <span>Metode:</span>
                            <span x-text="completedOrder.payment_method"></span>
                        </div>
                        <template x-if="completedOrder.payment_method === 'Tunai' && completedOrder.payment_status === 'paid'">
                            <div class="text-[10px] space-y-0.5 pt-1 border-t border-slate-200">
                                <div class="flex justify-between text-slate-600">
                                    <span>Tunai Diterima:</span>
                                    <span x-text="'Rp ' + parseInt(completedOrder.cash_paid || 0).toLocaleString('id-ID')"></span>
                                </div>
                                <div class="flex justify-between text-slate-600">
                                    <span>Kembalian:</span>
                                    <span x-text="'Rp ' + parseInt(completedOrder.cash_change || 0).toLocaleString('id-ID')"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="text-center pt-4 border-t border-dashed border-slate-300 space-y-1">
                        <p class="text-[9px] text-slate-400">Simpan nota ini untuk pengambilan cucian. Lacak status di:</p>
                        <p class="text-[9px] font-bold text-indigo-700 truncate"><?= base_url(); ?></p>
                        <p class="text-[9px] text-slate-400">*** Terima Kasih ***</p>
                    </div>

                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="p-4 bg-white border-t border-slate-100 flex flex-wrap gap-2 justify-end">
                <a :href="'cetak_nota.php?id=' + completedOrder.id" target="_blank" 
                   class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fas fa-print"></i> Cetak Thermal
                </a>

                <a :href="completedOrder.wa_direct_url || ('https://wa.me/' + (completedOrder.customer_phone ? (completedOrder.customer_phone.replace(/[^0-9]/g, '').startsWith('0') ? '62' + completedOrder.customer_phone.replace(/[^0-9]/g, '').substring(1) : completedOrder.customer_phone.replace(/[^0-9]/g, '')) : ''))" target="_blank" 
                   class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </a>

                <button type="button" @click="showReceiptModal = false; resetCart()" 
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                    Transaksi Baru
                </button>
            </div>

        </div>

    </div>

</div>

<script>
function posApp() {
    return {
        allServices: <?= json_encode($service_list); ?>,
        allCustomers: <?= json_encode($customer_list); ?>,
        
        // Filter & Search
        selectedCategory: 'all',
        searchService: '',
        
        // Customer
        isNewCustomer: <?= !empty($init_name) ? 'true' : 'false'; ?>,
        customerSearch: '',
        customerResults: [],
        selectedCustomerId: '',
        selectedDeposit: 0,
        selectedPoints: 0,
        customerName: <?= json_encode($init_name); ?>,
        customerPhone: <?= json_encode($init_phone); ?>,
        customerAddress: <?= json_encode($init_address); ?>,
        pickupId: <?= (int)$init_pickup_id; ?>,
        rackLocation: '',
        itemPhoto: '',
        
        // Cart & Preferences
        items: [],
        perfumes: [
            { name: 'Sakura Blossom', emoji: '🌸' },
            { name: 'Ocean Fresh', emoji: '🌊' },
            { name: 'Lavender Garden', emoji: '🌿' },
            { name: 'Akasia Premium', emoji: '🌲' },
            { name: 'Bebas Parfum', emoji: '🚫' }
        ],
        selectedPerfume: 'Sakura Blossom',
        speed: 'regular',
        orderNotes: <?= json_encode($init_notes); ?>,
        
        // Payment
        discountAmount: 0,
        paymentStatus: 'paid',
        paymentMethod: 'Tunai',
        cashPaid: 0,
        
        // State
        isSubmitting: false,
        showReceiptModal: <?= !empty($new_order_data) ? 'true' : 'false'; ?>,
        receiptPaper: '58mm',
        completedOrder: <?= !empty($new_order_data) ? json_encode($new_order_data) : '{}'; ?>,

        init() {
            // Seed default service if available
            if (this.allServices.length > 0) {
                const s0 = this.allServices[0];
                this.items.push({
                    id: s0.id,
                    name: s0.service_name,
                    price: parseFloat(s0.price),
                    unit: s0.unit || 'kg',
                    qty: 1,
                    duration_hours: parseInt(s0.duration_hours || 48),
                    notes: ''
                });
            }
            this.initShortcuts();
        },

        get availableCategories() {
            const set = new Set();
            this.allServices.forEach(s => {
                if (s.category) set.add(s.category);
                else if (s.service_type) set.add(s.service_type);
            });
            return Array.from(set);
        },

        countByCategory(cat) {
            return this.allServices.filter(s => (s.category === cat || s.service_type === cat)).length;
        },

        get displayedServices() {
            return this.allServices.filter(s => {
                const matchCat = (this.selectedCategory === 'all') || (s.category === this.selectedCategory) || (s.service_type === this.selectedCategory);
                const matchSearch = !this.searchService || s.service_name.toLowerCase().includes(this.searchService.toLowerCase());
                return matchCat && matchSearch;
            });
        },

        getItemQty(svcId) {
            const found = this.items.find(i => i.id == svcId);
            return found ? found.qty : 0;
        },

        addOrIncrement(svc) {
            window.playTone('pop');
            const found = this.items.find(i => i.id == svc.id);
            if (found) {
                found.qty = Math.round((found.qty + 1) * 10) / 10;
            } else {
                this.items.push({
                    id: svc.id,
                    name: svc.service_name,
                    price: parseFloat(svc.price),
                    unit: svc.unit || 'kg',
                    qty: 1,
                    duration_hours: parseInt(svc.duration_hours || 48),
                    notes: ''
                });
            }
        },

        stepQty(index, step) {
            window.playTone('beep');
            const item = this.items[index];
            const newQty = Math.round((item.qty + step) * 10) / 10;
            if (newQty <= 0) {
                this.removeItem(index);
            } else {
                item.qty = newQty;
            }
        },

        removeItem(index) {
            window.playTone('pop');
            this.items.splice(index, 1);
        },

        filterCustomers() {
            if (this.customerSearch.length < 2) {
                this.customerResults = [];
                return;
            }
            const q = this.customerSearch.toLowerCase();
            this.customerResults = this.allCustomers.filter(c => 
                (c.name && c.name.toLowerCase().includes(q)) || (c.phone && c.phone.includes(q))
            ).slice(0, 6);
        },

        selectCustomer(c) {
            window.playTone('pop');
            this.selectedCustomerId = c.id;
            this.selectedDeposit = parseFloat(c.deposit_balance || 0);
            this.selectedPoints = parseInt(c.loyalty_points || 0);
            this.customerName = c.name;
            this.customerPhone = c.phone;
            this.customerAddress = c.address || '';
            this.customerSearch = c.name + ' (' + c.phone + ')';
            this.customerResults = [];
        },

        clearCustomer() {
            this.selectedCustomerId = '';
            this.selectedDeposit = 0;
            this.selectedPoints = 0;
            this.customerName = '';
            this.customerPhone = '';
            this.customerAddress = '';
            this.customerSearch = '';
            this.customerResults = [];
        },

        handlePhotoUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const maxDim = 600;
                    let w = img.width, h = img.height;
                    if (w > h && w > maxDim) { h = Math.round(h * maxDim / w); w = maxDim; }
                    else if (h >= w && h > maxDim) { w = Math.round(w * maxDim / h); h = maxDim; }
                    canvas.width = w; canvas.height = h;
                    canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                    this.itemPhoto = canvas.toDataURL('image/jpeg', 0.78);
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        },

        get grossTotal() {
            return this.items.reduce((acc, item) => acc + (parseFloat(item.qty || 0) * parseFloat(item.price || 0)), 0);
        },

        get expressSurcharge() {
            if (this.speed !== 'express') return 0;
            return Math.round(this.grossTotal * 0.25);
        },

        get grandTotal() {
            const total = this.grossTotal + this.expressSurcharge - (parseFloat(this.discountAmount) || 0);
            return Math.max(0, total);
        },

        get cashChange() {
            if (this.paymentMethod !== 'Tunai' || this.paymentStatus !== 'paid') return 0;
            const paid = parseFloat(this.cashPaid) || 0;
            if (paid === 0) return 0;
            return paid - this.grandTotal;
        },

        getEstimateLabel() {
            if (this.items.length === 0) return '-';
            let maxHours = 24;
            this.items.forEach(i => {
                if (i.duration_hours > maxHours) maxHours = i.duration_hours;
            });
            if (this.speed === 'express') {
                maxHours = Math.max(12, Math.round(maxHours / 2));
            }
            const days = (maxHours / 24).toFixed(1);
            return maxHours + ' Jam (~' + days + ' Hari)';
        },

        resetCart() {
            window.playTone('pop');
            this.items = [];
            this.clearCustomer();
            this.discountAmount = 0;
            this.cashPaid = 0;
            this.orderNotes = '';
            this.rackLocation = '';
            this.itemPhoto = '';
            this.selectedPerfume = 'Sakura Blossom';
            this.speed = 'regular';
        },

        async processOrder() {
            if (this.items.length === 0 || this.grandTotal <= 0) {
                window.playTone('error');
                window.showToast('Keranjang masih kosong. Pilih minimal 1 layanan.', 'error');
                return;
            }

            if (!this.customerName.trim() || !this.customerPhone.trim()) {
                window.playTone('error');
                window.showToast('Nama pelanggan dan No. WhatsApp wajib diisi!', 'error');
                return;
            }

            this.isSubmitting = true;

            const payload = {
                customer_name: this.customerName,
                customer_phone: this.customerPhone,
                customer_address: this.customerAddress,
                pickup_id: this.pickupId,
                payment_status: this.paymentStatus,
                payment_method: this.paymentMethod,
                perfume: this.selectedPerfume,
                speed: this.speed,
                rack_location: this.rackLocation,
                item_photo: this.itemPhoto,
                discount_amount: this.discountAmount,
                cash_paid: this.cashPaid,
                notes: this.orderNotes,
                items: this.items
            };

            try {
                const response = await fetch('../api/pos_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    window.playTone('success');
                    window.showToast('Pesanan berhasil dibuat: ' + data.order.order_code, 'success');
                    
                    this.completedOrder = {
                        id: data.order.id,
                        order_code: data.order.order_code,
                        customer_name: data.order.customer_name,
                        customer_phone: data.order.customer_phone,
                        total_amount: data.order.total_amount,
                        cash_paid: data.order.cash_paid,
                        cash_change: data.order.cash_change,
                        payment_status: data.order.payment_status,
                        payment_method: data.order.payment_method,
                        perfume: this.selectedPerfume,
                        speed: this.speed,
                        rack_location: data.order.rack_location || this.rackLocation,
                        items: [...this.items],
                        created_at: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                        wa_direct_url: data.order.wa_direct_url || data.order.wa_url || ''
                    };

                    this.showReceiptModal = true;
                } else {
                    window.playTone('error');
                    window.showToast(data.message || 'Gagal menyimpan pesanan.', 'error');
                }
            } catch (err) {
                console.warn('API error, fallback to traditional POST:', err);
                const form = document.getElementById('posForm');
                if (form) form.submit();
            } finally {
                this.isSubmitting = false;
            }
        },

        initShortcuts() {
            window.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    const el = this.isNewCustomer ? document.getElementById('newCustNameInput') : document.getElementById('customerSearchInput');
                    if (el) el.focus();
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    this.paymentMethod = 'Tunai';
                    this.paymentStatus = 'paid';
                    this.cashPaid = this.grandTotal;
                    window.playTone('beep');
                } else if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    this.processOrder();
                }
            });
        }
    };
}
</script>

<?php require_once '../layout/footer.php'; ?>
