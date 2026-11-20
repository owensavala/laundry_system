<?php
// auth/login.php - Staff & Owner Login Portal (Pelanggan tidak perlu login)
session_start();
require_once '../config/database.php';

$set = get_settings($conn);

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'superadmin') {
        header("Location: ../superadmin/index.php");
        exit();
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/index.php");
        exit();
    }
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.';
}
if (isset($_GET['error']) && $_GET['error'] === 'forbidden') {
    $error = 'Anda tidak memiliki hak akses ke halaman tersebut.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staf & Owner - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-white to-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <a href="../index.php" class="inline-flex items-center justify-center w-16 h-16 rounded-3xl bg-indigo-600 text-white shadow-xl shadow-indigo-200 mb-4 hover:scale-105 transition transform">
                <i class="fas fa-soap text-2xl"></i>
            </a>
            <h1 class="text-2xl font-black text-gray-900"><?= htmlspecialchars($set['nama_laundry']); ?></h1>
            <p class="text-gray-500 text-sm mt-1">Portal Khusus Staf & Pemilik Usaha</p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
            <h2 class="text-xl font-black text-gray-800 mb-1">Masuk ke Sistem</h2>
            <p class="text-xs text-gray-400 mb-6">Gunakan akun Superadmin (Owner) atau Admin (Kasir)</p>

            <?php if (!empty($error)): ?>
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-2xl flex items-center gap-3">
                    <i class="fas fa-circle-exclamation text-red-500 shrink-0"></i>
                    <span><?= htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="proses_login.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Username / Email</label>
                    <div class="relative">
                        <i class="fas fa-user-shield absolute left-4 top-3.5 text-gray-400 text-sm"></i>
                        <input type="text" name="identifier" id="identifier" required
                               class="w-full pl-11 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                               placeholder="superadmin / admin">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-3.5 text-gray-400 text-sm"></i>
                        <input type="password" name="password" id="password" required
                               class="w-full pl-11 pr-11 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                               placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 px-1">
                            <i class="fas fa-eye text-sm" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3.5 rounded-xl shadow-lg shadow-indigo-100 hover:shadow-indigo-200 transition duration-200 flex items-center justify-center gap-2 mt-2">
                    <span>Masuk ke Sistem</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </form>

            <!-- Quick Demo Account Fillers for Staff Only -->
            <div class="mt-6 pt-6 border-t border-gray-100">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-2.5 text-center">Akses Cepat (Klik untuk Otomatis Isi)</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button type="button" onclick="fillDemo('superadmin', 'admin123')"
                            class="px-3 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl text-xs font-bold transition text-center flex items-center justify-center gap-2">
                        <i class="fas fa-crown text-amber-600"></i>
                        <div class="text-left">
                            <span class="block text-[11px] uppercase font-black">Owner</span>
                            <span class="text-[10px] text-amber-600 font-normal">superadmin / admin123</span>
                        </div>
                    </button>
                    <button type="button" onclick="fillDemo('admin', 'kasir123')"
                            class="px-3 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 rounded-xl text-xs font-bold transition text-center flex items-center justify-center gap-2">
                        <i class="fas fa-cash-register text-indigo-600"></i>
                        <div class="text-left">
                            <span class="block text-[11px] uppercase font-black">Kasir</span>
                            <span class="text-[10px] text-indigo-600 font-normal">admin / kasir123</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="mt-6 text-center text-xs text-gray-500">
                <a href="../index.php" class="text-indigo-600 hover:underline font-bold flex items-center justify-center gap-1.5">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Halaman Beranda & Lacak Cucian</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function fillDemo(user, pass) {
            document.getElementById('identifier').value = user;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>