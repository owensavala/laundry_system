<?php
session_start();
include '../config/database.php';

$ket = $_POST['keterangan'];
$jml = $_POST['jumlah'];
$id_user = $_SESSION['id_user'];

$q = "INSERT INTO pengeluaran (keterangan, jumlah_biaya, id_user) VALUES ('$ket', '$jml', '$id_user')";

if(mysqli_query($conn, $q)) {
    header("location:index.php");
} else {
    echo "Gagal: " . mysqli_error($conn);
}
?>