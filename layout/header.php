<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/theme.php';
$sys_settings = get_settings($conn);
$brand_theme = get_brand_theme($sys_settings);
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
        
        /* Modern Glassmorphic & Animation Utilities */
        .glass-panel {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -10px rgba(79, 70, 229, 0.15);
        }
        
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
            .no-print, #sidebar, #top-bar, nav, button, form, .backdrop-overlay, #toast-container {
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
    <?= render_theme_styles($brand_theme); ?>
    <script>
        // High Quality Zero-Dependency Web Audio Synthesizer
        window.soundEnabled = localStorage.getItem('lnd_sound') !== 'false';
        window.toggleSound = function() {
            window.soundEnabled = !window.soundEnabled;
            localStorage.setItem('lnd_sound', window.soundEnabled ? 'true' : 'false');
            window.showToast(window.soundEnabled ? 'Efek audio kasir diaktifkan' : 'Efek audio dimatikan', 'info');
            return window.soundEnabled;
        };
        window.playTone = function(type = 'beep') {
            if (!window.soundEnabled) return;
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;
                if (type === 'beep') {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(880, now);
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
                    osc.start(now); osc.stop(now + 0.12);
                } else if (type === 'pop') {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(580, now);
                    osc.frequency.exponentialRampToValueAtTime(920, now + 0.08);
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.08);
                    osc.start(now); osc.stop(now + 0.08);
                } else if (type === 'success') {
                    [523.25, 659.25, 783.99].forEach((freq, idx) => {
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.connect(g); g.connect(ctx.destination);
                        o.frequency.setValueAtTime(freq, now + idx * 0.06);
                        g.gain.setValueAtTime(0.09, now + idx * 0.06);
                        g.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.06 + 0.22);
                        o.start(now + idx * 0.06); o.stop(now + idx * 0.06 + 0.22);
                    });
                } else if (type === 'error') {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sawtooth';
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(240, now);
                    gain.gain.setValueAtTime(0.12, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                    osc.start(now); osc.stop(now + 0.25);
                }
            } catch(e) {}
        };

        // Global Dark Mode Toggle
        window.applyDarkMode = function(isDark) {
            if (isDark) document.body.classList.add('dark-mode');
            else document.body.classList.remove('dark-mode');
            const btn = document.getElementById('darkModeToggleBtn');
            if (btn) btn.innerHTML = isDark ? '<i class="fas fa-sun text-amber-400"></i>' : '<i class="fas fa-moon"></i>';
        };
        window.toggleDarkMode = function() {
            const next = !document.body.classList.contains('dark-mode');
            localStorage.setItem('laundry_dark_mode', next ? 'true' : 'false');
            window.applyDarkMode(next);
            if (window.playTone) window.playTone('pop');
            return next;
        };
        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('laundry_dark_mode') === 'true') window.applyDarkMode(true);
        });

        // Modern Floating Glassmorphic Toast System
        window.showToast = function(msg, type = 'info', title = '') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm pointer-events-none no-print';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto transform transition-all duration-300 ease-out translate-y-3 opacity-0 flex items-start gap-3 p-3.5 rounded-2xl shadow-2xl backdrop-blur-md text-xs font-semibold ${
                type === 'success' ? 'bg-slate-900/95 text-emerald-300 border border-emerald-500/40 shadow-emerald-500/10' :
                type === 'error' ? 'bg-slate-900/95 text-rose-300 border border-rose-500/40 shadow-rose-500/10' :
                'bg-slate-900/95 text-slate-100 border border-slate-700/60 shadow-slate-950/20'
            }`;
            const icon = type === 'success' ? 'fa-circle-check text-emerald-400' :
                         type === 'error' ? 'fa-triangle-exclamation text-rose-400' :
                         'fa-circle-info text-indigo-400';
            toast.innerHTML = `
                <i class="fas ${icon} text-base mt-0.5 shrink-0"></i>
                <div class="flex-1">
                    ${title ? `<div class="font-bold text-[13px] text-white mb-0.5">${title}</div>` : ''}
                    <div class="leading-relaxed">${msg}</div>
                </div>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            });
            if (type === 'success') window.playTone('success');
            else if (type === 'error') window.playTone('error');
            else window.playTone('beep');

            setTimeout(() => {
                toast.classList.add('translate-y-3', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        };
    </script>
</head>
<body class="themed-body text-slate-800 antialiased min-h-screen flex flex-col md:flex-row" x-data="{ mobileMenu: false }">

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
           class="fixed inset-y-0 left-0 z-50 w-72 md:w-64 brand-gradient-sidebar text-slate-200 flex flex-col shrink-0 min-h-screen md:static transition-transform duration-300 ease-in-out no-print">
        
        <!-- Sidebar Brand & Mobile Close Button -->
        <div class="p-4 border-b border-white/10 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
                <?= render_brand_logo_html($brand_theme, 'w-10 h-10 rounded-2xl', 'text-lg'); ?>
                <div class="min-w-0">
                    <h1 class="font-bold text-white text-sm leading-tight break-words"><?= htmlspecialchars($brand_theme['nama_laundry']); ?></h1>
                    <span class="inline-block px-2 py-0.5 rounded text-[9px] font-black tracking-wider uppercase mt-0.5 badge-tagline leading-tight">
                        <?= $user_role === 'superadmin' ? 'Owner' : 'Kasir'; ?> &bull; <?= htmlspecialchars($brand_theme['tagline']); ?>
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
                
                <a href="../superadmin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'index.php' && $current_dir == 'superadmin') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-chart-pie w-5 text-center text-pink-300"></i>
                    <span>Dashboard Analytics</span>
                </a>
                
                <a href="../superadmin/transaksi.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'transaksi.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-receipt w-5 text-center text-pink-300"></i>
                    <span>Semua Transaksi</span>
                </a>

                <a href="../superadmin/layanan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'layanan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-tags w-5 text-center text-pink-300"></i>
                    <span>Master Layanan & Harga</span>
                </a>

                <a href="../superadmin/karyawan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'karyawan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-users-cog w-5 text-center text-pink-300"></i>
                    <span>Manajemen Karyawan</span>
                </a>

                <a href="../superadmin/pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pelanggan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-address-book w-5 text-center text-pink-300"></i>
                    <span>Data Pelanggan (CRM)</span>
                </a>

                <a href="../superadmin/laporan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'laporan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-file-invoice-dollar w-5 text-center text-pink-300"></i>
                    <span>Laporan Keuangan</span>
                </a>

                <a href="../superadmin/pengaturan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pengaturan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-sliders w-5 text-center text-pink-300"></i>
                    <span>Pengaturan & WhatsApp</span>
                </a>

                <a href="../admin/tampilan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'tampilan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-palette w-5 text-center text-pink-300"></i>
                    <span>Tampilan, Warna & Logo</span>
                </a>

                <a href="../admin/shift.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'shift.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-cash-register w-5 text-center text-amber-300"></i>
                    <span>Rekap Shift Kasir</span>
                </a>

                <a href="../admin/stok.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'stok.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-boxes-stacked w-5 text-center text-emerald-300"></i>
                    <span>Stok Bahan Laundry</span>
                </a>

                <div class="text-[11px] font-bold uppercase text-slate-400 px-3 pt-4 pb-1 tracking-wider">Akses Operasional</div>
                <a href="../admin/pos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <i class="fas fa-cash-register w-5 text-center text-amber-400"></i>
                    <span>Buka POS Kasir</span>
                </a>

            <?php else: ?>
                <!-- Admin / Kasir Menu -->
                <div class="text-[11px] font-bold uppercase text-slate-400 px-3 pt-2 pb-1 tracking-wider">Operasional Kasir</div>
                
                <a href="../admin/index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'index.php' && $current_dir == 'admin') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-desktop w-5 text-center text-pink-300"></i>
                    <span>Dashboard Operasional</span>
                </a>

                <a href="../admin/pos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pos.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-cash-register w-5 text-center text-emerald-400"></i>
                    <span>Input Pesanan (POS)</span>
                </a>

                <a href="../admin/pesanan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pesanan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-tasks w-5 text-center text-pink-300"></i>
                    <span>Status & Antrean Cucian</span>
                </a>

                <a href="../admin/pelanggan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pelanggan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-address-book w-5 text-center text-pink-300"></i>
                    <span>Data Pelanggan (CRM)</span>
                </a>

                <a href="../admin/pickup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pickup.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-motorcycle w-5 text-center text-amber-400"></i>
                    <span>Permintaan Pickup</span>
                </a>

                <a href="../admin/pengeluaran.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'pengeluaran.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-receipt w-5 text-center text-rose-400"></i>
                    <span>Biaya Operasional</span>
                </a>

                <a href="../admin/tampilan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'tampilan.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-palette w-5 text-center text-pink-300"></i>
                    <span>Tampilan, Warna & Logo</span>
                </a>

                <a href="../admin/shift.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'shift.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-cash-register w-5 text-center text-amber-300"></i>
                    <span>Rekap Shift Kasir</span>
                </a>

                <a href="../admin/stok.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition <?= ($current_page == 'stok.php') ? 'btn-brand text-white font-semibold shadow-md' : 'text-slate-300 hover:bg-white/10 hover:text-white'; ?>">
                    <i class="fas fa-boxes-stacked w-5 text-center text-emerald-300"></i>
                    <span>Stok Bahan Laundry</span>
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
            
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Live Clock Display (Hidden on very small mobile) -->
                <div x-data="{ time: new Date().toLocaleTimeString('id-ID') }" 
                     x-init="setInterval(() => time = new Date().toLocaleTimeString('id-ID'), 1000)" 
                     class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono font-bold text-slate-600">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span x-text="time + ' WIB'"></span>
                </div>

                <!-- Sound FX Toggle Button -->
                <button type="button" 
                        onclick="const st = window.toggleSound(); this.querySelector('i').className = st ? 'fas fa-volume-high text-brand' : 'fas fa-volume-xmark text-slate-400';" 
                        title="Aktifkan/Matikan Suara Kasir"
                        class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 border border-slate-200 transition text-xs flex items-center justify-center">
                    <i class="fas fa-volume-high text-brand"></i>
                </button>

                <!-- Dark Mode Toggle Button -->
                <button type="button" id="darkModeToggleBtn"
                        onclick="window.toggleDarkMode()" 
                        title="Mode Malam / Siang"
                        class="p-2 rounded-xl text-slate-500 hover:bg-slate-100 border border-slate-200 transition text-xs flex items-center justify-center">
                    <i class="fas fa-moon"></i>
                </button>

                <!-- Quick Action POS shortcut button -->
                <a href="../admin/pos.php" 
                   title="Buka Kasir POS (F2)"
                   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs border transition badge-tagline hover:opacity-90">
                    <i class="fas fa-cash-register text-[11px]"></i>
                    <span>Kasir POS</span>
                </a>

                <div class="text-right hidden sm:block">
                    <p class="text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($user_name); ?></p>
                    <p class="text-[11px] text-brand font-semibold capitalize"><?= $user_role === 'superadmin' ? 'Owner / Pemilik' : 'Staf Kasir'; ?></p>
                </div>
                <div class="w-9 h-9 rounded-full text-white font-black flex items-center justify-center text-xs uppercase shadow-md shrink-0" style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));">
                    <?= substr($user_name, 0, 1); ?>
                </div>
            </div>

        </header>

        <!-- Page Dynamic Body Container -->
        <div class="p-3 sm:p-6 lg:p-8 flex-1 max-w-full overflow-x-hidden">