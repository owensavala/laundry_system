<?php
// index.php - Public Real-time Tracking & Services Showcase & Public Pickup Request
require_once 'config/database.php';
require_once 'includes/whatsapp.php';

$set = get_settings($conn);

// Search Logic
$order = null;
$order_items = [];
$error = null;

if (isset($_GET['kode']) && !empty(trim($_GET['kode']))) {
    $kode = trim(mysqli_real_escape_string($conn, $_GET['kode']));
    
    // Check in orders table
    $query = "SELECT o.*, u.name as cashier_name 
              FROM orders o 
              LEFT JOIN users u ON o.admin_id = u.id 
              WHERE o.order_code = '$kode' 
              LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $order = mysqli_fetch_assoc($result);
        
        // Fetch items
        $q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit 
            FROM order_details od 
            LEFT JOIN services s ON od.service_id = s.id 
            WHERE od.order_id = '{$order['id']}'");
        while ($it = mysqli_fetch_assoc($q_items)) {
            $order_items[] = $it;
        }
    } else {
        // Fallback check legacy pemasukan table if exists
        $cek_pem = mysqli_query($conn, "SHOW TABLES LIKE 'pemasukan'");
        if ($cek_pem && mysqli_num_rows($cek_pem) > 0) {
            $q_leg = mysqli_query($conn, "SELECT * FROM pemasukan WHERE kode_transaksi='$kode' LIMIT 1");
            if ($q_leg && mysqli_num_rows($q_leg) > 0) {
                $leg = mysqli_fetch_assoc($q_leg);
                $st_leg = 'pending';
                if ($leg['status_cucian'] == 'Proses') $st_leg = 'processing';
                elseif ($leg['status_cucian'] == 'Selesai') $st_leg = 'ready';
                elseif ($leg['status_cucian'] == 'Diambil') $st_leg = 'completed';

                $order = [
                    'id' => 0,
                    'order_code' => $leg['kode_transaksi'],
                    'customer_name' => $leg['nama_pelanggan'],
                    'customer_phone' => '',
                    'status' => $st_leg,
                    'payment_status' => (strtolower($leg['status_pembayaran']) == 'lunas') ? 'paid' : 'unpaid',
                    'payment_method' => 'Tunai',
                    'total_amount' => $leg['total_bayar'],
                    'created_at' => $leg['tgl_transaksi'],
                    'estimate_completed_at' => null,
                    'notes' => ''
                ];
                $order_items[] = [
                    'service_name' => 'Cuci Kiloan',
                    'qty' => $leg['berat_kg'] ?? 1,
                    'unit' => 'kg',
                    'subtotal' => $leg['total_bayar']
                ];
            } else {
                $error = "Kode Transaksi #$kode tidak ditemukan. Pastikan nomor nota yang Anda masukkan sudah benar.";
            }
        } else {
            $error = "Kode Transaksi #$kode tidak ditemukan. Pastikan nomor nota yang Anda masukkan sudah benar.";
        }
    }
}

// Handle Public Pickup Submission
$pickup_msg = '';
$pickup_err = '';
$wa_confirm_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_public_pickup'])) {
    $p_name = trim($_POST['customer_name'] ?? '');
    $p_phone = trim($_POST['customer_phone'] ?? '');
    $p_address = trim($_POST['pickup_address'] ?? '');
    $p_notes = trim($_POST['notes'] ?? '');

    if (!empty($p_name) && !empty($p_phone) && !empty($p_address)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO pickup_requests (customer_id, customer_name, customer_phone, pickup_address, notes, status, created_at) VALUES (0, ?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($stmt, "ssss", $p_name, $p_phone, $p_address, $p_notes);
        
        if (mysqli_stmt_execute($stmt)) {
            $pickup_msg = 'Permintaan jemput cucian berhasil dikirim! Staf kami akan segera menghubungi Anda.';
            
            $wa_text = "Halo Admin " . $set['nama_laundry'] . ", saya mau konfirmasi permintaan jemput cucian:\n" .
                       "Nama: " . $p_name . "\n" .
                       "No. WA: " . $p_phone . "\n" .
                       "Alamat: " . $p_address . "\n" .
                       ($p_notes ? "Catatan: " . $p_notes . "\n" : "");
            $wa_confirm_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $set['whatsapp']) . "?text=" . rawurlencode($wa_text);
        } else {
            $pickup_err = 'Gagal mengirim permintaan: ' . mysqli_error($conn);
        }
    } else {
        $pickup_err = 'Nama, nomor WhatsApp, dan alamat penjemputan wajib diisi!';
    }
}

// Fetch Services catalog
$services_catalog = mysqli_query($conn, "SELECT * FROM services ORDER BY service_type ASC, price ASC");
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lacak Cucian Real-Time - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Navbar -->
    <nav class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-slate-100 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center gap-2">
            
            <a href="index.php" class="flex items-center gap-2 sm:gap-2.5 font-black text-lg sm:text-xl text-indigo-600 truncate">
                <span class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs sm:text-sm shadow-md shadow-indigo-200 shrink-0">
                    <i class="fas fa-soap"></i>
                </span>
                <span class="truncate"><?= htmlspecialchars($set['nama_laundry']); ?></span>
            </a>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <a href="#layanan" class="hidden md:inline-block text-xs font-bold text-slate-600 hover:text-indigo-600 transition px-2 py-1">
                    Layanan & Harga
                </a>
                <a href="panduan.php" class="text-xs font-bold text-slate-600 hover:text-indigo-600 transition px-2.5 py-1.5 rounded-xl border border-slate-200 hover:border-indigo-300 flex items-center gap-1.5">
                    <i class="fas fa-book-open text-indigo-500"></i>
                    <span>Panduan</span>
                </a>
                <a href="#pickup" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-3 py-2 rounded-xl transition flex items-center gap-1">
                    <i class="fas fa-truck-pickup text-indigo-600"></i>
                    <span class="hidden sm:inline">Minta</span> Jemput
                </a>
                <a href="auth/login.php" class="text-xs font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 px-3.5 py-2 rounded-xl transition flex items-center gap-1.5 shadow-sm shadow-indigo-200">
                    <i class="fas fa-lock text-[10px]"></i>
                    <span>Staf</span>
                </a>
            </div>

        </div>
    </nav>

    <!-- Hero Section with Search -->
    <div id="lacak" class="relative bg-gradient-to-b from-indigo-900 via-indigo-800 to-indigo-950 text-white pt-10 sm:pt-16 pb-20 sm:pb-28 px-4 sm:px-6 overflow-hidden rounded-b-[2.5rem] sm:rounded-b-[3.5rem] shadow-2xl">
        <div class="max-w-3xl mx-auto text-center relative z-10">
            
            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-700/50 border border-indigo-500/40 text-indigo-200 text-[11px] sm:text-xs font-extrabold uppercase tracking-wider mb-4 sm:mb-6 backdrop-blur-md">
                <i class="fas fa-satellite-dish text-emerald-400 animate-pulse"></i> Pelacakan Status Cucian Real-time
            </span>

            <h1 class="text-2xl sm:text-4xl md:text-5xl font-black tracking-tight leading-tight mb-3 sm:mb-4">
                Lacak Status Cucian Anda Kapan Saja
            </h1>
            
            <p class="text-indigo-200 text-xs sm:text-base max-w-xl mx-auto mb-6 sm:mb-8 font-medium">
                Masukkan Kode Transaksi / Nomor Nota Anda untuk mengetahui apakah pakaian sedang dicuci, disetrika, atau sudah siap diambil.
            </p>

            <!-- Search Form (Highly Responsive on mobile) -->
            <form action="" method="GET" class="relative max-w-xl mx-auto">
                <div class="relative flex items-center">
                    <i class="fas fa-receipt absolute left-4 sm:left-5 text-slate-400 text-sm sm:text-lg"></i>
                    <input type="text" id="orderCodeInput" name="kode" value="<?= isset($_GET['kode']) ? htmlspecialchars($_GET['kode']) : '' ?>" 
                           class="w-full py-3.5 sm:py-4 pl-11 sm:pl-14 pr-24 sm:pr-36 rounded-2xl text-slate-900 font-extrabold shadow-2xl focus:outline-none focus:ring-4 focus:ring-indigo-400/50 transition placeholder-slate-400 uppercase text-xs sm:text-base" 
                           placeholder="CONTOH: LND-20261017-001" autocomplete="off" required>
                    <button type="submit" class="absolute right-1.5 sm:right-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 sm:px-5 py-2 sm:py-2.5 rounded-xl font-bold text-xs sm:text-sm flex items-center gap-1.5 shadow-lg shadow-indigo-600/40 transition">
                        <span>Lacak</span>
                        <i class="fas fa-arrow-right text-[10px] sm:text-xs"></i>
                    </button>
                </div>
            </form>

            <!-- Quick Action: QR Camera Scanner Button & Recent Searches -->
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <button type="button" onclick="openQrModal()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-bold backdrop-blur-md transition shadow-sm">
                    <i class="fas fa-qrcode text-indigo-300"></i>
                    <span>Scan QR Struk Kamera</span>
                </button>
            </div>

            <!-- Recent Orders from LocalStorage -->
            <div id="recentOrdersContainer" class="hidden mt-4 max-w-xl mx-auto">
                <p class="text-[11px] text-indigo-300 font-bold uppercase tracking-wider mb-1.5 flex items-center justify-center gap-1">
                    <i class="fas fa-history text-[10px]"></i> Pencarian Terakhir Anda:
                </p>
                <div id="recentOrdersList" class="flex flex-wrap items-center justify-center gap-1.5"></div>
            </div>

            <?php if($error): ?>
                <div class="mt-4 bg-rose-500/95 backdrop-blur-md text-white text-xs sm:text-sm py-2.5 px-4 sm:px-5 rounded-2xl inline-flex items-center gap-2 shadow-xl border border-rose-400/30 text-left">
                    <i class="fas fa-exclamation-circle text-sm shrink-0"></i>
                    <span><?= htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

        </div>

        <!-- Ambient Background Lights -->
        <div class="absolute -top-24 -left-24 w-80 sm:w-96 h-80 sm:h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-80 sm:w-96 h-80 sm:h-96 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>
    </div>

    <!-- Tracking Result Box (Floating Responsive Card) -->
    <?php if ($order): ?>
    <div class="max-w-3xl mx-auto px-3 sm:px-4 -mt-12 sm:-mt-16 mb-12 sm:mb-16 relative z-20">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden">
            
            <!-- Result Header -->
            <div class="bg-slate-900 text-white p-5 sm:p-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-4">
                <div>
                    <span class="text-[10px] sm:text-[11px] font-bold text-indigo-300 uppercase tracking-wider block">No. Nota Transaksi</span>
                    <h2 class="text-xl sm:text-2xl font-black tracking-tight font-mono text-white mt-0.5"><?= htmlspecialchars($order['order_code']); ?></h2>
                    <p class="text-[11px] sm:text-xs text-slate-400 mt-0.5">Didaftarkan: <?= date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-[10px] sm:text-[11px] font-bold text-indigo-300 uppercase tracking-wider block">Total Tagihan</span>
                    <h2 class="text-xl sm:text-2xl font-black font-mono text-emerald-400">Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?></h2>
                    <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $order['payment_status'] === 'paid' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'; ?>">
                        <?= $order['payment_status'] === 'paid' ? 'LUNAS (' . htmlspecialchars($order['payment_method']) . ')' : 'BELUM LUNAS'; ?>
                    </span>
                </div>
            </div>

            <!-- Stepper Progress Tracker -->
            <div class="p-4 sm:p-8 border-b border-slate-100 bg-slate-50/50">
                <?php 
                    $st = $order['status'];
                    $step = 1;
                    $status_text = 'Menunggu Antrean';
                    $status_desc = 'Pakaian Anda telah diterima oleh kasir dan sedang menunggu giliran cuci.';
                    
                    if ($st === 'processing') {
                        $step = 2;
                        $status_text = 'Sedang Dicuci & Dikeringkan';
                        $status_desc = 'Pakaian Anda sedang dicuci secara higienis menggunakan deterjen berkualitas.';
                    } elseif ($st === 'ironing') {
                        $step = 3;
                        $status_text = 'Sedang Disetrika & Dipacking';
                        $status_desc = 'Pakaian Anda sedang disetrika rapi dan dipacking steril.';
                    } elseif ($st === 'ready') {
                        $step = 4;
                        $status_text = 'Cucian Selesai & Siap Diambil!';
                        $status_desc = 'Cucian Anda sudah wangi dan rapi, siap diambil di outlet kami.';
                    } elseif ($st === 'completed') {
                        $step = 5;
                        $status_text = 'Cucian Sudah Diambil';
                        $status_desc = 'Terima kasih telah mempercayakan pakaian Anda kepada kami!';
                    }
                ?>

                <!-- Current Status Banner -->
                <div class="text-center mb-6 sm:mb-8">
                    <span class="inline-block px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 text-[10px] sm:text-xs font-black uppercase rounded-full mb-1.5">
                        Status Terkini
                    </span>
                    <h3 class="text-lg sm:text-2xl font-black text-slate-800 leading-tight"><?= $status_text; ?></h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto mt-1"><?= $status_desc; ?></p>
                </div>
                
                <?php 
                    $time_created = !empty($order['created_at']) ? date('d/m H:i', strtotime($order['created_at'])) : '-';
                    $time_processing = !empty($order['processing_at']) ? date('d/m H:i', strtotime($order['processing_at'])) : ($step > 2 ? 'Selesai' : ($step == 2 ? 'Sedang' : '-'));
                    $time_ironing = !empty($order['ironing_at']) ? date('d/m H:i', strtotime($order['ironing_at'])) : ($step > 3 ? 'Selesai' : ($step == 3 ? 'Sedang' : '-'));
                    $time_ready = !empty($order['ready_at']) ? date('d/m H:i', strtotime($order['ready_at'])) : ($step >= 4 ? 'Siap' : '-');
                    $time_completed = !empty($order['completed_at']) ? date('d/m H:i', strtotime($order['completed_at'])) : ($step == 5 ? 'Selesai' : '-');
                ?>

                <!-- Horizontal Progress Tracker (Mobile Optimized) -->
                <div class="relative flex justify-between items-start px-1 sm:px-4 mb-4">
                    <div class="absolute top-5 left-0 w-full h-1 bg-slate-200 -z-10 rounded-full"></div>
                    <div class="absolute top-5 left-0 h-1 bg-indigo-600 -z-10 rounded-full transition-all duration-1000" style="width: <?= ($step - 1) * 25; ?>%;"></div>

                    <!-- 1. Menunggu -->
                    <div class="flex flex-col items-center bg-white px-1 sm:px-2 text-center max-w-[65px] sm:max-w-[80px]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm <?= $step >= 1 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-1.5 uppercase <?= $step >= 1 ? 'text-indigo-600' : 'text-slate-400'; ?>">Menunggu</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400 font-mono"><?= $time_created; ?></span>
                    </div>

                    <!-- 2. Dicuci -->
                    <div class="flex flex-col items-center bg-white px-1 sm:px-2 text-center max-w-[65px] sm:max-w-[80px]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm <?= $step >= 2 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-soap"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-1.5 uppercase <?= $step >= 2 ? 'text-indigo-600' : 'text-slate-400'; ?>">Dicuci</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400 font-mono"><?= $time_processing; ?></span>
                    </div>

                    <!-- 3. Disetrika -->
                    <div class="flex flex-col items-center bg-white px-1 sm:px-2 text-center max-w-[65px] sm:max-w-[80px]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm <?= $step >= 3 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-temperature-high"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-1.5 uppercase <?= $step >= 3 ? 'text-indigo-600' : 'text-slate-400'; ?>">Disetrika</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400 font-mono"><?= $time_ironing; ?></span>
                    </div>

                    <!-- 4. Selesai -->
                    <div class="flex flex-col items-center bg-white px-1 sm:px-2 text-center max-w-[65px] sm:max-w-[80px]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm <?= $step >= 4 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-check"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-1.5 uppercase <?= $step >= 4 ? 'text-indigo-600' : 'text-slate-400'; ?>">Selesai</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400 font-mono"><?= $time_ready; ?></span>
                    </div>

                    <!-- 5. Diambil -->
                    <div class="flex flex-col items-center bg-white px-1 sm:px-2 text-center max-w-[65px] sm:max-w-[80px]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm <?= $step >= 5 ? 'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-200' : 'border-slate-300 text-slate-300'; ?>">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-1.5 uppercase <?= $step >= 5 ? 'text-indigo-600' : 'text-slate-400'; ?>">Diambil</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400 font-mono"><?= $time_completed; ?></span>
                    </div>
                </div>
            </div>

            <!-- Rincian Pesanan -->
            <div class="p-5 sm:p-8">
                <h4 class="font-extrabold text-slate-800 text-xs sm:text-sm uppercase tracking-wider mb-3">Rincian Cucian:</h4>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs mb-5">
                    <div class="bg-slate-50 p-3 rounded-2xl">
                        <span class="text-slate-400 block font-bold text-[10px] uppercase mb-0.5">Nama Pelanggan</span>
                        <span class="font-black text-slate-800 text-xs sm:text-sm"><?= htmlspecialchars($order['customer_name']); ?></span>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-2xl">
                        <span class="text-slate-400 block font-bold text-[10px] uppercase mb-0.5">Target Estimasi Selesai</span>
                        <span class="font-black text-indigo-600 text-xs sm:text-sm">
                            <?= $order['estimate_completed_at'] ? date('d/m/Y H:i', strtotime($order['estimate_completed_at'])) : '-'; ?>
                        </span>
                    </div>

                    <div class="bg-slate-50 p-3 rounded-2xl col-span-2 sm:col-span-1">
                        <span class="text-slate-400 block font-bold text-[10px] uppercase mb-0.5">Metode Bayar</span>
                        <span class="font-black text-slate-800 text-xs sm:text-sm"><?= htmlspecialchars($order['payment_method']); ?></span>
                    </div>
                </div>

                <!-- Items Table (Responsive scroll) -->
                <div class="border border-slate-100 rounded-2xl overflow-x-auto custom-scrollbar mb-5">
                    <table class="w-full text-xs text-left min-w-[280px]">
                        <thead class="bg-slate-50 text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="p-3">Layanan</th>
                                <th class="p-3 text-center">Jumlah</th>
                                <th class="p-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td class="p-3 font-semibold text-slate-800"><?= htmlspecialchars($item['service_name']); ?></td>
                                <td class="p-3 text-center font-bold text-slate-600"><?= $item['qty']; ?> <?= $item['unit']; ?></td>
                                <td class="p-3 text-right font-mono font-bold text-indigo-600">Rp <?= number_format($item['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Interactive Action Buttons -->
                <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $set['whatsapp']); ?>?text=Halo%20Admin%20<?= urlencode($set['nama_laundry']); ?>,%20saya%20ingin%20bertanya%20mengenai%20pesanan%20dengan%20Kode%20Nota%20<?= urlencode($order['order_code']); ?>" 
                       target="_blank" 
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md shadow-emerald-100 transition">
                        <i class="fab fa-whatsapp text-sm"></i>
                        <span>Tanya Kasir</span>
                    </a>

                    <?php 
                        $share_text = "Halo, ini status cucian laundry saya di " . $set['nama_laundry'] . " (Nota #" . $order['order_code'] . "): Status saat ini *" . $status_text . "*. Pantau langsung di: " . base_url("index.php?kode=" . $order['order_code']);
                        $share_url = "https://wa.me/?text=" . rawurlencode($share_text);
                    ?>
                    <a href="<?= $share_url; ?>" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl font-bold text-xs border border-indigo-200 transition">
                        <i class="fas fa-share-alt"></i>
                        <span>Bagikan ke WA</span>
                    </a>

                    <button type="button" onclick="copyTrackingLink()" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition">
                        <i class="fas fa-copy"></i>
                        <span id="copyBtnText">Salin Tautan</span>
                    </button>

                    <a href="index.php?kode=<?= urlencode($order['order_code']); ?>" class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition" title="Muat Ulang Status">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </a>
                </div>

            </div>

        </div>
    </div>
    <?php endif; ?>

    <!-- Layanan & Harga Showcase -->
    <div id="layanan" class="max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="text-center max-w-xl mx-auto mb-8 sm:mb-12">
            <span class="text-[11px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 rounded-full">Daftar Tarif</span>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 mt-2">Layanan & Harga Terbaik Kami</h2>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">Kami menyediakan layanan cuci kiloan reguler & kilat serta perawatan cucian satuan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <?php while($s = mysqli_fetch_assoc($services_catalog)): ?>
            <div class="bg-white p-5 sm:p-7 rounded-3xl shadow-sm border border-slate-100 hover:shadow-xl hover:border-indigo-100 transition duration-300 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg sm:text-xl font-bold">
                            <?php if ($s['service_type'] === 'kiloan'): ?>
                                <i class="fas fa-weight-hanging"></i>
                            <?php else: ?>
                                <i class="fas fa-tshirt"></i>
                            <?php endif; ?>
                        </span>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider <?= $s['service_type'] === 'kiloan' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'; ?>">
                            <?= $s['service_type']; ?>
                        </span>
                    </div>

                    <h3 class="font-extrabold text-slate-800 text-sm sm:text-base mb-1"><?= htmlspecialchars($s['service_name']); ?></h3>
                    <p class="text-xs text-slate-400 mb-4"><i class="far fa-clock mr-1 text-indigo-400"></i>Estimasi: <?= (int)$s['duration_hours']; ?> Jam</p>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-between items-end">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold block uppercase">Tarif</span>
                        <p class="text-xl sm:text-2xl font-black text-indigo-600 font-mono">
                            Rp <?= number_format($s['price'], 0, ',', '.'); ?> 
                            <span class="text-xs font-normal text-slate-400">/ <?= htmlspecialchars($s['unit']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- PUBLIC PICKUP REQUEST SECTION (Tanpa Perlu Login) -->
    <div id="pickup" class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-6 sm:p-10">
            
            <div class="text-center max-w-xl mx-auto mb-6 sm:mb-8">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-black uppercase tracking-wider mb-2">
                    <i class="fas fa-motorcycle text-emerald-600"></i> Layanan Antar Jemput
                </span>
                <h2 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight">Minta Jemput Cucian (Pickup)</h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-1">Pakaian kotor menumpuk? Cukup isi formulir singkat di bawah ini, kurir kami akan langsung menuju ke lokasi Anda!</p>
            </div>

            <?php if ($pickup_msg): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 sm:p-5 text-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center text-lg font-bold mx-auto mb-2">
                        <i class="fas fa-check"></i>
                    </div>
                    <h4 class="font-extrabold text-emerald-900 text-sm"><?= htmlspecialchars($pickup_msg); ?></h4>
                    <?php if ($wa_confirm_url): ?>
                        <div class="mt-3">
                            <a href="<?= $wa_confirm_url; ?>" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition">
                                <i class="fab fa-whatsapp text-sm"></i>
                                <span>Kirim Konfirmasi ke WhatsApp Toko</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($pickup_err): ?>
                <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm p-4 rounded-xl flex items-center gap-2">
                    <i class="fas fa-circle-exclamation text-rose-500 shrink-0"></i>
                    <span><?= htmlspecialchars($pickup_err); ?></span>
                </div>
            <?php endif; ?>

            <form action="#pickup" method="POST" class="space-y-4 max-w-2xl mx-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nama Lengkap Anda *</label>
                        <input type="text" name="customer_name" required placeholder="Contoh: Ibu Rina / Bpk Budi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Nomor WhatsApp / HP *</label>
                        <input type="tel" name="customer_phone" required placeholder="08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Alamat Lengkap Penjemputan *</label>
                    <textarea name="pickup_address" required rows="2" placeholder="Contoh: Jl. Anggrek No. 10, RT 02/05, Pagar hitam sebelah warung..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Tolong jemput sore sekitar jam 15.00" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="text-center pt-2">
                    <button type="submit" name="submit_public_pickup" class="w-full sm:w-auto px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold rounded-2xl text-sm shadow-xl shadow-indigo-100 transition inline-flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Kirim Permintaan Penjemputan</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-300 py-10 sm:py-12 border-t border-slate-800 mt-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center sm:text-left grid grid-cols-1 sm:grid-cols-3 gap-8">
            <div>
                <div class="flex items-center justify-center sm:justify-start gap-2.5 font-black text-xl text-white mb-3">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm">
                        <i class="fas fa-soap"></i>
                    </span>
                    <span><?= htmlspecialchars($set['nama_laundry']); ?></span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed max-w-xs mx-auto sm:mx-0">
                    Sistem manajemen operasional laundry terpadu. Pencatatan kasir modern, pelacakan status real-time, dan laporan keuangan komprehensif.
                </p>
            </div>

            <div>
                <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-3">Lokasi Outlet</h4>
                <p class="text-xs text-slate-400 mb-2"><i class="fas fa-map-marker-alt text-rose-500 mr-2"></i><?= htmlspecialchars($set['alamat']); ?></p>
                <p class="text-xs text-slate-400"><i class="fab fa-whatsapp text-emerald-400 mr-2"></i><?= htmlspecialchars($set['whatsapp']); ?></p>
            </div>

            <div class="text-center sm:text-right">
                <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-3">Akses Sistem</h4>
                <div class="space-y-2 text-xs">
                    <div><a href="panduan.php" class="text-indigo-400 hover:underline"><i class="fas fa-book-open mr-1"></i>Buku Panduan Penggunaan</a></div>
                    <div><a href="auth/login.php" class="text-slate-400 hover:underline"><i class="fas fa-lock mr-1"></i>Login Staf / Pemilik</a></div>
                    <div><a href="#pickup" class="text-slate-400 hover:underline"><i class="fas fa-truck-pickup mr-1"></i>Minta Jemput Cucian</a></div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 mt-8 pt-8 border-t border-slate-800 text-center text-xs text-slate-500">
            &copy; <?= date('Y'); ?> <?= htmlspecialchars($set['nama_laundry']); ?>. All rights reserved.
        </div>
    </footer>

    <!-- QR Camera Scanner Modal -->
    <div id="qrModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-sm w-full p-5 text-center text-slate-800 relative shadow-2xl">
            <button type="button" onclick="closeQrModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                <i class="fas fa-camera"></i>
            </div>
            <h3 class="font-black text-base text-slate-900">Scan QR Code Struk</h3>
            <p class="text-xs text-slate-500 mb-4">Arahkan kamera ke QR Code yang tercetak pada struk fisik Anda.</p>
            
            <div id="qrReader" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 min-h-[220px]"></div>
            
            <p id="qrScanStatus" class="text-[11px] text-slate-400 mt-3 flex items-center justify-center gap-1.5">
                <i class="fas fa-spinner fa-spin text-indigo-500"></i> Membuka kamera...
            </p>
        </div>
    </div>

    <!-- Tracking Enhancements Script -->
    <script>
        // LocalStorage Recent Searches Management
        const RECENT_KEY = 'lnd_recent_orders';

        function saveRecentOrder(code, name) {
            if (!code) return;
            try {
                let items = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
                items = items.filter(it => it.code.toUpperCase() !== code.toUpperCase());
                items.unshift({ code: code.toUpperCase(), name: name || '', time: new Date().toISOString() });
                if (items.length > 5) items = items.slice(0, 5);
                localStorage.setItem(RECENT_KEY, JSON.stringify(items));
            } catch(e) {}
        }

        function renderRecentOrders() {
            try {
                const listEl = document.getElementById('recentOrdersList');
                const contEl = document.getElementById('recentOrdersContainer');
                if (!listEl || !contEl) return;

                const items = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
                if (items.length === 0) {
                    contEl.classList.add('hidden');
                    return;
                }

                listEl.innerHTML = '';
                items.forEach(it => {
                    const chip = document.createElement('a');
                    chip.href = 'index.php?kode=' + encodeURIComponent(it.code);
                    chip.className = 'px-3 py-1 bg-white/10 hover:bg-white/20 border border-white/20 rounded-full text-[11px] font-mono text-indigo-100 font-bold backdrop-blur-md transition flex items-center gap-1';
                    chip.innerHTML = `<i class="fas fa-ticket-alt text-[9px] text-indigo-300"></i> ${it.code}`;
                    listEl.appendChild(chip);
                });
                contEl.classList.remove('hidden');
            } catch(e) {}
        }

        // Save current searched order if available
        <?php if ($order): ?>
            saveRecentOrder("<?= addslashes($order['order_code']); ?>", "<?= addslashes($order['customer_name']); ?>");
        <?php endif; ?>

        // Render on page load
        window.addEventListener('DOMContentLoaded', () => {
            renderRecentOrders();
        });

        // Copy Tracking Link to Clipboard
        function copyTrackingLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const btnText = document.getElementById('copyBtnText');
                if (btnText) {
                    btnText.innerText = 'Tersalin! ✓';
                    setTimeout(() => { btnText.innerText = 'Salin Tautan'; }, 2000);
                }
            }).catch(() => {
                alert('Tautan tracking berhasil disalin ke clipboard: ' + url);
            });
        }

        // QR Code Camera Scanner
        let html5QrScanner = null;

        function openQrModal() {
            const modal = document.getElementById('qrModal');
            const status = document.getElementById('qrScanStatus');
            modal.classList.remove('hidden');
            status.innerHTML = '<i class="fas fa-spinner fa-spin text-indigo-500"></i> Menyiapkan kamera...';

            if (!html5QrScanner) {
                html5QrScanner = new Html5Qrcode("qrReader");
            }

            const config = { fps: 10, qrbox: { width: 200, height: 200 } };
            html5QrScanner.start({ facingMode: "environment" }, config, (decodedText) => {
                // Parse code from URL or raw text
                let orderCode = decodedText.trim();
                try {
                    const parsedUrl = new URL(decodedText);
                    const k = parsedUrl.searchParams.get('kode');
                    if (k) orderCode = k;
                } catch(err) {
                    // Raw string
                }

                status.innerHTML = '<span class="text-emerald-600 font-bold"><i class="fas fa-check-circle"></i> Berhasil dibaca! Memuat...</span>';
                html5QrScanner.stop().then(() => {
                    window.location.href = 'index.php?kode=' + encodeURIComponent(orderCode);
                }).catch(() => {
                    window.location.href = 'index.php?kode=' + encodeURIComponent(orderCode);
                });
            }, (error) => {
                // Ignore scanning noise frames
            }).then(() => {
                status.innerHTML = '<i class="fas fa-camera text-indigo-600 mr-1"></i> Arahkan kamera ke QR Struk';
            }).catch(err => {
                status.innerHTML = '<span class="text-rose-500 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Kamera tidak dapat diakses atau diblokir browser.</span>';
            });
        }

        function closeQrModal() {
            const modal = document.getElementById('qrModal');
            modal.classList.add('hidden');
            if (html5QrScanner) {
                try {
                    html5QrScanner.stop().catch(() => {});
                } catch(e) {}
            }
        }
    </script>
</body>
</html>