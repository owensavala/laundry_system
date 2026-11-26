<?php
// api/pos_api.php - High Speed Atomic POS Order Processor API
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';

// Auth Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan masuk kembali.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$customer_name = trim($input['customer_name'] ?? '');
$customer_phone = trim($input['customer_phone'] ?? '');
$customer_address = trim($input['customer_address'] ?? '');
$pickup_id = !empty($input['pickup_id']) ? (int)$input['pickup_id'] : null;

$payment_status = in_array($input['payment_status'] ?? '', ['paid', 'unpaid']) ? $input['payment_status'] : 'unpaid';
$payment_method = trim($input['payment_method'] ?? 'Tunai');
$notes = trim($input['notes'] ?? '');
$perfume = trim($input['perfume'] ?? 'Sakura Blossom');
$speed = in_array($input['speed'] ?? '', ['regular', 'express']) ? $input['speed'] : 'regular';
$rack_location = trim($input['rack_location'] ?? '');
$item_photo = trim($input['item_photo'] ?? '');

$discount_amount = max(0, (float)($input['discount_amount'] ?? 0));
$cash_paid = max(0, (float)($input['cash_paid'] ?? 0));
$admin_id = (int)($_SESSION['user_id'] ?? 1);

$items = $input['items'] ?? [];
if (is_string($items)) {
    $items = json_decode($items, true) ?? [];
}

if (empty($customer_name) || empty($customer_phone)) {
    echo json_encode(['success' => false, 'message' => 'Nama pelanggan dan nomor HP/WhatsApp wajib diisi!']);
    exit;
}

if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'Mohon pilih minimal satu layanan laundry di keranjang!']);
    exit;
}

// Start Transaction
mysqli_begin_transaction($conn);

try {
    // 1. Sync Customer
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

    // 2. Generate Unique Sequential Order Code: LND-YYYYMMDD-XXX
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

    // 3. Calculate Totals & Durations
    $gross_total = 0;
    $max_duration = 48; // default 48h
    $processed_items = [];

    foreach ($items as $item) {
        $svc_id = (int)($item['service_id'] ?? $item['id'] ?? 0);
        $qty = max(0.1, (float)($item['qty'] ?? 1));
        $item_notes = trim($item['notes'] ?? '');

        $q_svc = mysqli_query($conn, "SELECT * FROM services WHERE id = $svc_id LIMIT 1");
        if ($q_svc && mysqli_num_rows($q_svc) > 0) {
            $svc = mysqli_fetch_assoc($q_svc);
            $price = (float)$svc['price'];
            $duration = (int)($svc['duration_hours'] ?? 48);
            if ($duration > $max_duration) $max_duration = $duration;

            $subtotal = $qty * $price;
            $gross_total += $subtotal;

            $processed_items[] = [
                'service_id' => $svc_id,
                'service_name' => $svc['service_name'],
                'unit' => $svc['unit'] ?? 'kg',
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $subtotal,
                'notes' => $item_notes
            ];
        }
    }

    // Apply speed modifier (+25% for express)
    if ($speed === 'express') {
        $gross_total = round($gross_total * 1.25);
        $max_duration = max(12, round($max_duration / 2));
    }

    $final_total = max(0, $gross_total - $discount_amount);
    $cash_change = max(0, $cash_paid - $final_total);

    $now = date('Y-m-d H:i:s');
    $estimate_completed_at = date('Y-m-d H:i:s', strtotime("+$max_duration hours"));

    // 4. Insert Order
    $stmt_ord = mysqli_prepare($conn, "INSERT INTO orders (order_code, customer_id, pickup_id, customer_name, customer_phone, admin_id, status, payment_status, payment_method, total_amount, discount_amount, cash_paid, cash_change, notes, perfume, speed, rack_location, item_photo, estimate_completed_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    mysqli_stmt_bind_param($stmt_ord, "siisssssddddsssssss", 
        $order_code, 
        $customer_id, 
        $pickup_id, 
        $customer_name, 
        $customer_phone, 
        $admin_id, 
        $payment_status, 
        $payment_method, 
        $final_total, 
        $discount_amount, 
        $cash_paid, 
        $cash_change, 
        $notes, 
        $perfume, 
        $speed, 
        $rack_location,
        $item_photo,
        $estimate_completed_at, 
        $now
    );
    mysqli_stmt_execute($stmt_ord);
    $order_id = mysqli_insert_id($conn);

    // 4b. Handle Deposit Balance deduction & Loyalty Points (+1 point per paid order)
    if ($customer_id > 0) {
        if ($payment_method === 'Saldo Deposit' && $payment_status === 'paid') {
            $q_bal = mysqli_query($conn, "SELECT deposit_balance FROM users WHERE id = $customer_id FOR UPDATE");
            $cur_bal = $q_bal ? (float)(mysqli_fetch_assoc($q_bal)['deposit_balance'] ?? 0) : 0;
            if ($cur_bal < $final_total) {
                throw new Exception("Saldo deposit pelanggan tidak mencukupi (Saldo: Rp " . number_format($cur_bal, 0, ',', '.') . ").");
            }
            mysqli_query($conn, "UPDATE users SET deposit_balance = deposit_balance - $final_total WHERE id = $customer_id");
        }
        if ($payment_status === 'paid') {
            mysqli_query($conn, "UPDATE users SET loyalty_points = loyalty_points + 1 WHERE id = $customer_id");
        }
    }

    // 5. Insert Order Details
    $stmt_det = mysqli_prepare($conn, "INSERT INTO order_details (order_id, service_id, qty, subtotal, notes) VALUES (?, ?, ?, ?, ?)");
    foreach ($processed_items as $pi) {
        mysqli_stmt_bind_param($stmt_det, "iidds", $order_id, $pi['service_id'], $pi['qty'], $pi['subtotal'], $pi['notes']);
        mysqli_stmt_execute($stmt_det);
    }

    // 6. Update pickup request if linked
    if ($pickup_id > 0) {
        mysqli_query($conn, "UPDATE pickup_requests SET status = 'picked_up', order_id = $order_id WHERE id = $pickup_id");
    }

    // Commit Transaction
    mysqli_commit($conn);

    // 7. Generate WhatsApp Notification URL
    $order_for_wa = [
        'id' => $order_id,
        'order_code' => $order_code,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'total_amount' => $final_total,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method
    ];
    $wa_url = send_wa_order($conn, $order_for_wa);

    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil disimpan!',
        'order' => [
            'id' => $order_id,
            'order_code' => $order_code,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'total_amount' => $final_total,
            'discount_amount' => $discount_amount,
            'cash_paid' => $cash_paid,
            'cash_change' => $cash_change,
            'payment_status' => $payment_status,
            'payment_method' => $payment_method,
            'perfume' => $perfume,
            'speed' => $speed,
            'estimate_completed_at' => $estimate_completed_at,
            'items' => $processed_items,
            'wa_url' => $wa_url
        ]
    ]);

} catch (Throwable $e) {
    if (isset($conn) && $conn) {
        mysqli_rollback($conn);
    }
    echo json_encode(['success' => false, 'message' => 'Gagal memproses transaksi: ' . $e->getMessage()]);
}
