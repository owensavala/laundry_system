<?php
// auth/register.php
session_start();
require_once '../config/database.php';

$set = get_settings($conn);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($phone) || empty($password)) {
        $error = 'Nama, nomor WhatsApp/telepon, dan password wajib diisi!';
    } else {
        // Cek duplicate phone
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE phone = ? OR (email != '' AND email = ?)");
        mysqli_stmt_bind_param($stmt_check, "ss", $phone, $email);
        mysqli_stmt_execute($stmt_check);
        $res = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($res) > 0) {
            $error = 'Nomor HP atau Email sudah terdaftar dalam sistem.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . rand(10, 99);
            
            $stmt_ins = mysqli_prepare($conn, "INSERT INTO users (name, email, username, password, phone, role, address) VALUES (?, ?, ?, ?, ?, 'customer', ?)");
            mysqli_stmt_bind_param($stmt_ins, "ssssss", $name, $email, $username, $hash, $phone, $address);
            
            if (mysqli_stmt_execute($stmt_ins)) {
                $new_id = mysqli_insert_id($conn);
                // Also auto-link any existing orders that used this phone number
                mysqli_query($conn, "UPDATE orders SET customer_id='$new_id' WHERE customer_phone='$phone' AND (customer_id IS NULL OR customer_id=0)");

                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = 'Gagal mendaftar: ' . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-white to-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-lg my-8">
        <div class="text-center mb-6">
            <a href="../index.php" class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-200 mb-3">
                <i class="fas fa-soap text-xl"></i>
            </a>
            <h1 class="text-2xl font-black text-gray-900"><?= htmlspecialchars($set['nama_laundry']); ?></h1>
            <p class="text-gray-500 text-sm">Daftar Akun Pelanggan untuk Lacak & Riwayat Cucian</p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Pendaftaran Pelanggan</h2>
            <p class="text-xs text-gray-400 mb-6">Kelola pesanan cucian dan ajukan penjemputan dengan mudah</p>

            <?php if (!empty($error)): ?>
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-circle-exclamation text-red-500 shrink-0"></i>
                    <span><?= htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Nama Lengkap *</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Budi Santoso">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-2">No. WhatsApp / HP *</label>
                        <input type="tel" name="phone" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="08123456789">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Email (Opsional)</label>
                        <input type="email" name="email" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="budi@example.com">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Password *</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Minimal 6 karakter">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Alamat Penjemputan / Rumah</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Jl. Melati No. 45, RT 02/05, Kebayoran"></textarea>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-100 hover:shadow-indigo-200 transition duration-200 flex items-center justify-center gap-2 mt-4">
                    <i class="fas fa-user-plus text-sm"></i>
                    <span>Daftar Sekarang</span>
                </button>
            </form>

            <div class="mt-6 text-center text-xs text-gray-500">
                Sudah punya akun? <a href="login.php" class="font-bold text-indigo-600 hover:underline">Masuk ke Sini</a>
            </div>
        </div>
    </div>

</body>
</html>
