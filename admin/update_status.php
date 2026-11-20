<?php
// admin/update_status.php - Status transition handler with automated WhatsApp notification
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

require_once '../includes/whatsapp.php';

$id = (int)($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';
$redirect = $_GET['redirect'] ?? 'pesanan';

$valid_statuses = ['pending', 'processing', 'ironing', 'ready', 'completed'];

if ($id > 0 && in_array($status, $valid_statuses)) {
    $now = date('Y-m-d H:i:s');
    
    // Milestone timestamp tracking
    $milestone_sql = "";
    if ($status === 'processing') {
        $milestone_sql = ", processing_at = COALESCE(processing_at, '$now')";
    } elseif ($status === 'ironing') {
        $milestone_sql = ", ironing_at = COALESCE(ironing_at, '$now'), processing_at = COALESCE(processing_at, '$now')";
    } elseif ($status === 'ready') {
        $milestone_sql = ", ready_at = COALESCE(ready_at, '$now'), ironing_at = COALESCE(ironing_at, '$now'), processing_at = COALESCE(processing_at, '$now')";
    } elseif ($status === 'completed') {
        $milestone_sql = ", completed_at = '$now', ready_at = COALESCE(ready_at, '$now')";
    }

    $q_up = "UPDATE orders SET status='$status' $milestone_sql WHERE id='$id'";

    if (mysqli_query($conn, $q_up)) {
        // Fetch order details for WhatsApp
        $q_ord = mysqli_query($conn, "SELECT * FROM orders WHERE id='$id'");
        $order = mysqli_fetch_assoc($q_ord);
        $set = get_settings($conn);

        // When order is marked 'ready' (Selesai), trigger WhatsApp notification!
        if ($status === 'ready' && !empty($order['customer_phone'])) {
            $msg = build_wa_message($set['wa_template_ready'], [
                'customer_name' => $order['customer_name'],
                'order_code' => $order['order_code'],
                'laundry_name' => $set['nama_laundry']
            ]);

            // Attempt automated API send if configured
            $wa_res = send_wa_notification($conn, $order['customer_phone'], $msg);
            
            // If in manual mode, we can redirect or let the user click the WA link
            if (!$wa_res['success'] && ($set['wa_gateway_provider'] ?? 'manual') === 'manual') {
                // If manual mode, let user know or open WA
                $wa_url = $wa_res['direct_url'];
                echo "<script>
                    if (confirm('Status berhasil diubah ke Selesai! Buka WhatsApp untuk memberi tahu pelanggan?')) {
                        window.open('$wa_url', '_blank');
                    }
                    window.location = '" . ($redirect === 'index' ? 'index.php' : 'pesanan.php') . "';
                </script>";
                exit();
            }
        }

        header("Location: " . ($redirect === 'index' ? 'index.php' : 'pesanan.php') . "?update=sukses");
        exit();
    } else {
        echo "Gagal mengupdate status: " . mysqli_error($conn);
    }
} else {
    header("Location: pesanan.php");
    exit();
}
?>
