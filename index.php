<?php
// index.php - State-of-the-Art Public Real-time Tracking, Modern Services Catalog & Smart Pickup
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/includes/theme.php';

$set = get_settings($conn);
$brand_theme = get_brand_theme($set);

// Search Query (Initial SSR Fallback)
$init_code = trim($_GET['kode'] ?? '');

// Handle Public Pickup Submission
$pickup_msg = '';
$pickup_err = '';
$wa_confirm_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_public_pickup'])) {
    $p_name = trim($_POST['customer_name'] ?? '');
    $p_phone = trim($_POST['customer_phone'] ?? '');
    $p_address = trim($_POST['pickup_address'] ?? '');
    $p_slot = trim($_POST['pickup_slot'] ?? 'Pagi (08:00 - 11:00)');
    $p_notes = trim($_POST['notes'] ?? '');

    if (!empty($p_name) && !empty($p_phone) && !empty($p_address)) {
        $full_notes = "Waktu Jemput: $p_slot" . ($p_notes ? " | Catatan: $p_notes" : "");
        $stmt = mysqli_prepare($conn, "INSERT INTO pickup_requests (customer_id, customer_name, customer_phone, pickup_address, notes, status, created_at) VALUES (0, ?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($stmt, "ssss", $p_name, $p_phone, $p_address, $full_notes);
        
        if (mysqli_stmt_execute($stmt)) {
            $pickup_msg = 'Permintaan jemput cucian berhasil dikirim! Kurir kami akan segera menghubungi Anda.';
            
            $wa_text = "Halo Admin " . $set['nama_laundry'] . ", saya mau konfirmasi permintaan jemput pakaian kotor:\n" .
                       "Nama: " . $p_name . "\n" .
                       "No. WhatsApp: " . $p_phone . "\n" .
                       "Waktu Jemput: " . $p_slot . "\n" .
                       "Alamat: " . $p_address . "\n" .
                       ($p_notes ? "Catatan Tambahan: " . $p_notes . "\n" : "") .
                       "Mohon segera diproses ya. Terima kasih!";
            $wa_confirm_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $set['whatsapp']) . "?text=" . rawurlencode($wa_text);
        } else {
            $pickup_err = 'Gagal mengirim permintaan: ' . mysqli_error($conn);
        }
    } else {
        $pickup_err = 'Nama lengkap, nomor WhatsApp, dan alamat penjemputan wajib diisi!';
    }
}

// Fetch Services catalog
$q_services = mysqli_query($conn, "SELECT * FROM services ORDER BY category ASC, service_type ASC, price ASC");
$services_catalog = [];
$service_categories = ['Semua'];
while ($s = mysqli_fetch_assoc($q_services)) {
    $services_catalog[] = $s;
    if (!empty($s['category']) && !in_array($s['category'], $service_categories)) {
        $service_categories[] = $s['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= htmlspecialchars($brand_theme['primary']); ?>">
    <link rel="manifest" href="manifest.php">
    <title>Lacak Cucian Real-Time & Layanan - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QR Code Scanner Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 9999px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Laser scanning animation for QR */
        @keyframes scanLaser {
            0% { top: 10%; opacity: 0.8; }
            50% { top: 90%; opacity: 1; }
            100% { top: 10%; opacity: 0.8; }
        }
        .laser-beam {
            position: absolute;
            left: 5%;
            right: 5%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #6366f1, #38bdf8, transparent);
            box-shadow: 0 0 15px #38bdf8;
            animation: scanLaser 2s ease-in-out infinite;
        }
    </style>
    <?= render_theme_styles($brand_theme); ?>
    <script>
        // Native Web Audio Synthesizer
        window.playTone = function(type = 'beep') {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const now = ctx.currentTime;
                if (type === 'beep') {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.setValueAtTime(880, now);
                    gain.gain.setValueAtTime(0.08, now);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.1);
                    osc.start(now); osc.stop(now + 0.1);
                } else if (type === 'success') {
                    [523.25, 659.25, 783.99].forEach((freq, idx) => {
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.connect(g); g.connect(ctx.destination);
                        o.frequency.setValueAtTime(freq, now + idx * 0.06);
                        g.gain.setValueAtTime(0.08, now + idx * 0.06);
                        g.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.06 + 0.2);
                        o.start(now + idx * 0.06); o.stop(now + idx * 0.06 + 0.2);
                    });
                }
            } catch(e) {}
        };
    </script>
</head>
<body class="themed-body text-slate-800 antialiased selection:bg-pink-500 selection:text-white" 
      x-data="trackingApp('<?= addslashes($init_code); ?>')">

    <!-- Top Navbar -->
    <nav class="bg-white/90 backdrop-blur-md sticky top-0 z-40 border-b border-slate-100 shadow-sm transition">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center gap-2">
            
            <a href="index.php" class="flex items-center gap-2.5 font-black text-lg sm:text-xl text-brand truncate">
                <?= render_brand_logo_html($brand_theme, 'w-9 h-9 rounded-2xl', 'text-sm'); ?>
                <div class="truncate">
                    <span class="truncate tracking-tight block leading-none"><?= htmlspecialchars($brand_theme['nama_laundry']); ?></span>
                    <span class="text-[10px] font-bold text-slate-400 block mt-0.5"><?= htmlspecialchars($brand_theme['tagline']); ?></span>
                </div>
            </a>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <a href="#layanan" class="hidden md:inline-block text-xs font-bold text-slate-600 hover:text-brand transition px-2.5 py-1.5">
                    Layanan & Tarif
                </a>
                <a href="panduan.php" class="text-xs font-bold text-slate-600 hover:text-brand transition px-3 py-1.5 rounded-xl border border-slate-200 hover:border-pink-300 flex items-center gap-1.5">
                    <i class="fas fa-book-open text-brand"></i>
                    <span>Panduan</span>
                </a>
                <a href="#pickup" class="text-xs font-bold px-3.5 py-2 rounded-xl transition flex items-center gap-1.5 badge-tagline shadow-xs hover:opacity-90">
                    <i class="fas fa-truck-pickup"></i>
                    <span class="hidden sm:inline">Minta</span> Jemput
                </a>
                <a href="auth/login.php" class="text-xs font-extrabold px-4 py-2 rounded-xl transition flex items-center gap-1.5 shadow-md btn-brand">
                    <i class="fas fa-lock text-[10px]"></i>
                    <span>Portal Staf</span>
                </a>
            </div>

        </div>
    </nav>

    <!-- Hero Section with Dynamic Brand Gradient & Instant Search -->
    <div id="lacak" class="relative brand-gradient-hero text-white pt-12 sm:pt-20 pb-24 sm:pb-32 px-4 sm:px-6 overflow-hidden rounded-b-[2.5rem] sm:rounded-b-[4rem] shadow-2xl">
        
        <!-- Ambient Decorative Glows -->
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-white/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-amber-200/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute inset-0 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none"></div>

        <div class="max-w-3xl mx-auto text-center relative z-10">
            
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full badge-tagline text-xs sm:text-sm font-black tracking-wide mb-5 shadow-md">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <span><?= htmlspecialchars($brand_theme['tagline']); ?></span>
            </div>

            <h1 class="text-2xl sm:text-4xl md:text-5xl font-black tracking-tight leading-tight mb-3 sm:mb-4 drop-shadow-xs">
                <?= htmlspecialchars($brand_theme['nama_laundry']); ?> &mdash; Lacak Cucian Real-Time
            </h1>
            
            <p class="text-white/90 text-xs sm:text-base max-w-xl mx-auto mb-8 font-medium leading-relaxed">
                Ketikkan nomor nota transaksi atau scan barcode struk Anda untuk melihat apakah pakaian sedang dicuci, disetrika, atau siap diambil.
            </p>

            <!-- Search Bar with Live Reactive Handling -->
            <form @submit.prevent="submitSearch" class="relative max-w-xl mx-auto">
                <div class="relative flex items-center">
                    <i class="fas fa-receipt absolute left-4 sm:left-5 text-slate-400 text-sm sm:text-lg"></i>
                    
                    <input type="text" 
                           x-model="searchQuery" 
                           @input="errorMsg = ''"
                           class="w-full py-4 sm:py-4.5 pl-11 sm:pl-14 pr-28 sm:pr-40 rounded-2xl bg-white text-slate-900 font-black shadow-2xl focus:outline-none focus:ring-4 focus:ring-white/50 transition placeholder-slate-400 uppercase text-xs sm:text-base tracking-wide" 
                           placeholder="CONTOH: LND-20261120-001" 
                           autocomplete="off" required>
                    
                    <!-- Search & Clear Action Buttons -->
                    <div class="absolute right-2 flex items-center gap-1.5">
                        <button type="button" 
                                x-show="searchQuery.length > 0" 
                                @click="searchQuery = ''; currentOrder = null; errorMsg = ''" 
                                class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg text-xs" 
                                title="Hapus">
                            <i class="fas fa-times"></i>
                        </button>

                        <button type="submit" 
                                :disabled="isLoading"
                                class="btn-brand disabled:opacity-50 px-4 sm:px-6 py-2.5 rounded-xl font-extrabold text-xs sm:text-sm flex items-center gap-2 shadow-lg transition active:scale-95">
                            <i x-show="!isLoading" class="fas fa-search text-xs"></i>
                            <i x-show="isLoading" class="fas fa-circle-notch fa-spin text-xs"></i>
                            <span x-text="isLoading ? 'Mencari...' : 'Lacak'"></span>
                        </button>
                    </div>
                </div>
            </form>

            <!-- Quick Action: QR Camera Scanner Button -->
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <button type="button" 
                        @click="openQrModal" 
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-bold backdrop-blur-md transition shadow-sm active:scale-95">
                    <i class="fas fa-qrcode text-indigo-300"></i>
                    <span>Scan QR Struk Kamera</span>
                </button>
            </div>

            <!-- Recent Orders from LocalStorage -->
            <div x-show="recentOrders.length > 0" x-cloak class="mt-5 max-w-xl mx-auto">
                <p class="text-[11px] text-indigo-300 font-bold uppercase tracking-wider mb-2 flex items-center justify-center gap-1.5">
                    <i class="fas fa-clock-rotate-left text-[10px]"></i> Pencarian Terakhir Anda:
                </p>
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <template x-for="item in recentOrders" :key="item.code">
                        <button type="button" 
                                @click="searchQuery = item.code; fetchTracking(item.code)" 
                                class="group px-3 py-1 bg-white/10 hover:bg-white/25 border border-white/20 rounded-full text-[11px] font-mono text-indigo-100 font-bold backdrop-blur-md transition flex items-center gap-1.5">
                            <i class="fas fa-ticket text-[9px] text-indigo-300"></i>
                            <span x-text="item.code"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Error Banner -->
            <div x-show="errorMsg" x-cloak x-transition class="mt-5 max-w-xl mx-auto bg-rose-500/95 backdrop-blur-md text-white text-xs sm:text-sm py-3 px-5 rounded-2xl flex items-center gap-2.5 shadow-2xl border border-rose-400/40 text-left">
                <i class="fas fa-circle-exclamation text-base shrink-0"></i>
                <span x-text="errorMsg" class="flex-1"></span>
            </div>

        </div>
    </div>

    <!-- Live Tracking Result Box (Appears reactively upon search) -->
    <template x-if="currentOrder">
        <div class="max-w-3xl mx-auto px-3 sm:px-4 -mt-14 sm:-mt-20 mb-14 sm:mb-20 relative z-20">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden">
            
            <!-- Result Top Card Header -->
            <div class="bg-slate-900 text-white p-5 sm:p-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] sm:text-[11px] font-bold text-indigo-300 uppercase tracking-wider block">No. Nota Transaksi</span>
                        <button type="button" @click="copyToClipboard(currentOrder.order_code)" class="text-xs text-indigo-300 hover:text-white transition" title="Salin Kode">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <h2 class="text-xl sm:text-3xl font-black tracking-tight font-mono text-white mt-0.5" x-text="currentOrder.order_code"></h2>
                    <p class="text-[11px] sm:text-xs text-slate-400 mt-1">
                        Didaftarkan: <span class="text-slate-200 font-semibold" x-text="currentOrder.created_at_fmt"></span> &bull; 
                        Pelanggan: <span class="text-slate-200 font-semibold" x-text="currentOrder.customer_name"></span>
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-[10px] sm:text-[11px] font-bold text-indigo-300 uppercase tracking-wider block">Total Biaya Cucian</span>
                    <h2 class="text-2xl sm:text-3xl font-black font-mono text-emerald-400" x-text="'Rp ' + Number(currentOrder.total_amount).toLocaleString('id-ID')"></h2>
                    <span class="inline-flex items-center gap-1.5 mt-1 px-3 py-1 rounded-full text-[10px] sm:text-[11px] font-extrabold uppercase tracking-wide"
                          :class="currentOrder.payment_status === 'paid' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="currentOrder.payment_status === 'paid' ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                        <span x-text="currentOrder.payment_status === 'paid' ? 'LUNAS (' + currentOrder.payment_method + ')' : 'BELUM LUNAS'"></span>
                    </span>
                </div>
            </div>

            <!-- Stepper Progress Tracker -->
            <div class="p-5 sm:p-8 border-b border-slate-100 bg-slate-50/60">
                
                <!-- Current Active Status Banner -->
                <div class="text-center mb-8">
                    <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-[10px] sm:text-xs font-black uppercase mb-2 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                        <span x-text="'Tahap Saat Ini: ' + currentOrder.step + ' dari 5'"></span>
                    </div>
                    
                    <h3 class="text-xl sm:text-3xl font-black text-slate-800 leading-tight" x-text="currentOrder.status_title"></h3>
                    <p class="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto mt-1.5" x-text="currentOrder.status_desc"></p>
                    
                    <!-- SLA Countdown Banner -->
                    <div class="mt-3 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold"
                         :class="currentOrder.sla_status === 'ready' || currentOrder.sla_status === 'completed' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : (currentOrder.sla_status === 'overdue' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-200 text-slate-700')">
                        <i class="fas fa-stopwatch text-indigo-600"></i>
                        <span x-text="currentOrder.sla_text"></span>
                    </div>
                </div>

                <!-- Horizontal Stepper (Mobile & Desktop Responsive) -->
                <div class="relative flex justify-between items-start px-2 sm:px-6 mb-6">
                    <div class="absolute top-5 left-0 w-full h-1 bg-slate-200 -z-0 rounded-full"></div>
                    <div class="absolute top-5 left-0 h-1 bg-gradient-to-r from-indigo-600 to-violet-500 -z-0 rounded-full transition-all duration-700" 
                         :style="'width: ' + ((currentOrder.step - 1) * 25) + '%;'"></div>

                    <!-- 1. Menunggu -->
                    <div class="flex flex-col items-center bg-transparent z-10 text-center max-w-[65px] sm:max-w-[85px]">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-sm"
                             :class="currentOrder.step >= 1 ? 'border-indigo-600 bg-indigo-600 text-white shadow-indigo-300' : 'border-slate-300 bg-white text-slate-300'">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-2 uppercase" :class="currentOrder.step >= 1 ? 'text-indigo-600' : 'text-slate-400'">Antrean</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400" x-text="currentOrder.created_at_fmt ? currentOrder.created_at_fmt.split(',')[0] : '-'"></span>
                    </div>

                    <!-- 2. Dicuci -->
                    <div class="flex flex-col items-center bg-transparent z-10 text-center max-w-[65px] sm:max-w-[85px]">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-sm"
                             :class="currentOrder.step >= 2 ? 'border-indigo-600 bg-indigo-600 text-white shadow-indigo-300' : 'border-slate-300 bg-white text-slate-300'">
                            <i class="fas fa-soap"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-2 uppercase" :class="currentOrder.step >= 2 ? 'text-indigo-600' : 'text-slate-400'">Dicuci</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400" x-text="currentOrder.processing_at_fmt ? currentOrder.processing_at_fmt.split(',')[0] : (currentOrder.step > 2 ? 'Selesai' : '-')"></span>
                    </div>

                    <!-- 3. Disetrika -->
                    <div class="flex flex-col items-center bg-transparent z-10 text-center max-w-[65px] sm:max-w-[85px]">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-sm"
                             :class="currentOrder.step >= 3 ? 'border-indigo-600 bg-indigo-600 text-white shadow-indigo-300' : 'border-slate-300 bg-white text-slate-300'">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-2 uppercase" :class="currentOrder.step >= 3 ? 'text-indigo-600' : 'text-slate-400'">Setrika</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400" x-text="currentOrder.ironing_at_fmt ? currentOrder.ironing_at_fmt.split(',')[0] : (currentOrder.step > 3 ? 'Selesai' : '-')"></span>
                    </div>

                    <!-- 4. Siap Diambil -->
                    <div class="flex flex-col items-center bg-transparent z-10 text-center max-w-[65px] sm:max-w-[85px]">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-sm"
                             :class="currentOrder.step >= 4 ? 'border-emerald-600 bg-emerald-600 text-white shadow-emerald-300 ring-4 ring-emerald-100' : 'border-slate-300 bg-white text-slate-300'">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-2 uppercase" :class="currentOrder.step >= 4 ? 'text-emerald-600' : 'text-slate-400'">Siap</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400" x-text="currentOrder.ready_at_fmt ? currentOrder.ready_at_fmt.split(',')[0] : (currentOrder.step >= 4 ? 'Siap' : '-')"></span>
                    </div>

                    <!-- 5. Diambil -->
                    <div class="flex flex-col items-center bg-transparent z-10 text-center max-w-[65px] sm:max-w-[85px]">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full border-2 sm:border-4 flex items-center justify-center font-bold text-xs sm:text-sm transition-all shadow-sm"
                             :class="currentOrder.step >= 5 ? 'border-emerald-600 bg-emerald-600 text-white shadow-emerald-300' : 'border-slate-300 bg-white text-slate-300'">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <p class="text-[9px] sm:text-xs font-bold mt-2 uppercase" :class="currentOrder.step >= 5 ? 'text-emerald-600' : 'text-slate-400'">Selesai</p>
                        <span class="text-[8px] sm:text-[10px] text-slate-400" x-text="currentOrder.completed_at_fmt ? currentOrder.completed_at_fmt.split(',')[0] : '-'"></span>
                    </div>
                </div>

                <!-- Auto-Refresh Live Polling Indicator -->
                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-3 border-t border-slate-200/60">
                    <div class="flex items-center gap-1.5 font-semibold text-emerald-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <span>Pembaruan Otomatis Aktif (Setiap 15 Detik)</span>
                    </div>
                    <button type="button" @click="fetchTracking(currentOrder.order_code)" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1 transition">
                        <i class="fas fa-rotate" :class="isLoading ? 'fa-spin' : ''"></i>
                        <span>Cek Sekarang</span>
                    </button>
                </div>

            </div>

            <!-- Order Details & Item Breakdown -->
            <div class="p-5 sm:p-8 space-y-6">
                
                <!-- Quick Attributes (Fragrance, Speed, Cashier) -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pewangi / Aroma</span>
                        <div class="flex items-center gap-1.5 mt-0.5 text-xs font-bold text-slate-800">
                            <i class="fas fa-spa text-pink-500"></i>
                            <span x-text="currentOrder.perfume || 'Sakura Blossom'"></span>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kecepatan</span>
                        <div class="flex items-center gap-1.5 mt-0.5 text-xs font-bold text-slate-800">
                            <i class="fas" :class="currentOrder.speed === 'express' ? 'fa-bolt text-amber-500' : 'fa-clock text-indigo-500'"></i>
                            <span x-text="currentOrder.speed === 'express' ? 'Kilat (Express)' : 'Reguler'"></span>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Metode Bayar</span>
                        <div class="flex items-center gap-1.5 mt-0.5 text-xs font-bold text-slate-800">
                            <i class="fas fa-wallet text-emerald-500"></i>
                            <span x-text="currentOrder.payment_method || 'Tunai'"></span>
                        </div>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kasir Pelayan</span>
                        <div class="flex items-center gap-1.5 mt-0.5 text-xs font-bold text-slate-800">
                            <i class="fas fa-user-check text-indigo-500"></i>
                            <span x-text="currentOrder.cashier_name || 'Staf Laundry'"></span>
                        </div>
                    </div>
                </div>

                <!-- Optional Rack Location & Condition Photo -->
                <div x-show="currentOrder.rack_location || currentOrder.item_photo" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between p-3.5 rounded-2xl border border-pink-100 bg-pink-50/50">
                    <div x-show="currentOrder.rack_location" class="flex items-center gap-2 text-xs font-bold text-slate-700">
                        <span class="w-8 h-8 rounded-xl bg-white text-brand flex items-center justify-center shadow-2xs border border-pink-100">
                            <i class="fas fa-boxes-packing"></i>
                        </span>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase block">Lokasi Rak Penyimpanan</span>
                            <span class="font-black text-slate-800" x-text="currentOrder.rack_location"></span>
                        </div>
                    </div>
                    <div x-show="currentOrder.item_photo" class="flex items-center gap-2.5">
                        <img :src="currentOrder.item_photo" alt="Foto Kondisi Cucian" class="w-14 h-14 rounded-xl object-cover border border-pink-200 shadow-xs">
                        <span class="text-[11px] font-bold text-slate-500">Foto Kondisi Awal</span>
                    </div>
                </div>

                <!-- Items Table -->
                <div>
                    <h4 class="text-xs font-black uppercase text-slate-400 tracking-wider mb-3">Rincian Item Pakaian</h4>
                    <div class="border border-slate-100 rounded-2xl overflow-hidden divide-y divide-slate-100">
                        <template x-for="(item, idx) in currentOrder.items" :key="idx">
                            <div class="p-3.5 sm:p-4 bg-white flex justify-between items-center text-xs sm:text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                                        <i class="fas" :class="item.icon || 'fa-shirt'"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800" x-text="item.service_name"></p>
                                        <p class="text-[11px] text-slate-400" x-text="item.qty + ' ' + item.unit + (item.notes ? ' • ' + item.notes : '')"></p>
                                    </div>
                                </div>
                                <span class="font-extrabold text-slate-800 font-mono" x-text="'Rp ' + Number(item.subtotal).toLocaleString('id-ID')"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Action Buttons: WhatsApp Contact, Share & Refresh -->
                <div class="flex flex-col sm:flex-row gap-2.5 pt-2">
                    <a :href="currentOrder.wa_admin_url" target="_blank" class="flex-1 px-5 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition">
                        <i class="fab fa-whatsapp text-base"></i>
                        <span>Tanya Admin via WhatsApp</span>
                    </a>
                    
                    <a :href="currentOrder.wa_share_url" target="_blank" class="px-5 py-3 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs sm:text-sm flex items-center justify-center gap-2 border border-indigo-200 transition">
                        <i class="fas fa-share-nodes"></i>
                        <span>Bagikan Status</span>
                    </a>

                    <button type="button" @click="copyTrackingLink" class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm flex items-center justify-center gap-2 transition">
                        <i class="fas fa-link"></i>
                        <span id="copyLinkText">Salin Tautan</span>
                    </button>
                </div>

            </div>

        </div>
    </div>
    </template>

    <!-- Services Showcase Catalog with Category Filter Pills & Estimator -->
    <div id="layanan" class="max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-20 border-t border-slate-200">
        
        <div class="text-center max-w-xl mx-auto mb-10 sm:mb-12">
            <span class="inline-block px-3.5 py-1.5 rounded-full badge-tagline text-xs font-black uppercase tracking-wider mb-2">
                Daftar Layanan & Tarif
            </span>
            <h2 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">
                Pilihan Paket Laundry Terbaik
            </h2>
            <p class="text-slate-500 text-xs sm:text-sm mt-2">
                Harga transparan, higienis, pengerjaan cepat dengan standar deterjen & setrika uap premium.
            </p>
        </div>

        <!-- Category Filter Pills -->
        <div class="flex gap-2 overflow-x-auto no-scrollbar justify-start sm:justify-center mb-8 pb-1">
            <?php foreach ($service_categories as $cat): ?>
                <button type="button" 
                        @click="selectedCategory = '<?= $cat; ?>'" 
                        :class="selectedCategory === '<?= $cat; ?>' ? 'btn-brand shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0">
                    <?= htmlspecialchars($cat); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Services Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($services_catalog as $svc): ?>
                <?php 
                    $cat = $svc['category'] ?? ($svc['service_type'] === 'kiloan' ? 'Kiloan' : 'Satuan');
                    $icon = $svc['icon'] ?? 'fa-shirt';
                ?>
                <div x-show="selectedCategory === 'Semua' || selectedCategory === '<?= addslashes($cat); ?>'" 
                     x-transition 
                     class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:shadow-xl hover:border-pink-200 transition-all flex flex-col justify-between group">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="w-12 h-12 rounded-2xl badge-tagline flex items-center justify-center text-lg font-bold group-hover:scale-110 transition duration-300">
                                <i class="fas <?= htmlspecialchars($icon); ?>"></i>
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-600">
                                <?= htmlspecialchars($cat); ?>
                            </span>
                        </div>
                        <h3 class="font-black text-lg text-slate-800 leading-tight mb-1"><?= htmlspecialchars($svc['service_name']); ?></h3>
                        <p class="text-xs text-slate-400 mb-4 flex items-center gap-1.5">
                            <i class="fas fa-clock text-brand"></i>
                            <span>Estimasi <?= (int)$svc['duration_hours']; ?> Jam</span>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Tarif</span>
                            <span class="text-lg font-black text-brand font-mono">Rp <?= number_format($svc['price'], 0, ',', '.'); ?></span>
                            <span class="text-xs text-slate-500">/ <?= htmlspecialchars($svc['unit']); ?></span>
                        </div>
                        <a href="#pickup" class="px-3.5 py-2 rounded-xl bg-slate-50 hover:bg-pink-50 text-brand font-bold text-xs transition border border-slate-200 group-hover:border-pink-200">
                            Pesan <i class="fas fa-arrow-right text-[10px] ml-1"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Interactive Quick Estimator Widget -->
        <div class="mt-12 brand-gradient-hero text-white rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden" 
             x-data="{ estWeight: 5, estPricePerKg: 7000 }">
            <div class="max-w-xl">
                <span class="inline-block px-3 py-1 rounded-full badge-tagline text-[10px] font-black uppercase tracking-wider mb-2">
                    Kalkulator Simulasi Biaya
                </span>
                <h3 class="text-xl sm:text-2xl font-black mb-2">Hitung Cepat Estimasi Biaya Laundry Kiloan</h3>
                <p class="text-xs sm:text-sm text-white/85 mb-6">Geser perkiraan berat cucian Anda untuk melihat total biaya secara instan.</p>

                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-xs font-bold mb-2">
                            <span>Perkiraan Berat Cucian:</span>
                            <span class="text-amber-200 font-mono text-base" x-text="estWeight + ' kg'"></span>
                        </div>
                        <input type="range" min="1" max="25" step="0.5" x-model="estWeight" 
                               class="w-full accent-amber-300 bg-white/30 h-2 rounded-lg cursor-pointer">
                    </div>

                    <div class="p-4 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-between border border-white/20">
                        <div>
                            <span class="text-[11px] text-white/85 block">Perkiraan Biaya Cuci Komplit:</span>
                            <span class="text-2xl font-black text-amber-200 font-mono" x-text="'Rp ' + (estWeight * estPricePerKg).toLocaleString('id-ID')"></span>
                        </div>
                        <a href="#pickup" class="px-5 py-2.5 badge-tagline hover:opacity-90 font-extrabold text-xs rounded-xl shadow-lg transition">
                            Jemput Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Keunggulan Outlet Showcase -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <div class="rounded-3xl p-6 sm:p-10 border border-pink-100 shadow-sm brand-gradient-soft">
            <div class="text-center max-w-xl mx-auto mb-8">
                <span class="inline-block px-3.5 py-1 rounded-full bg-white text-brand text-[11px] font-black uppercase tracking-wider mb-2 shadow-2xs">
                    Mengapa Memilih Kami?
                </span>
                <h2 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    Keunggulan <?= htmlspecialchars($brand_theme['nama_laundry']); ?>
                </h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white/95 backdrop-blur-sm p-5 rounded-2xl border border-white shadow-xs space-y-2">
                    <div class="w-10 h-10 rounded-xl badge-tagline flex items-center justify-center text-base font-bold">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-800">1 Mesin 1 Pelanggan</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Pakaian tidak dicampur dengan milik orang lain, lebih higienis dan aman dari tertukar.</p>
                </div>
                <div class="bg-white/95 backdrop-blur-sm p-5 rounded-2xl border border-white shadow-xs space-y-2">
                    <div class="w-10 h-10 rounded-xl badge-tagline flex items-center justify-center text-base font-bold">
                        <i class="fas fa-spray-can-sparkles"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-800">Pewangi Tahan Lama</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Pilihan aroma premium (Sakura, Ocean, Lavender, Akasia) yang lembut dan menyegarkan.</p>
                </div>
                <div class="bg-white/95 backdrop-blur-sm p-5 rounded-2xl border border-white shadow-xs space-y-2">
                    <div class="w-10 h-10 rounded-xl badge-tagline flex items-center justify-center text-base font-bold">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-800">Antar-Jemput Kupal</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Praktis dari rumah, pesan jemput online & pantau progres cucian langsung lewat HP.</p>
                </div>
                <div class="bg-white/95 backdrop-blur-sm p-5 rounded-2xl border border-white shadow-xs space-y-2">
                    <div class="w-10 h-10 rounded-xl badge-tagline flex items-center justify-center text-base font-bold">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h4 class="text-sm font-black text-slate-800">Layanan Express Kilat</h4>
                    <p class="text-xs text-slate-500 leading-relaxed">Butuh cepat? Tersedia paket Cuci Express selesai lebih singkat dengan kualitas tetap rapi.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Pickup Section (Formulir Minta Jemput Cucian) -->
    <div id="pickup" class="max-w-3xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-xl border border-slate-100">
            
            <div class="text-center mb-8">
                <span class="inline-block px-3.5 py-1.5 rounded-full badge-tagline text-xs font-black uppercase tracking-wider mb-2">
                    Layanan Antar Jemput
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    Minta Jemput Cucian ke Rumah (Pickup)
                </h2>
                <p class="text-slate-500 text-xs sm:text-sm max-w-md mx-auto mt-1.5">
                    Pakaian kotor menumpuk? Cukup isi formulir singkat di bawah ini, kurir kami akan segera meluncur ke lokasi Anda!
                </p>
            </div>

            <?php if (!empty($pickup_msg)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <i class="fas fa-circle-check text-emerald-600 text-lg"></i>
                        <span><?= htmlspecialchars($pickup_msg); ?></span>
                    </div>
                    <?php if (!empty($wa_confirm_url)): ?>
                        <a href="<?= $wa_confirm_url; ?>" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm shrink-0 flex items-center gap-1.5">
                            <i class="fab fa-whatsapp"></i> Konfirmasi ke WA
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($pickup_err)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm flex items-center gap-2.5">
                    <i class="fas fa-circle-exclamation text-rose-600 text-lg"></i>
                    <span><?= htmlspecialchars($pickup_err); ?></span>
                </div>
            <?php endif; ?>

            <form action="#pickup" method="POST" class="space-y-4" x-data="{ currentAddress: '' }">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1.5">Nama Lengkap Anda *</label>
                        <input type="text" name="customer_name" required placeholder="Contoh: Ibu Rina / Bpk Budi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1.5">Nomor WhatsApp / HP *</label>
                        <input type="tel" name="customer_phone" required placeholder="Contoh: 08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    </div>
                </div>

                <!-- Pickup Time Slot Selection -->
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1.5">Pilih Waktu Penjemputan</label>
                    <div class="grid grid-cols-3 gap-2" x-data="{ slot: 'Pagi (08:00 - 11:00)' }">
                        <input type="hidden" name="pickup_slot" :value="slot">
                        <button type="button" @click="slot = 'Pagi (08:00 - 11:00)'" 
                                :class="slot === 'Pagi (08:00 - 11:00)' ? 'btn-brand shadow-md' : 'bg-slate-50 text-slate-700 border border-slate-200'" 
                                class="py-2.5 px-3 rounded-xl text-xs font-bold text-center transition">
                            Pagi (08-11)
                        </button>
                        <button type="button" @click="slot = 'Siang (11:00 - 14:00)'" 
                                :class="slot === 'Siang (11:00 - 14:00)' ? 'btn-brand shadow-md' : 'bg-slate-50 text-slate-700 border border-slate-200'" 
                                class="py-2.5 px-3 rounded-xl text-xs font-bold text-center transition">
                            Siang (11-14)
                        </button>
                        <button type="button" @click="slot = 'Sore (14:00 - 17:00)'" 
                                :class="slot === 'Sore (14:00 - 17:00)' ? 'btn-brand shadow-md' : 'bg-slate-50 text-slate-700 border border-slate-200'" 
                                class="py-2.5 px-3 rounded-xl text-xs font-bold text-center transition">
                            Sore (14-17)
                        </button>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-600">Alamat Lengkap Penjemputan *</label>
                        <button type="button" @click="detectLocation" class="text-xs text-brand font-bold hover:underline flex items-center gap-1">
                            <i class="fas fa-location-crosshairs"></i> Gunakan Lokasi Saya
                        </button>
                    </div>
                    <textarea name="pickup_address" x-model="currentAddress" required rows="3" placeholder="Contoh: Jl. Anggrek No. 10, RT 02/05, Pagar hitam sebelah warung..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400 transition"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-600 mb-1.5">Catatan Tambahan untuk Kurir (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Titip ke satpam jika saya belum sampai rumah" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400 transition">
                </div>

                <div class="text-center pt-3">
                    <button type="submit" name="submit_public_pickup" class="w-full sm:w-auto px-8 py-4 btn-brand font-black rounded-2xl text-sm shadow-xl transition active:scale-95 inline-flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Kirim Permintaan Penjemputan</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Footer -->
    <footer class="text-white py-12 border-t border-white/10 mt-12" style="background: linear-gradient(180deg, var(--brand-accent) 0%, #1E1321 100%);">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-1 sm:grid-cols-3 gap-8 text-center sm:text-left">
            <div>
                <div class="flex items-center justify-center sm:justify-start gap-2.5 font-black text-xl text-white mb-3">
                    <?= render_brand_logo_html($brand_theme, 'w-9 h-9 rounded-xl', 'text-sm'); ?>
                    <div>
                        <span class="block leading-tight"><?= htmlspecialchars($brand_theme['nama_laundry']); ?></span>
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full badge-tagline inline-block mt-0.5"><?= htmlspecialchars($brand_theme['tagline']); ?></span>
                    </div>
                </div>
                <p class="text-white/75 text-xs leading-relaxed max-w-xs mx-auto sm:mx-0">
                    Layanan laundry bersih, wangi, cepat, dan terpercaya dengan pelacakan status real-time.
                </p>
            </div>

            <div>
                <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-3">Informasi Outlet</h4>
                <p class="text-xs text-white/85 mb-2"><i class="fas fa-map-marker-alt text-amber-300 mr-2"></i><?= htmlspecialchars($brand_theme['alamat']); ?></p>
                <p class="text-xs text-white/85 mb-2"><i class="fab fa-whatsapp text-emerald-300 mr-2"></i><?= htmlspecialchars($brand_theme['whatsapp']); ?></p>
                <p class="text-xs text-white/85"><i class="fab fa-instagram text-pink-300 mr-2"></i><?= htmlspecialchars($brand_theme['instagram']); ?></p>
            </div>

            <div class="text-center sm:text-right">
                <h4 class="text-sm font-bold text-white uppercase tracking-wider mb-3">Akses Sistem</h4>
                <div class="space-y-2 text-xs">
                    <div><a href="panduan.php" class="text-amber-200 hover:underline"><i class="fas fa-book-open mr-1"></i>Buku Panduan Penggunaan</a></div>
                    <div><a href="auth/login.php" class="text-white/80 hover:underline"><i class="fas fa-lock mr-1"></i>Portal Staf & Pemilik</a></div>
                    <div><a href="#pickup" class="text-white/80 hover:underline"><i class="fas fa-truck-pickup mr-1"></i>Minta Jemput Cucian</a></div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 mt-8 pt-8 border-t border-white/10 text-center text-xs text-white/60">
            &copy; <?= date('Y'); ?> <?= htmlspecialchars($brand_theme['nama_laundry']); ?>. All rights reserved.
        </div>
    </footer>

    <!-- QR Camera Scanner Modal with Laser Beam -->
    <div id="qrModal" class="hidden fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 text-center text-slate-800 relative shadow-2xl">
            <button type="button" onclick="closeQrModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                <i class="fas fa-camera"></i>
            </div>
            <h3 class="font-black text-base text-slate-900">Scan QR Code Struk</h3>
            <p class="text-xs text-slate-500 mb-4">Arahkan kamera ke QR Code yang tercetak pada struk fisik Anda.</p>
            
            <div class="relative overflow-hidden rounded-2xl border-2 border-indigo-200 bg-slate-900 min-h-[220px]">
                <div id="qrReader" class="w-full"></div>
                <div class="laser-beam pointer-events-none"></div>
            </div>
            
            <p id="qrScanStatus" class="text-[11px] text-slate-500 mt-3 flex items-center justify-center gap-1.5">
                <i class="fas fa-spinner fa-spin text-indigo-500"></i> Membuka kamera...
            </p>
        </div>
    </div>

    <!-- Floating WhatsApp & Instagram Action Buttons -->
    <?php
        $float_wa = format_wa_phone($brand_theme['whatsapp']);
        $float_ig_handle = ltrim($brand_theme['instagram'], '@');
    ?>
    <div class="fixed bottom-5 right-5 z-40 flex flex-col gap-2.5 items-end">
        <a href="https://instagram.com/<?= urlencode($float_ig_handle); ?>" target="_blank"
           title="Instagram <?= htmlspecialchars($brand_theme['instagram']); ?>"
           class="w-11 h-11 rounded-full bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 text-white shadow-lg flex items-center justify-center text-lg hover:scale-110 transition">
            <i class="fab fa-instagram"></i>
        </a>
        <a href="https://wa.me/<?= $float_wa; ?>?text=<?= rawurlencode('Halo ' . $brand_theme['nama_laundry'] . ', saya ingin tanya layanan laundry.'); ?>" target="_blank"
           title="Chat WhatsApp <?= htmlspecialchars($brand_theme['whatsapp']); ?>"
           class="px-4 py-2.5 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white shadow-xl shadow-emerald-500/30 flex items-center gap-2 text-xs font-extrabold hover:scale-105 transition">
            <i class="fab fa-whatsapp text-base"></i>
            <span class="hidden sm:inline">Chat Admin</span>
        </a>
    </div>

    <!-- Alpine.js Application Logic -->
    <script>
        function trackingApp(initialCode) {
            return {
                searchQuery: initialCode || '',
                currentOrder: null,
                isLoading: false,
                errorMsg: '',
                selectedCategory: 'Semua',
                recentOrders: [],
                pollingTimer: null,

                init() {
                    this.loadRecentOrders();
                    if (this.searchQuery) {
                        this.fetchTracking(this.searchQuery);
                    }
                },

                loadRecentOrders() {
                    try {
                        this.recentOrders = JSON.parse(localStorage.getItem('lnd_recent_orders') || '[]');
                    } catch(e) {
                        this.recentOrders = [];
                    }
                },

                saveRecentOrder(code, name) {
                    if (!code) return;
                    try {
                        let list = this.recentOrders.filter(it => it.code.toUpperCase() !== code.toUpperCase());
                        list.unshift({ code: code.toUpperCase(), name: name || '' });
                        if (list.length > 5) list = list.slice(0, 5);
                        this.recentOrders = list;
                        localStorage.setItem('lnd_recent_orders', JSON.stringify(list));
                    } catch(e) {}
                },

                submitSearch() {
                    const cleanCode = this.searchQuery.trim().toUpperCase();
                    if (!cleanCode) return;
                    this.fetchTracking(cleanCode);
                },

                fetchTracking(code) {
                    this.isLoading = true;
                    this.errorMsg = '';

                    fetch('api/tracking_api.php?kode=' + encodeURIComponent(code))
                        .then(res => res.json())
                        .then(data => {
                            this.isLoading = false;
                            if (data.success && data.order) {
                                this.currentOrder = data.order;
                                this.searchQuery = data.order.order_code;
                                this.saveRecentOrder(data.order.order_code, data.order.customer_name);
                                window.playTone('success');

                                // Start background polling for status changes
                                if (this.pollingTimer) clearInterval(this.pollingTimer);
                                this.pollingTimer = setInterval(() => {
                                    if (this.currentOrder && this.currentOrder.status !== 'completed') {
                                        this.silentPoll(this.currentOrder.order_code);
                                    }
                                }, 15000);
                            } else {
                                this.currentOrder = null;
                                this.errorMsg = data.message || 'Nomor nota tidak ditemukan.';
                            }
                        })
                        .catch(err => {
                            this.isLoading = false;
                            this.errorMsg = 'Terjadi kesalahan jaringan saat melacak status. Silakan coba lagi.';
                        });
                },

                silentPoll(code) {
                    fetch('api/tracking_api.php?kode=' + encodeURIComponent(code))
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.order) {
                                if (this.currentOrder && this.currentOrder.status !== data.order.status) {
                                    window.playTone('success');
                                }
                                this.currentOrder = data.order;
                            }
                        })
                        .catch(() => {});
                },

                copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('Nomor Nota ' + text + ' berhasil disalin!');
                    }).catch(() => {});
                },

                copyTrackingLink() {
                    const url = window.location.origin + window.location.pathname + '?kode=' + encodeURIComponent(this.currentOrder.order_code);
                    navigator.clipboard.writeText(url).then(() => {
                        const btn = document.getElementById('copyLinkText');
                        if (btn) {
                            btn.innerText = 'Tersalin! ✓';
                            setTimeout(() => btn.innerText = 'Salin Tautan', 2000);
                        }
                    }).catch(() => {
                        prompt('Salin tautan ini:', url);
                    });
                },

                detectLocation() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => {
                                this.currentAddress = `Koordinat: ${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)} (Mohon lengkapi detail nama jalan / nomor rumah)`;
                            },
                            err => {
                                alert('Izin lokasi ditolak atau tidak tersedia pada browser.');
                            }
                        );
                    } else {
                        alert('Geolocation tidak didukung pada browser ini.');
                    }
                },

                openQrModal() {
                    openQrModal(code => {
                        this.searchQuery = code;
                        this.fetchTracking(code);
                    });
                }
            };
        }

        // QR Code Scanner Support
        let html5QrScanner = null;
        let qrCallback = null;

        function openQrModal(cb) {
            qrCallback = cb;
            const modal = document.getElementById('qrModal');
            const status = document.getElementById('qrScanStatus');
            modal.classList.remove('hidden');
            status.innerHTML = '<i class="fas fa-spinner fa-spin text-indigo-500"></i> Menyiapkan kamera...';

            if (!html5QrScanner) {
                html5QrScanner = new Html5Qrcode("qrReader");
            }

            const config = { fps: 10, qrbox: { width: 220, height: 220 } };
            html5QrScanner.start({ facingMode: "environment" }, config, (decodedText) => {
                let orderCode = decodedText.trim();
                try {
                    const parsedUrl = new URL(decodedText);
                    const k = parsedUrl.searchParams.get('kode');
                    if (k) orderCode = k;
                } catch(e) {}

                window.playTone('beep');
                status.innerHTML = '<span class="text-emerald-600 font-bold"><i class="fas fa-check-circle"></i> Berhasil dibaca!</span>';
                
                closeQrModal();
                if (typeof qrCallback === 'function') {
                    qrCallback(orderCode);
                } else {
                    window.location.href = 'index.php?kode=' + encodeURIComponent(orderCode);
                }
            }, () => {}).then(() => {
                status.innerHTML = '<i class="fas fa-camera text-indigo-600 mr-1"></i> Arahkan kamera ke QR Struk';
            }).catch(err => {
                status.innerHTML = '<span class="text-rose-500 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Kamera tidak dapat diakses atau diblokir browser.</span>';
            });
        }

        function closeQrModal() {
            const modal = document.getElementById('qrModal');
            modal.classList.add('hidden');
            if (html5QrScanner) {
                try { html5QrScanner.stop().catch(() => {}); } catch(e) {}
            }
        }

        // Register PWA Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
