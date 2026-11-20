<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Laundry - Clean&Fresh</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border-t-8 border-blue-600">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-2">Clean&Fresh</h1>
        <p class="text-center text-gray-500 mb-8 text-sm">Masukkan kode transaksi untuk cek status cucian Anda.</p>
        
        <form method="POST" class="space-y-4">
            <input type="text" name="kode" placeholder="Contoh: TRX-7641" 
                   class="w-full px-4 py-3 border-2 rounded-xl focus:border-blue-500 outline-none uppercase font-mono tracking-widest text-center" required>
            <button name="cek" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-lg">
                Cek Status Sekarang
            </button>
        </form>

        <?php
        if(isset($_POST['cek'])){
            include 'config/database.php';
            $kode = mysqli_real_escape_string($conn, $_POST['kode']);
            
            $query = mysqli_query($conn, "SELECT * FROM pemasukan WHERE kode_transaksi='$kode'");
            $data = mysqli_fetch_assoc($query);

            if($data){
                // Warna status
                $warna = "bg-yellow-500"; // Default Proses
                if($data['status_cucian'] == 'Selesai') $warna = "bg-green-500";
                if($data['status_cucian'] == 'Diambil') $warna = "bg-gray-500";
                ?>
                
                <div class="mt-8 p-4 border-2 border-dashed border-blue-200 rounded-xl bg-blue-50">
                    <p class="text-xs text-blue-400 uppercase font-bold tracking-wider">Detail Pelanggan</p>
                    <p class="text-lg font-bold text-gray-700"><?php echo $data['nama_pelanggan']; ?></p>
                    
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-sm text-gray-500">Status Saat Ini:</span>
                        <span class="<?php echo $warna; ?> text-white px-3 py-1 rounded-full text-xs font-bold uppercase">
                            <?php echo $data['status_cucian']; ?>
                        </span>
                    </div>
                </div>

                <?php
            } else {
                echo "<p class='mt-6 text-center text-red-500 font-bold'>Maaf, Kode tidak ditemukan!</p>";
            }
        }
        ?>

        <div class="mt-10 text-center">
            <a href="index.php" class="text-blue-600 hover:underline text-sm font-medium"> Kembali ke Beranda</a>
        </div>
    </div>

</body>
</html>