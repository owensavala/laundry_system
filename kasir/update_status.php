<?php
// kasir/update_status.php - Redirect to admin/update_status.php
$id = $_GET['id'] ?? '';
$status = $_GET['status'] ?? '';
header("Location: ../admin/update_status.php?id=" . urlencode($id) . "&status=" . urlencode($status));
exit();
?>