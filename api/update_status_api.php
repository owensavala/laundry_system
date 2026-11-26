<?php
// api/update_status_api.php - AJAX Instant Order Status & Payment Update
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/whatsapp.php';

// Authentication Check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan masuk kembali.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$order_id = (int)($input['order_id'] ?? 0);
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
    exit;
}

// Fetch current order
$q_order = mysqli_query($conn, "SELECT * FROM orders WHERE id = $order_id LIMIT 1");
if (!$q_order || mysqli_num_rows($q_order) === 0) {
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan.']);
    exit;
}

$order = mysqli_fetch_assoc($q_order);
$set = get_settings($conn);
$now = date('Y-m-d H:i:s');

// 1. Status Update
$allowed_statuses = ['pending', 'processing', 'ironing', 'ready', 'completed'];
$new_status = $input['status'] ?? null;

// Support 'advance' action to move to next logical stage
if (($input['action'] ?? '') === 'advance' || $new_status === 'next') {
    $flow = ['pending' => 'processing', 'processing' => 'ironing', 'ironing' => 'ready', 'ready' => 'completed'];
    $new_status = $flow[$order['status']] ?? $order['status'];
}

$status_updated = false;
$wa_url = null;
$wa_sent = false;

if ($new_status && in_array($new_status, $allowed_statuses) && $new_status !== $order['status']) {
    $time_col = null;
    if ($new_status === 'processing') $time_col = 'processing_at';
    elseif ($new_status === 'ironing') $time_col = 'ironing_at';
    elseif ($new_status === 'ready') $time_col = 'ready_at';
    elseif ($new_status === 'completed') $time_col = 'completed_at';

    $update_sql = "UPDATE orders SET status = '$new_status'";
    if ($time_col) {
        $update_sql .= ", $time_col = '$now'";
    }
    $update_sql .= " WHERE id = $order_id";
    
    if (mysqli_query($conn, $update_sql)) {
        $status_updated = true;
        $order['status'] = $new_status;

        // Auto-send WhatsApp if status changed to 'ready'
        if ($new_status === 'ready' && !empty($order['customer_phone'])) {
            $wa_url = send_wa_ready($conn, $order);
            $wa_sent = true;
        }
    }
}

// 2. Payment Settlement Update
$payment_updated = false;
if (isset($input['payment_status']) && in_array($input['payment_status'], ['unpaid', 'paid'])) {
    $pay_st = $input['payment_status'];
    $pay_method = mysqli_real_escape_string($conn, $input['payment_method'] ?? $order['payment_method'] ?? 'Tunai');
    $cash_paid = isset($input['cash_paid']) ? (float)$input['cash_paid'] : (float)$order['cash_paid'];
    $cash_change = max(0, $cash_paid - (float)$order['total_amount']);

    if (mysqli_query($conn, "UPDATE orders SET payment_status = '$pay_st', payment_method = '$pay_method', cash_paid = $cash_paid, cash_change = $cash_change WHERE id = $order_id")) {
        $payment_updated = true;
        $order['payment_status'] = $pay_st;
        $order['payment_method'] = $pay_method;
        $order['cash_paid'] = $cash_paid;
        $order['cash_change'] = $cash_change;
    }
}

// 3. Rack Location Update
$rack_updated = false;
if (isset($input['rack_location'])) {
    $rack = mysqli_real_escape_string($conn, trim($input['rack_location']));
    if (mysqli_query($conn, "UPDATE orders SET rack_location = '$rack' WHERE id = $order_id")) {
        $rack_updated = true;
        $order['rack_location'] = $rack;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Status pesanan berhasil diperbarui!',
    'status_updated' => $status_updated,
    'payment_updated' => $payment_updated,
    'rack_updated' => $rack_updated,
    'order' => [
        'id' => (int)$order['id'],
        'order_code' => $order['order_code'],
        'status' => $order['status'],
        'rack_location' => $order['rack_location'] ?? '',
        'payment_status' => $order['payment_status'],
        'payment_method' => $order['payment_method'],
        'cash_paid' => (float)$order['cash_paid'],
        'cash_change' => (float)$order['cash_change'],
        'wa_url' => $wa_url,
        'wa_sent' => $wa_sent
    ]
]);
