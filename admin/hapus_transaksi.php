<?php
session_start();
include '../config/database.php';

// Proteksi Admin
if($_SESSION['role'] != 'admin') {
    header("location:../auth/login.php");
    exit();
}

if(isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Hapus data berdasarkan ID
    $query = "DELETE FROM pemasukan WHERE id_pemasukan = '$id'";
    
    if(mysqli_query($conn, $query)) {
        // Redirect kembali ke index dengan pesan sukses
        echo "<script>alert('Data transaksi berhasil dihapus!'); window.location='index.php';</script>";
    } else {
        echo "Gagal menghapus: " . mysqli_error($conn);
    }
}
?>