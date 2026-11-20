<?php
// admin/pos.php - Dynamic Point of Sale (POS) Order Entry
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../includes/whatsapp.php';

$set = get_settings($conn);
$msg = '';
$err = '';
$new_order_data = null;

// Fetch all services for dropdown
$q_services = mysqli_query($conn, "SELECT * FROM services ORDER BY service_type ASC, service_name ASC");
$service_list = [];
while ($s = mysqli_fetch_assoc($q_services)) {
    $service_list[] = $s;
}

// Prefill from Pickup Request if directed from admin/pickup.php
$init_pickup_id = (int)($_GET['pickup_id'] ?? 0);
$init_name = trim($_GET['name'] ?? '');
$init_phone = trim($_GET['phone'] ?? '');
$init_address = trim($_GET['address'] ?? '');
$init_notes = trim($_GET['notes'] ?? '');

// Fetch existing customers for autocomplete
$q_customers = mysqli_query($conn, "SELECT id, name, phone, address FROM users WHERE role='customer' ORDER BY name ASC");
$customer_list = [];
while ($c = mysqli_fetch_assoc($q_customers)) {
    $customer_list[] = $c;
}

// Handle Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $customer_type = $_POST['customer_type'] ?? 'existing';
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $pickup_id = !empty($_POST['pickup_id']) ? (int)$_POST['pickup_id'] : null;

    $payment_status = $_POST['payment_status'] ?? 'unpaid';
    $payment_method = $_POST['payment_method'] ?? 'Tunai';
    $notes = trim($_POST['notes'] ?? '');
    $discount_amount = max(0, (float)($_POST['discount_amount'] ?? 0));
    $cash_paid = max(0, (float)($_POST['cash_paid'] ?? 0));
    $cash_change = max(0, (float)($_POST['cash_change'] ?? 0));
    $admin_id = $_SESSION['user_id'];

    $services_data = json_decode($_POST['items_json'] ?? '[]', true);

    if (empty($customer_name) || empty($customer_phone)) {
        $err = 'Nama pelanggan dan nomor HP/WhatsApp wajib diisi!';
    } elseif (empty($services_data)) {
        $err = 'Mohon pilih minimal satu layanan laundry!';
    } else {
        // Auto-sync or find customer in users table
        if (!empty($customer_phone)) {
            $c_phone_safe = mysqli_real_escape_string($conn, $customer_phone);
            $c_name_safe = mysqli_real_escape_string($conn, $customer_name);
            $c_addr_safe = mysqli_real_escape_string($conn, $customer_address);
            
            $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE phone='$c_phone_safe' LIMIT 1");
            if ($cek_user && mysqli_num_rows($cek_user) > 0) {
                $customer_id = mysqli_fetch_assoc($cek_user)['id'];
                mysqli_query($conn, "UPDATE users SET name='$c_name_safe', address='$c_addr_safe' WHERE id='$customer_id'");
            } else {
                $c_email = 'cust_' . preg_replace('/[^0-9]/', '', $c_phone_safe) . '@customer.local';
                $dummy_pw = password_hash('customer123', PASSWORD_DEFAULT);
                mysqli_query($conn, "INSERT INTO users (name, email, password, phone, role, address) 
                    VALUES ('$c_name_safe', '$c_email', '$dummy_pw', '$c_phone_safe', 'customer', '$c_addr_safe')");
                $customer_id = mysqli_insert_id($conn);
            }
        }

        // Collision-Proof Sequential Order Code: LND-YYYYMMDD-XXX
        $today_prefix = "LND-" . date('Ymd') . "-";
        $q_seq = mysqli_query($conn, "SELECT order_code FROM orders WHERE order_code LIKE '$today_prefix%' ORDER BY id DESC LIMIT 1");
        $next_num = 1;
        if ($q_seq && mysqli_num_rows($q_seq) > 0) {
            $last_code = mysqli_fetch_assoc($q_seq)['order_code'];
            $parts = explode('-', $last_code);
            $last_seq = (int)end($parts);
            if ($last_seq >= 1) {
                $next_num = $last_seq + 1;
            }
        }
        $order_code = $today_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        while (true) {
            $check_unique = mysqli_query($conn, "SELECT id FROM orders WHERE order_code = '$order_code' LIMIT 1");
            if (!$check_unique || mysqli_num_rows($check_unique) == 0) {
                break;
            }
            $next_num++;
            $order_code = $today_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        }

        // Calculate gross total and max duration
        $gross_total = 0;
        $max_duration = 24;
        foreach ($services_data as $item) {
            $sub = (float)$item['qty'] * (float)$item['price'];
            $gross_total += $sub;
            if ((int)$item['duration_hours'] > $max_duration) {
                $max_duration = (int)$item['duration_hours'];
            }
        }

        // Apply discount
        $total_amount = max(0, $gross_total - $discount_amount);
        if ($payment_method === 'Tunai' && $payment_status === 'paid') {
            if ($cash_paid < $total_amount) {
                $cash_paid = $total_amount;
                $cash_change = 0;
            } else {
                $cash_change = $cash_paid - $total_amount;
            }
        }

        $created_at = date('Y-m-d H:i:s');
        $estimate_completed_at = date('Y-m-d H:i:s', strtotime("+$max_duration hours"));

        // Insert Order
        $stmt_ord = mysqli_prepare($conn, "INSERT INTO orders 
            (order_code, customer_id, pickup_id, customer_name, customer_phone, admin_id, status, payment_status, payment_method, total_amount, discount_amount, cash_paid, cash_change, notes, estimate_completed_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_ord, "siisssssddddsss", 
            $order_code, $customer_id, $pickup_id, $customer_name, $customer_phone, $admin_id, 
            $payment_status, $payment_method, $total_amount, $discount_amount, $cash_paid, $cash_change, $notes, $estimate_completed_at, $created_at);

        if (mysqli_stmt_execute($stmt_ord)) {
            $order_id = mysqli_insert_id($conn);

            // If linked to a pickup request, mark as picked_up
            if ($pickup_id && $pickup_id > 0) {
                mysqli_query($conn, "UPDATE pickup_requests SET status='picked_up', order_id='$order_id' WHERE id='$pickup_id'");
            }

            // Insert Order Details
            foreach ($services_data as $item) {
                $sid = (int)$item['id'];
                $qty = (float)$item['qty'];
                $sub = $qty * (float)$item['price'];
                $stmt_det = mysqli_prepare($conn, "INSERT INTO order_details (order_id, service_id, qty, subtotal) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_det, "iidd", $order_id, $sid, $qty, $sub);
                mysqli_stmt_execute($stmt_det);
            }

            // Prepare WhatsApp notification
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
                'wa_direct_url' => $wa_send_result['direct_url'],
                'wa_auto_sent' => $wa_send_result['success']
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
<div x-data="posApp()" class="max-w-7xl mx-auto">

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800">Point of Sale (POS) Kasir</h1>
            <p class="text-slate-400 text-sm">Pencatatan cucian baru, hitung berat/satuan otomatis, dan cetak nota.</p>
        </div>
        
        <!-- Keyboard Shortcuts Bar (Desktop Speed Booster) -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="hidden lg:flex items-center gap-2 text-[11px] text-slate-500 bg-white px-3 py-2 rounded-2xl border border-slate-200 shadow-xs">
                <span class="font-bold text-slate-700"><i class="fas fa-keyboard text-indigo-500 mr-1"></i>Shortcuts:</span>
                <span><kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono font-bold text-slate-700">F2</kbd> Cari Pelanggan</span>
                <span><kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono font-bold text-slate-700">F4</kbd> Status Bayar</span>
                <span><kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono font-bold text-slate-700">F8</kbd> Uang Tunai</span>
                <span><kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-[10px] font-mono font-bold text-slate-700">Ctrl+Enter</kbd> Simpan</span>
            </div>

            <a href="pesanan.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                <i class="fas fa-list"></i> Antrean Cucian
            </a>
        </div>
    </div>

    <?php if ($msg && $new_order_data): ?>
    <!-- Success Modal Notification -->
    <div class="mb-8 bg-emerald-50 border border-emerald-200 rounded-3xl p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-xl shrink-0 font-bold shadow-lg shadow-emerald-500/30">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Transaksi Berhasil Disimpan</span>
                    <h3 class="text-xl font-extrabold text-slate-900"><?= $new_order_data['order_code']; ?> - <?= htmlspecialchars($new_order_data['customer_name']); ?></h3>
                    <p class="text-xs text-slate-500 mt-0.5">Total Bayar: <span class="font-bold text-emerald-700">Rp <?= number_format($new_order_data['total_amount'], 0, ',', '.'); ?></span></p>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <a href="cetak_nota.php?id=<?= $new_order_data['id']; ?>" target="_blank" class="flex-1 sm:flex-initial px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md shadow-indigo-100">
                    <i class="fas fa-print"></i> Cetak Struk Thermal
                </a>

                <a href="<?= $new_order_data['wa_direct_url']; ?>" target="_blank" class="flex-1 sm:flex-initial px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md shadow-emerald-100">
                    <i class="fab fa-whatsapp text-sm"></i> Kirim WhatsApp
                </a>

                <a href="pos.php" class="px-4 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition flex items-center justify-center">
                    <i class="fas fa-plus mr-1"></i> Transaksi Baru
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-2">
            <i class="fas fa-circle-exclamation text-red-500"></i>
            <span><?= $err; ?></span>
        </div>
    <?php endif; ?>

    <form id="posForm" action="" method="POST" @submit="prepareSubmit($event)">
        <input type="hidden" name="items_json" :value="JSON.stringify(items)">
        <input type="hidden" name="customer_id" :value="selectedCustomerId">
        <input type="hidden" name="pickup_id" :value="pickupId">
        <input type="hidden" name="discount_amount" :value="discountAmount">
        <input type="hidden" name="cash_paid" :value="cashPaid">
        <input type="hidden" name="cash_change" :value="cashChange">

        <!-- Linked Pickup Request Notice -->
        <div x-show="pickupId" class="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 p-4 rounded-3xl flex items-center justify-between text-amber-900 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold">
                    <i class="fas fa-truck-pickup"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-sm">Input Pesanan dari Permintaan Antar Jemput (Pickup #<span x-text="pickupId"></span>)</h4>
                    <p class="text-xs text-amber-700">Data nama, nomor WhatsApp, alamat, dan catatan telah disinkronkan otomatis.</p>
                </div>
            </div>
            <span class="px-3 py-1 bg-amber-200 text-amber-900 rounded-xl text-xs font-black uppercase">Pickup Terhubung</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Col (Col 2): Customer & Services -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Customer Selection Card -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-user-circle text-indigo-500"></i> Data Pelanggan
                        </h3>
                        <div class="flex gap-1 text-xs">
                            <button type="button" @click="isNewCustomer = false" 
                                    :class="!isNewCustomer ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'" 
                                    class="px-3 py-1.5 rounded-lg font-bold transition">
                                Pelanggan Lama
                            </button>
                            <button type="button" @click="isNewCustomer = true; clearCustomer()" 
                                    :class="isNewCustomer ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'" 
                                    class="px-3 py-1.5 rounded-lg font-bold transition">
                                Pelanggan Baru (+)
                            </button>
                        </div>
                    </div>

                    <!-- Existing Customer Search -->
                    <div x-show="!isNewCustomer" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cari Pelanggan (Ketik Nama / No. HP)</label>
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                                <input type="text" id="customerSearchInput" x-model="customerSearch" @input="filterCustomers()" 
                                       placeholder="Ketik minimal 2 digit nomor HP atau nama... [Tekan F2]" 
                                       class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <!-- Dropdown Autocomplete -->
                            <div x-show="customerResults.length > 0 && customerSearch.length > 0" 
                                 class="mt-2 bg-white border border-slate-200 rounded-2xl shadow-xl max-h-48 overflow-y-auto divide-y divide-slate-100 z-10 relative">
                                <template x-for="c in customerResults" :key="c.id">
                                    <div @click="selectCustomer(c)" class="p-3 hover:bg-indigo-50 cursor-pointer flex justify-between items-center text-sm transition">
                                        <div>
                                            <p class="font-bold text-slate-800" x-text="c.name"></p>
                                            <p class="text-xs text-slate-400" x-text="c.phone"></p>
                                        </div>
                                        <span class="text-xs text-indigo-600 font-bold">Pilih &rarr;</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Inputs (Pre-filled or New) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nama Pelanggan *</label>
                            <input type="text" id="newCustNameInput" name="customer_name" x-model="customerName" required placeholder="Budi Santoso" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Nomor WhatsApp / HP *</label>
                            <input type="tel" name="customer_phone" x-model="customerPhone" required placeholder="08123456789" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Alamat Pelanggan</label>
                            <input type="text" name="customer_address" x-model="customerAddress" placeholder="Jl. Mawar No. 12" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Services & Items Table -->
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-tags text-indigo-500"></i> Rincian Layanan Cucian
                            </h3>
                            <p class="text-xs text-slate-400">Pilih layanan, timbang berat (kg) atau hitung satuan (pcs)</p>
                        </div>
                        
                        <!-- Service Category Filter Pills -->
                        <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl text-xs font-bold">
                            <button type="button" @click="serviceCategory = 'all'" :class="serviceCategory === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="px-2.5 py-1 rounded-lg transition">Semua</button>
                            <button type="button" @click="serviceCategory = 'kiloan'" :class="serviceCategory === 'kiloan' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="px-2.5 py-1 rounded-lg transition">Kiloan</button>
                            <button type="button" @click="serviceCategory = 'satuan'" :class="serviceCategory === 'satuan' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="px-2.5 py-1 rounded-lg transition">Satuan</button>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-4">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                                <div class="w-full flex-1">
                                    <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Pilih Layanan</label>
                                    <select @change="updateService(index, $event.target.value)" 
                                            class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <option value="">-- Pilih Layanan --</option>
                                        <template x-for="svc in filteredServices" :key="svc.id">
                                            <option :value="svc.id" :selected="svc.id == item.id" 
                                                    x-text="svc.service_name + ' (Rp ' + parseInt(svc.price).toLocaleString('id-ID') + '/' + svc.unit + ')'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="w-full sm:w-auto flex items-center justify-between sm:justify-start gap-3 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-200/60">
                                    <div class="w-24 sm:w-28 shrink-0">
                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">
                                            Qty (<span x-text="item.unit || 'kg'"></span>)
                                        </label>
                                        <input type="number" step="0.1" min="0.1" x-model.number="item.qty" 
                                               class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="1">
                                    </div>

                                    <div class="flex-1 sm:w-36 text-right sm:text-right">
                                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Subtotal</label>
                                        <p class="font-mono font-black text-indigo-600 text-sm mt-1 sm:mt-1.5" x-text="'Rp ' + (item.qty * item.price).toLocaleString('id-ID')"></p>
                                    </div>

                                    <div class="sm:pt-5 shrink-0">
                                        <button type="button" @click="removeItem(index)" 
                                                class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center text-xs transition" title="Hapus Item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 pt-3 flex justify-between items-center">
                        <button type="button" @click="addItem()" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i class="fas fa-plus"></i> Tambah Layanan
                        </button>
                        <div class="text-right">
                            <span class="text-xs text-slate-400 uppercase font-bold block">Estimasi Selesai:</span>
                            <span class="text-xs font-extrabold text-slate-700" x-text="getEstimateLabel()"></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Col (Col 1): Payment & Summary -->
            <div class="space-y-6">
                
                <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-100 lg:sticky lg:top-24 space-y-6">
                    <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2 border-b border-slate-100 pb-4">
                        <i class="fas fa-cash-register text-indigo-500"></i> Ringkasan & Pembayaran
                    </h3>

                    <!-- Gross & Discount Breakdown -->
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal Layanan:</span>
                            <span class="font-mono font-bold text-slate-800" x-text="'Rp ' + grossTotal.toLocaleString('id-ID')"></span>
                        </div>
                        <div class="flex justify-between items-center text-slate-500">
                            <span>Potongan / Diskon (Rp):</span>
                            <input type="number" min="0" step="1000" x-model.number="discountAmount" placeholder="0" class="w-28 px-2.5 py-1 text-right bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-rose-600 focus:bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Grand Total Banner -->
                    <div class="p-5 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200">
                        <p class="text-xs font-bold text-indigo-200 uppercase tracking-wider">Total Yang Harus Dibayar</p>
                        <h2 class="text-3xl font-black mt-1 font-mono" x-text="'Rp ' + grandTotal.toLocaleString('id-ID')"></h2>
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Status Pembayaran</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition text-xs font-bold"
                                   :class="paymentStatus === 'paid' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                <input type="radio" name="payment_status" value="paid" x-model="paymentStatus" class="text-emerald-600">
                                <span>Lunas (Dibayar)</span>
                            </label>
                            <label class="border rounded-xl p-3 flex items-center gap-2 cursor-pointer transition text-xs font-bold"
                                   :class="paymentStatus === 'unpaid' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                <input type="radio" name="payment_status" value="unpaid" x-model="paymentStatus" class="text-rose-600">
                                <span>Belum Lunas</span>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Metode Pembayaran</label>
                        <select name="payment_method" x-model="paymentMethod" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="Tunai">Tunai / Cash</option>
                            <option value="Transfer QRIS">QRIS</option>
                            <option value="Transfer BCA">Transfer Bank BCA</option>
                            <option value="Transfer Mandiri">Transfer Bank Mandiri / BNI / BRI</option>
                        </select>
                    </div>

                    <!-- Cash Tender & Change Calculator (When Cash Selected) -->
                    <div x-show="paymentMethod === 'Tunai' && paymentStatus === 'paid'" class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase">Kalkulator Uang Kembalian</label>
                        
                        <div>
                            <span class="text-[11px] text-slate-400 block mb-1">Uang Diterima dari Pelanggan (Rp):</span>
                            <input type="number" id="cashPaidInput" min="0" step="1000" x-model.number="cashPaid" placeholder="Contoh: 50000" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm font-black font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <!-- Quick Cash Shortcuts -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button type="button" @click="cashPaid = grandTotal" class="px-2 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-indigo-700 rounded-lg shadow-sm">Uang Pas</button>
                            <button type="button" @click="cashPaid = 20000" class="px-2 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-sm">20 rb</button>
                            <button type="button" @click="cashPaid = 50000" class="px-2 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-sm">50 rb</button>
                            <button type="button" @click="cashPaid = 100000" class="px-2 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-sm">100 rb</button>
                            <button type="button" @click="cashPaid = 200000" class="px-2 py-1 bg-white border border-slate-200 hover:bg-indigo-50 text-[10px] font-bold text-slate-700 rounded-lg shadow-sm">200 rb</button>
                        </div>

                        <!-- Kembalian Display -->
                        <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-xs">
                            <span class="font-bold text-slate-600">Uang Kembalian:</span>
                            <span class="font-mono font-black text-sm" 
                                  :class="cashChange >= 0 ? 'text-emerald-600' : 'text-rose-600'" 
                                  x-text="cashChange >= 0 ? ('Rp ' + cashChange.toLocaleString('id-ID')) : ('Kurang Rp ' + Math.abs(cashChange).toLocaleString('id-ID'))">
                            </span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Catatan Khusus Cucian</label>
                        <textarea name="notes" x-model="notes" rows="2" placeholder="Contoh: Pisahkan kemeja putih, jangan pakai pewangi kuat..." 
                                  class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="submit_order" :disabled="items.length === 0 || grandTotal <= 0" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-extrabold py-4 rounded-2xl shadow-xl shadow-indigo-100 transition flex items-center justify-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>Simpan & Buat Nota</span>
                    </button>

                </div>

            </div>

        </div>
    </form>

</div>

<script>
function posApp() {
    return {
        allServices: <?= json_encode($service_list); ?>,
        allCustomers: <?= json_encode($customer_list); ?>,
        isNewCustomer: <?= !empty($init_name) ? 'true' : 'false'; ?>,
        customerSearch: '',
        customerResults: [],
        selectedCustomerId: '',
        customerName: <?= json_encode($init_name); ?>,
        customerPhone: <?= json_encode($init_phone); ?>,
        customerAddress: <?= json_encode($init_address); ?>,
        pickupId: <?= (int)$init_pickup_id; ?>,
        notes: <?= json_encode($init_notes); ?>,
        serviceCategory: 'all',
        discountAmount: 0,
        paymentStatus: 'paid',
        paymentMethod: 'Tunai',
        cashPaid: 0,

        init() {
            this.initShortcuts();
        },

        initShortcuts() {
            window.addEventListener('keydown', (e) => {
                // F2: Focus Customer Search or Customer Name
                if (e.key === 'F2') {
                    e.preventDefault();
                    if (this.isNewCustomer) {
                        const el = document.getElementById('newCustNameInput');
                        if (el) el.focus();
                    } else {
                        const el = document.getElementById('customerSearchInput');
                        if (el) el.focus();
                    }
                }

                // F4: Toggle Payment Status
                if (e.key === 'F4') {
                    e.preventDefault();
                    this.paymentStatus = (this.paymentStatus === 'paid') ? 'unpaid' : 'paid';
                }

                // F8: Focus Cash Tender
                if (e.key === 'F8') {
                    e.preventDefault();
                    this.paymentMethod = 'Tunai';
                    this.paymentStatus = 'paid';
                    this.$nextTick(() => {
                        const el = document.getElementById('cashPaidInput');
                        if (el) {
                            el.focus();
                            el.select();
                        }
                    });
                }

                // Ctrl + Enter: Submit Order
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    if (this.items.length > 0 && this.grandTotal > 0) {
                        const form = document.getElementById('posForm');
                        if (form) form.requestSubmit();
                    } else {
                        alert('Silakan pilih minimal 1 layanan laundry terlebih dahulu.');
                    }
                }
            });
        },
        items: [
            { id: <?= $service_list[0]['id'] ?? 1; ?>, qty: 1, price: <?= (float)($service_list[0]['price'] ?? 7000); ?>, unit: '<?= $service_list[0]['unit'] ?? 'kg'; ?>', duration_hours: <?= (int)($service_list[0]['duration_hours'] ?? 48); ?> }
        ],

        get filteredServices() {
            if (this.serviceCategory === 'all') return this.allServices;
            return this.allServices.filter(s => s.service_type === this.serviceCategory);
        },

        filterCustomers() {
            if (this.customerSearch.length < 2) {
                this.customerResults = [];
                return;
            }
            const q = this.customerSearch.toLowerCase();
            this.customerResults = this.allCustomers.filter(c => 
                c.name.toLowerCase().includes(q) || c.phone.includes(q)
            ).slice(0, 5);
        },

        selectCustomer(c) {
            this.selectedCustomerId = c.id;
            this.customerName = c.name;
            this.customerPhone = c.phone;
            this.customerAddress = c.address || '';
            this.customerResults = [];
            this.customerSearch = c.name + ' (' + c.phone + ')';
        },

        clearCustomer() {
            this.selectedCustomerId = '';
            this.customerName = '';
            this.customerPhone = '';
            this.customerAddress = '';
            this.customerSearch = '';
            this.customerResults = [];
        },

        addItem() {
            const list = this.filteredServices.length > 0 ? this.filteredServices : this.allServices;
            if (list.length > 0) {
                const s = list[0];
                this.items.push({
                    id: s.id,
                    qty: 1,
                    price: parseFloat(s.price),
                    unit: s.unit,
                    duration_hours: parseInt(s.duration_hours)
                });
            }
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        updateService(index, serviceId) {
            const svc = this.allServices.find(s => s.id == serviceId);
            if (svc) {
                this.items[index].id = svc.id;
                this.items[index].price = parseFloat(svc.price);
                this.items[index].unit = svc.unit;
                this.items[index].duration_hours = parseInt(svc.duration_hours);
            }
        },

        get grossTotal() {
            return this.items.reduce((acc, item) => acc + (parseFloat(item.qty || 0) * parseFloat(item.price || 0)), 0);
        },

        get grandTotal() {
            const total = this.grossTotal - (parseFloat(this.discountAmount) || 0);
            return Math.max(0, total);
        },

        get cashChange() {
            if (this.paymentMethod !== 'Tunai' || this.paymentStatus !== 'paid') return 0;
            const paid = parseFloat(this.cashPaid) || 0;
            if (paid === 0) return 0;
            return paid - this.grandTotal;
        },

        getEstimateLabel() {
            let maxHours = 24;
            this.items.forEach(i => {
                if (i.duration_hours > maxHours) maxHours = i.duration_hours;
            });
            const days = (maxHours / 24).toFixed(1);
            return maxHours + ' Jam (~' + days + ' Hari)';
        },

        prepareSubmit(event) {
            if (this.items.length === 0 || this.grandTotal <= 0) {
                alert('Pilih minimal satu layanan dengan kuantitas yang valid.');
                event.preventDefault();
            }
        }
    };
}

// Play pleasant chime on order creation
<?php if ($msg && $new_order_data): ?>
window.addEventListener('DOMContentLoaded', () => {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(523.25, now); // C5
        osc.frequency.setValueAtTime(659.25, now + 0.1); // E5
        osc.frequency.setValueAtTime(783.99, now + 0.2); // G5
        osc.frequency.setValueAtTime(1046.50, now + 0.3); // C6
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + 0.6);
    } catch(e) {}
});
<?php endif; ?>
</script>

<?php require_once '../layout/footer.php'; ?>
