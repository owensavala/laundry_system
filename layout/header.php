<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
$sys_settings = get_settings($conn);
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';

// Active page detection
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($sys_settings['nama_laundry']); ?> - Management System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        
        /* Custom Smooth Scrollbar for responsive tables */
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Hide scrollbar for clean horizontal tabs */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        @media print {
            .no-print, #sidebar, #top-bar, nav, button, form, .backdrop-overlay {
                display: none !important;
            }
            .print\:block { display: block !important; }
            .print\:p-0 { padding: 0 !important; }
            .print\:m-0 { margin: 0 !important; }
            .print\:w-full { width: 100% !important; }
            body, .bg-gray-50, .bg-slate-50 {
                background-color: #fff !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col md:flex-row" x-data="{ mobileMenu: false }">

    <!-- Mobile Backdrop Overlay -->
    <div x-show="mobileMenu" 
         x-cloak 
         @click="mobileMenu = false" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 md:hidden no-print">
    </div>

    <!-- Sidebar Navigation (Responsive Slide-over Drawer on Mobile, Static Sidebar on Desktop) -->
    <aside id="sidebar"
           :class="mobileMenu ? 'translate-x-0 shadow-2xl' : '-translate-x-full md:translate-x-0'"
           class="fixed inset-y-0 left-0 z-50 w-72 md:w-64 bg-slate-900 text-slate-200 flex flex-col shrink-0 min-h-screen md:static transition-transform duration-300 ease-in-out no-print">
        
        <!-- Sidebar Brand & Mobile Close Button -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-600/30">
                    <i class="fas fa-soap text-lg"></i>
                </div>
                <div>
                    <h1 class="font-bold text-white text-base leading-tight truncate w-36"><?= htmlspecialchars($sys_settings['nama_laundry']); ?></h1>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black tracking-wider uppercase mt-0.5 
                        <?= $user_role === 'superadmin' ? 'bg-amber-500/20 text-amber-400' : 'bg-indigo-500/20 text-indigo-400'; ?>">
                        <?= $user_role === 'superadmin' ? 'Superadmin / Owner' : 'Admin / Kasir'; ?>
                    </span>
                </div>
            </div>
            <!-- Close Button for Mobile Drawer -->
            <button @click="mobileMenu = false" class="md:hidden text-slate-400 hover:text-white p-2 rounded-xl hover:bg-slate-800 focus:outline-none transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- User Info Bar -->
        <div class="px-5 py-3 bg-slate-950/40 border-b border-slate-800/80 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center text-slate-300 font-bold text-xs uppercase border border-slate-700">
                <?= substr($user_name, 0, 1); ?>
            </div>
            <div class="overflow-hidden">
                <p class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user_name); ?></p>
                <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto text-sm font-medium">

            <?php if ($user_role === 'superadmin'): ?>
                <!-- Superadmin Menu -->
                <div class="text-[11px] font-bold uppercase text-slate-400 px-3 pt-2 pb-1 tracking-wider">Owner Dashboard</div>
                
                <a href="../superadmin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'index.php' && $current_dir == 'superadmin') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-chart-pie w-5 text-center text-indigo-400"></i>
                    <span>Dashboard Analytics</span>
                </a>
                
                <a href="../superadmin/transaksi.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'transaksi.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-receipt w-5 text-center text-indigo-400"></i>
                    <span>Semua Transaksi</span>
                </a>

                <a href="../superadmin/layanan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'layanan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-tags w-5 text-center text-indigo-400"></i>
                    <span>Master Layanan & Harga</span>
                </a>

                <a href="../superadmin/karyawan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'karyawan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-users-cog w-5 text-center text-indigo-400"></i>
                    <span>Manajemen Karyawan</span>
                </a>

                <a href="../superadmin/pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pelanggan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-address-book w-5 text-center text-indigo-400"></i>
                    <span>Data Pelanggan (CRM)</span>
                </a>

                <a href="../superadmin/laporan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'laporan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-file-invoice-dollar w-5 text-center text-indigo-400"></i>
                    <span>Laporan Keuangan</span>
                </a>

                <a href="../superadmin/pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pengaturan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-sliders w-5 text-center text-indigo-400"></i>
                    <span>Pengaturan & WhatsApp</span>
                </a>

                <div class="text-[11px] font-bold uppercase text-slate-400 px-3 pt-4 pb-1 tracking-wider">Akses Operasional</div>
                <a href="../admin/pos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <i class="fas fa-cash-register w-5 text-center text-amber-400"></i>
                    <span>Buka POS Kasir</span>
                </a>

            <?php else: ?>
                <!-- Admin / Kasir Menu -->
                <div class="text-[11px] font-bold uppercase text-slate-400 px-3 pt-2 pb-1 tracking-wider">Operasional Kasir</div>
                
                <a href="../admin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'index.php' && $current_dir == 'admin') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-desktop w-5 text-center text-indigo-400"></i>
                    <span>Dashboard Operasional</span>
                </a>

                <a href="../admin/pos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pos.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-cash-register w-5 text-center text-emerald-400"></i>
                    <span>Input Pesanan (POS)</span>
                </a>

                <a href="../admin/pesanan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pesanan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-tasks w-5 text-center text-indigo-400"></i>
                    <span>Status & Antrean Cucian</span>
                </a>

                <a href="../admin/pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pelanggan.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-address-book w-5 text-center text-indigo-400"></i>
                    <span>Data Pelanggan (CRM)</span>
                </a>

                <a href="../admin/pickup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pickup.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-motorcycle w-5 text-center text-amber-400"></i>
                    <span>Permintaan Pickup</span>
                </a>

                <a href="../admin/pengeluaran.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pengeluaran.php') ? 'bg-indigo-600 text-white font-semibold shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
                    <i class="fas fa-receipt w-5 text-center text-rose-400"></i>
                    <span>Biaya Operasional</span>
                </a>
            <?php endif; ?>

            <div class="pt-4 border-t border-slate-800 mt-4">
                <a href="../panduan.php?role=<?= $user_role === 'superadmin' ? 'owner' : 'kasir'; ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-indigo-300 hover:text-white hover:bg-slate-800 transition text-xs font-semibold">
                    <i class="fas fa-book-open w-5 text-center text-indigo-400"></i>
                    <span>Buku Panduan Sistem</span>
                </a>

                <a href="../index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition text-xs">
                    <i class="fas fa-globe w-5 text-center"></i>
                    <span>Lihat Halaman Publik</span>
                </a>

                <a href="../auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?')" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-rose-400 hover:bg-rose-500/10 hover:text-rose-300 transition mt-1 text-xs">
                    <i class="fas fa-arrow-right-from-bracket w-5 text-center"></i>
                    <span>Keluar (Logout)</span>
                </a>
            </div>

        </nav>

        <div class="p-4 border-t border-slate-800 text-center text-[11px] text-slate-500">
            &copy; <?= date('Y'); ?> <?= htmlspecialchars($sys_settings['nama_laundry']); ?>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <main class="flex-1 flex flex-col min-w-0 w-full overflow-y-auto">
        
        <!-- Unified Top Header Bar for Desktop & Mobile -->
        <header id="top-bar" class="bg-white border-b border-slate-200 px-4 sm:px-6 py-3.5 flex items-center justify-between no-print sticky top-0 z-30 shadow-sm">
            
            <div class="flex items-center gap-3">
                <!-- Hamburger Menu Trigger (Mobile only) -->
                <button @click="mobileMenu = true" 
                        class="md:hidden p-2 -ml-1.5 text-slate-600 hover:text-indigo-600 hover:bg-slate-100 rounded-xl focus:outline-none transition flex items-center justify-center">
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <div>
                    <h2 class="font-extrabold text-slate-800 text-base sm:text-xl capitalize leading-tight">
                        <?php
                        $title = str_replace(['.php', '_'], ['', ' '], $current_page);
                        if ($title === 'index') {
                            $title = ($user_role === 'superadmin') ? 'Dashboard Analytics' : 'Dashboard Operasional';
                        }
                        echo htmlspecialchars(ucwords($title));
                        ?>
                    </h2>
                    <p class="text-[11px] text-slate-400 hidden sm:block">Sistem Manajemen Terintegrasi <?= htmlspecialchars($sys_settings['nama_laundry']); ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($user_name); ?></p>
                    <p class="text-[11px] text-indigo-600 font-semibold capitalize"><?= $user_role === 'superadmin' ? 'Owner' : 'Kasir'; ?></p>
                </div>
                <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-xs uppercase border border-indigo-200 shadow-sm shrink-0">
                    <?= substr($user_name, 0, 1); ?>
                </div>
            </div>

        </header>

        <!-- Page Dynamic Body Container -->
        <div class="p-3 sm:p-6 lg:p-8 flex-1 max-w-full overflow-x-hidden">