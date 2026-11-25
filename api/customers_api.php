<?php
// api/customers_api.php - Customer Search & History API
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = trim($_GET['q'] ?? '');
$where = "WHERE role = 'customer'";
if (!empty($q)) {
    $q_safe = mysqli_real_escape_string($conn, $q);
    $where .= " AND (name LIKE '%$q_safe%' OR phone LIKE '%$q_safe%')";
}

$query = "SELECT u.id, u.name, u.phone, u.address, 
                 COUNT(o.id) as total_orders, 
                 COALESCE(SUM(o.total_amount), 0) as total_spent,
                 MAX(o.created_at) as last_order_at
          FROM users u
          LEFT JOIN orders o ON u.id = o.customer_id
          $where
          GROUP BY u.id
          ORDER BY total_orders DESC, u.name ASC
          LIMIT 20";

$res = mysqli_query($conn, $query);
$customers = [];
while ($r = mysqli_fetch_assoc($res)) {
    $customers[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'phone' => $r['phone'] ?? '',
        'address' => $r['address'] ?? '',
        'total_orders' => (int)$r['total_orders'],
        'total_spent' => (float)$r['total_spent'],
        'last_order_at' => $r['last_order_at']
    ];
}

echo json_encode([
    'success' => true,
    'customers' => $customers
]);
