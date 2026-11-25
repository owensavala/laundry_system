<?php
// api/services_api.php - Services & Catalog JSON API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$category = trim($_GET['category'] ?? 'all');
$where = "WHERE 1=1";
if ($category !== 'all' && !empty($category)) {
    $cat_safe = mysqli_real_escape_string($conn, $category);
    $where .= " AND (category = '$cat_safe' OR service_type = '$cat_safe')";
}

$query = "SELECT * FROM services $where ORDER BY category ASC, service_type ASC, price ASC";
$res = mysqli_query($conn, $query);

$services = [];
$categories = ['Semua'];
$q_cats = mysqli_query($conn, "SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category != ''");
while ($c = mysqli_fetch_assoc($q_cats)) {
    $categories[] = $c['category'];
}

while ($r = mysqli_fetch_assoc($res)) {
    $services[] = [
        'id' => (int)$r['id'],
        'service_name' => $r['service_name'],
        'service_type' => $r['service_type'],
        'category' => $r['category'] ?? ($r['service_type'] === 'kiloan' ? 'Kiloan' : 'Satuan'),
        'icon' => $r['icon'] ?? 'fa-shirt',
        'price' => (float)$r['price'],
        'price_formatted' => 'Rp ' . number_format($r['price'], 0, ',', '.'),
        'unit' => $r['unit'] ?? 'kg',
        'duration_hours' => (int)$r['duration_hours']
    ];
}

echo json_encode([
    'success' => true,
    'categories' => array_values(array_unique($categories)),
    'services' => $services
]);
