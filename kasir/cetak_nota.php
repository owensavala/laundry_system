<?php
// kasir/cetak_nota.php - Redirect to admin/cetak_nota.php
$id = $_GET['id'] ?? '';
header("Location: ../admin/cetak_nota.php?id=" . urlencode($id));
exit();
?>