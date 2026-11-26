<?php
// api/tracking_api.php - Real-Time Live Order Tracking API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$kode = trim($_GET['kode'] ?? '');
if (empty($kode)) {
    echo json_encode(['success' => false, 'message' => 'Nomor nota / kode transaksi harus diisi.']);
    exit;
}

$kode_clean = mysqli_real_escape_string($conn, $kode);
$set = get_settings($conn);

$query = "SELECT o.*, u.name as cashier_name 
          FROM orders o 
          LEFT JOIN users u ON o.admin_id = u.id 
          WHERE o.order_code = '$kode_clean' 
          LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    // Check legacy table fallback
    $cek_leg = @mysqli_query($conn, "SELECT * FROM pemasukan WHERE kode_transaksi = '$kode_clean' LIMIT 1");
    if ($cek_leg && mysqli_num_rows($cek_leg) > 0) {
        $leg = mysqli_fetch_assoc($cek_leg);
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
            'total_amount' => (float)$leg['total_bayar'],
            'discount_amount' => 0,
            'cash_paid' => (float)$leg['total_bayar'],
            'cash_change' => 0,
            'notes' => '',
            'perfume' => 'Standar',
            'speed' => 'regular',
            'created_at' => $leg['tgl_transaksi'],
            'estimate_completed_at' => null,
            'processing_at' => null,
            'ironing_at' => null,
            'ready_at' => null,
            'completed_at' => null,
            'cashier_name' => 'Staf Laundry'
        ];

        $items = [[
            'service_name' => 'Cuci Kiloan',
            'qty' => (float)($leg['berat_kg'] ?? 1),
            'unit' => 'kg',
            'subtotal' => (float)$leg['total_bayar'],
            'notes' => ''
        ]];
    } else {
        echo json_encode(['success' => false, 'message' => "Nomor Nota #$kode tidak ditemukan dalam sistem. Mohon periksa kembali nomor nota Anda."]);
        exit;
    }
} else {
    $order = mysqli_fetch_assoc($result);
    $items = [];
    $q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit, s.icon, s.category 
        FROM order_details od 
        LEFT JOIN services s ON od.service_id = s.id 
        WHERE od.order_id = '{$order['id']}'");
    while ($it = mysqli_fetch_assoc($q_items)) {
        $items[] = [
            'service_name' => $it['service_name'] ?? 'Layanan Laundry',
            'qty' => (float)$it['qty'],
            'unit' => $it['unit'] ?? 'kg',
            'icon' => $it['icon'] ?? 'fa-shirt',
            'subtotal' => (float)$it['subtotal'],
            'notes' => $it['notes'] ?? ''
        ];
    }
}

// Stage details
$st = $order['status'];
$stages = [
    'pending' => ['step' => 1, 'title' => 'Menunggu Antrean', 'desc' => 'Pakaian telah diterima di kasir dan sedang menunggu giliran pencucian.', 'icon' => 'fa-clipboard-list'],
    'processing' => ['step' => 2, 'title' => 'Sedang Dicuci & Dikeringkan', 'desc' => 'Pakaian sedang dicuci bersih higienis dengan deterjen premium dan pewangi pilihan.', 'icon' => 'fa-soap'],
    'ironing' => ['step' => 3, 'title' => 'Sedang Disetrika & Dipacking', 'desc' => 'Pakaian sedang disetrika uap rapi, disemprot parfum, dan dikemas steril.', 'icon' => 'fa-bolt'],
    'ready' => ['step' => 4, 'title' => 'Selesai & Siap Diambil!', 'desc' => 'Pakaian sudah bersih, wangi, rapi, dan siap Anda ambil di outlet kami.', 'icon' => 'fa-box-open'],
    'completed' => ['step' => 5, 'title' => 'Cucian Sudah Diambil', 'desc' => 'Terima kasih telah mempercayakan pakaian Anda kepada ' . ($set['nama_laundry'] ?? 'Laundry Ar-Roniyah') . '!', 'icon' => 'fa-check-circle']
];

$cur_stage = $stages[$st] ?? $stages['pending'];

// SLA calculation
$sla_status = 'on_schedule';
$sla_text = 'Tepat Waktu';
if ($order['estimate_completed_at']) {
    $est_time = strtotime($order['estimate_completed_at']);
    $now = time();
    if ($st === 'completed') {
        $sla_status = 'completed';
        $sla_text = 'Selesai & Diambil';
    } elseif ($st === 'ready') {
        $sla_status = 'ready';
        $sla_text = 'Siap Diambil di Outlet';
    } elseif ($now > $est_time) {
        $sla_status = 'overdue';
        $sla_text = 'Melewati Estimasi Waktu';
    } else {
        $diff = $est_time - $now;
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        if ($hours > 24) {
            $days = floor($hours / 24);
            $hours_rem = $hours % 24;
            $sla_text = "Estimasi {$days} hari {$hours_rem} jam lagi";
        } elseif ($hours > 0) {
            $sla_text = "Estimasi {$hours} jam {$minutes} menit lagi";
        } else {
            $sla_text = "Estimasi {$minutes} menit lagi";
        }
    }
}

// Masked phone
$masked_phone = '';
if (!empty($order['customer_phone'])) {
    $len = strlen($order['customer_phone']);
    if ($len > 6) {
        $masked_phone = substr($order['customer_phone'], 0, 4) . '****' . substr($order['customer_phone'], -3);
    } else {
        $masked_phone = $order['customer_phone'];
    }
}

// WhatsApp URLs
$base = base_url();
$tracking_url = $base . (strpos($base, '?') !== false ? '&' : '?') . 'kode=' . urlencode($order['order_code']);

$share_msg = "Halo, ini status cucian laundry saya di " . $set['nama_laundry'] . " (Nota #" . $order['order_code'] . "):\n" .
             "Status saat ini: *" . $cur_stage['title'] . "*\n" .
             "Pantau langsung di: " . $tracking_url;
$wa_share_url = "https://wa.me/?text=" . rawurlencode($share_msg);

$admin_msg = "Halo Admin " . $set['nama_laundry'] . ", saya ingin bertanya mengenai pesanan dengan Kode Nota " . $order['order_code'];
$wa_admin_num = format_wa_phone($set['whatsapp'] ?? '081253996394');
$wa_admin_url = "https://wa.me/" . $wa_admin_num . "?text=" . rawurlencode($admin_msg);

echo json_encode([
    'success' => true,
    'order' => [
        'id' => (int)$order['id'],
        'order_code' => $order['order_code'],
        'customer_name' => $order['customer_name'],
        'customer_phone_masked' => $masked_phone,
        'status' => $order['status'],
        'status_title' => $cur_stage['title'],
        'status_desc' => $cur_stage['desc'],
        'status_icon' => $cur_stage['icon'],
        'step' => $cur_stage['step'],
        'payment_status' => $order['payment_status'],
        'payment_method' => $order['payment_method'] ?? 'Tunai',
        'total_amount' => (float)$order['total_amount'],
        'discount_amount' => (float)($order['discount_amount'] ?? 0),
        'cash_paid' => (float)($order['cash_paid'] ?? 0),
        'cash_change' => (float)($order['cash_change'] ?? 0),
        'perfume' => $order['perfume'] ?? 'Sakura Blossom',
        'speed' => $order['speed'] ?? 'regular',
        'rack_location' => $order['rack_location'] ?? '',
        'item_photo' => $order['item_photo'] ?? '',
        'notes' => $order['notes'] ?? '',
        'cashier_name' => $order['cashier_name'] ?? 'Staf Kasir',
        'created_at' => $order['created_at'],
        'created_at_fmt' => !empty($order['created_at']) ? date('d M Y, H:i', strtotime($order['created_at'])) : '-',
        'estimate_completed_at' => $order['estimate_completed_at'],
        'estimate_completed_at_fmt' => !empty($order['estimate_completed_at']) ? date('d M Y, H:i', strtotime($order['estimate_completed_at'])) : '-',
        'processing_at' => $order['processing_at'],
        'processing_at_fmt' => !empty($order['processing_at']) ? date('d M Y, H:i', strtotime($order['processing_at'])) : null,
        'ironing_at' => $order['ironing_at'],
        'ironing_at_fmt' => !empty($order['ironing_at']) ? date('d M Y, H:i', strtotime($order['ironing_at'])) : null,
        'ready_at' => $order['ready_at'],
        'ready_at_fmt' => !empty($order['ready_at']) ? date('d M Y, H:i', strtotime($order['ready_at'])) : null,
        'completed_at' => $order['completed_at'],
        'completed_at_fmt' => !empty($order['completed_at']) ? date('d M Y, H:i', strtotime($order['completed_at'])) : null,
        'sla_status' => $sla_status,
        'sla_text' => $sla_text,
        'items' => $items,
        'tracking_url' => $tracking_url,
        'wa_share_url' => $wa_share_url,
        'wa_admin_url' => $wa_admin_url
    ]
]);
