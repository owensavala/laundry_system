<?php
session_start();
include '../config/database.php';

$nama = $_POST['nama'];
$berat = $_POST['berat'];
$total = $_POST['total'];
$id_user = $_SESSION['id_user']; 
$kode = "TRX-" . rand(1000, 9999); 

// PERBAIKAN DI SINI: Pastikan tertulis 'Antrian' (pakai tanda kutip), bukan variabel
$query = "INSERT INTO pemasukan (kode_transaksi, nama_pelanggan, berat_kg, total_bayar, id_user, status_pembayaran, status_cucian) 
          VALUES ('$kode', '$nama', '$berat', '$total', '$id_user', 'Lunas', 'Antrian')";

if (mysqli_query($conn, $query)) {
    $id_terakhir = mysqli_insert_id($conn);
    header("location:cetak_nota.php?id=$id_terakhir");
} else {
    echo "Gagal simpan: " . mysqli_error($conn);
}
?>