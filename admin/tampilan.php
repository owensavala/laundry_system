<?php
// admin/tampilan.php - Kustomisasi Kombinasi Warna, Nama, Tagline, & Logo Laundry
require_once '../includes/auth_check.php';
check_auth(['admin', 'superadmin']);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_tampilan'])) {
    $nama = trim($_POST['nama_laundry'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $logo_icon = trim($_POST['logo_icon'] ?? 'fa-soap');
    $logo_data = trim($_POST['logo_data'] ?? '');

    $theme_primary   = trim($_POST['theme_primary'] ?? '#DB2777');
    $theme_pastel    = trim($_POST['theme_pastel'] ?? '#FFCED8');
    $theme_secondary = trim($_POST['theme_secondary'] ?? '#FFE7B8');
    $theme_accent    = trim($_POST['theme_accent'] ?? '#701A45');
    $theme_bg        = trim($_POST['theme_bg'] ?? '#FFF9FA');

    // Handle direct file upload fallback if JS resize didn't populate logo_data
    if (!empty($_FILES['logo_file']['tmp_name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/svg+xml'];
        $mime = mime_content_type($_FILES['logo_file']['tmp_name']);
        if (in_array($mime, $allowed) && $_FILES['logo_file']['size'] <= 2 * 1024 * 1024) {
            $raw = file_get_contents($_FILES['logo_file']['tmp_name']);
            $logo_data = 'data:' . $mime . ';base64,' . base64_encode($raw);
        } else {
            $err = 'Format logo harus PNG/JPG/WEBP/SVG dan maksimal 2MB.';
        }
    }

    if (isset($_POST['hapus_logo']) && $_POST['hapus_logo'] === '1') {
        $logo_data = '';
    }

    if (empty($nama)) {
        $err = 'Nama laundry wajib diisi!';
    } elseif (empty($err)) {
        $stmt = mysqli_prepare($conn, "UPDATE settings SET nama_laundry=?, tagline=?, alamat=?, whatsapp=?, instagram=?, logo_data=?, logo_icon=?, theme_primary=?, theme_pastel=?, theme_secondary=?, theme_accent=?, theme_bg=? WHERE id=1");
        mysqli_stmt_bind_param($stmt, "ssssssssssss", $nama, $tagline, $alamat, $whatsapp, $instagram, $logo_data, $logo_icon, $theme_primary, $theme_pastel, $theme_secondary, $theme_accent, $theme_bg);
        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Tampilan, kombinasi warna, nama, & logo berhasil diperbarui untuk seluruh sistem!';
        } else {
            $err = 'Gagal menyimpan perubahan: ' . mysqli_error($conn);
        }
    }
}

$set = get_settings($conn);
require_once '../includes/theme.php';
$cur = get_brand_theme($set);

require_once '../layout/header.php';
?>

<div x-data="appearanceApp()" class="max-w-7xl mx-auto pb-16">

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/90 backdrop-blur-md p-5 rounded-3xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl text-white flex items-center justify-center text-xl shadow-lg" style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));">
                <i class="fas fa-palette"></i>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight">Kustomisasi Tampilan, Warna & Logo</h1>
                <p class="text-xs text-slate-500">Ubah nama usaha, slogan, logo, dan kombinasi warna tema secara langsung.</p>
            </div>
        </div>
        <a href="../index.php" target="_blank" class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 transition flex items-center gap-2">
            <i class="fas fa-external-link-alt text-brand"></i> Lihat Halaman Publik
        </a>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm px-4 py-3.5 rounded-2xl flex items-center gap-2.5 shadow-xs">
            <i class="fas fa-circle-check text-emerald-500 text-base"></i>
            <span><?= $msg; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm px-4 py-3.5 rounded-2xl flex items-center gap-2.5 shadow-xs">
            <i class="fas fa-circle-exclamation text-rose-500 text-base"></i>
            <span><?= $err; ?></span>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <input type="hidden" name="logo_data" :value="logoData">
        <input type="hidden" name="hapus_logo" :value="removeLogoFlag">

        <!-- LEFT: Controls (7 cols) -->
        <div class="lg:col-span-7 space-y-6">

            <!-- 1. Brand Identity & Contact -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i class="fas fa-store text-brand"></i>
                    <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Identitas & Kontak Laundry</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nama Laundry *</label>
                        <input type="text" name="nama_laundry" x-model="nama" required
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Tagline / Slogan</label>
                        <input type="text" name="tagline" x-model="tagline" placeholder="Solusi Laundry Anda"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nomor WhatsApp</label>
                        <input type="text" name="whatsapp" x-model="whatsapp" placeholder="081253996394"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Instagram</label>
                        <input type="text" name="instagram" x-model="instagram" placeholder="@laundry_ar-roniyah"
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Alamat Outlet</label>
                    <input type="text" name="alamat" x-model="alamat" placeholder="Jln. Raya Pelabuhan Kupal"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none focus:ring-2 focus:ring-pink-400">
                </div>
            </div>

            <!-- 2. Logo Upload & Icon Picker -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-4">
                <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-image text-brand"></i>
                        <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Logo Usaha</h2>
                    </div>
                    <button type="button" x-show="logoData" @click="clearLogo()" class="text-[11px] font-bold text-rose-600 hover:underline">
                        <i class="fas fa-trash mr-1"></i> Hapus Gambar Logo
                    </button>
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-4">
                    <div class="w-20 h-20 rounded-2xl overflow-hidden border border-slate-200 flex items-center justify-center shrink-0 shadow-sm"
                         :style="!logoData ? `background: linear-gradient(135deg, ${themePrimary}, ${themeAccent}); color: #fff;` : 'background: #fff;'">
                        <template x-if="logoData">
                            <img :src="logoData" class="w-full h-full object-cover" alt="Logo Preview">
                        </template>
                        <template x-if="!logoData">
                            <i :class="'fas ' + logoIcon + ' text-3xl'"></i>
                        </template>
                    </div>

                    <div class="flex-1 w-full space-y-2">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase">Upload Gambar Logo (PNG / JPG / WEBP)</label>
                        <input type="file" name="logo_file" accept="image/*" @change="handleLogoUpload($event)"
                               class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                        <p class="text-[11px] text-slate-400">Jika tidak mengunggah gambar, sistem akan menampilkan ikon pilihan di bawah ini.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2">Atau Pilih Ikon Logo</label>
                    <input type="hidden" name="logo_icon" :value="logoIcon">
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <template x-for="ic in availableIcons" :key="ic.icon">
                            <button type="button" @click="logoIcon = ic.icon; window.playTone && window.playTone('pop')"
                                    :class="logoIcon === ic.icon ? 'ring-2 ring-offset-1 border-slate-800 bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                                    class="p-2.5 rounded-xl border text-center transition flex flex-col items-center gap-1">
                                <i :class="'fas ' + ic.icon + ' text-base'"></i>
                                <span class="text-[10px] font-bold" x-text="ic.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- 3. Color Palette & Presets -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-xs space-y-5">
                <div class="border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i class="fas fa-swatchbook text-brand"></i>
                    <h2 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider">Kombinasi Warna Tema</h2>
                </div>

                <!-- 1-Click Presets -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2">Preset Kombinasi Warna Siap Pakai</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <template x-for="preset in presets" :key="preset.name">
                            <button type="button" @click="applyPreset(preset)"
                                    class="p-3 rounded-2xl border border-slate-200 hover:border-slate-400 bg-slate-50/70 hover:bg-white transition flex items-center justify-between text-left group">
                                <div>
                                    <span class="text-xs font-black text-slate-800 block" x-text="preset.name"></span>
                                    <span class="text-[10px] text-slate-400" x-text="preset.desc"></span>
                                </div>
                                <div class="flex items-center -space-x-1.5">
                                    <span class="w-5 h-5 rounded-full border border-white shadow-xs" :style="'background:' + preset.primary"></span>
                                    <span class="w-5 h-5 rounded-full border border-white shadow-xs" :style="'background:' + preset.pastel"></span>
                                    <span class="w-5 h-5 rounded-full border border-white shadow-xs" :style="'background:' + preset.secondary"></span>
                                    <span class="w-5 h-5 rounded-full border border-white shadow-xs" :style="'background:' + preset.accent"></span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Custom Color Pickers -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700">Warna Utama (Tombol)</label>
                            <span class="text-[10px] font-mono text-slate-400 uppercase" x-text="themePrimary"></span>
                        </div>
                        <input type="color" name="theme_primary" x-model="themePrimary" class="w-11 h-9 rounded-lg cursor-pointer border border-slate-300 bg-white p-0.5">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700">Warna Pastel Lembut</label>
                            <span class="text-[10px] font-mono text-slate-400 uppercase" x-text="themePastel"></span>
                        </div>
                        <input type="color" name="theme_pastel" x-model="themePastel" class="w-11 h-9 rounded-lg cursor-pointer border border-slate-300 bg-white p-0.5">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700">Warna Tagline / Cream</label>
                            <span class="text-[10px] font-mono text-slate-400 uppercase" x-text="themeSecondary"></span>
                        </div>
                        <input type="color" name="theme_secondary" x-model="themeSecondary" class="w-11 h-9 rounded-lg cursor-pointer border border-slate-300 bg-white p-0.5">
                    </div>

                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700">Warna Gelap / Kontras</label>
                            <span class="text-[10px] font-mono text-slate-400 uppercase" x-text="themeAccent"></span>
                        </div>
                        <input type="color" name="theme_accent" x-model="themeAccent" class="w-11 h-9 rounded-lg cursor-pointer border border-slate-300 bg-white p-0.5">
                    </div>

                    <div class="sm:col-span-2 p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                        <div>
                            <label class="block text-[11px] font-extrabold text-slate-700">Warna Latar Halaman (Background Tint)</label>
                            <span class="text-[10px] font-mono text-slate-400 uppercase" x-text="themeBg"></span>
                        </div>
                        <input type="color" name="theme_bg" x-model="themeBg" class="w-11 h-9 rounded-lg cursor-pointer border border-slate-300 bg-white p-0.5">
                    </div>
                </div>

                <div class="pt-3 flex justify-end">
                    <button type="submit" name="simpan_tampilan"
                            class="w-full sm:w-auto px-7 py-3.5 rounded-2xl text-white font-black text-xs sm:text-sm shadow-xl transition flex items-center justify-center gap-2 cursor-pointer"
                            :style="`background-color: ${themePrimary};`">
                        <i class="fas fa-save"></i>
                        <span>Simpan & Terapkan Tampilan</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- RIGHT: Real-time Live Preview (5 cols) -->
        <div class="lg:col-span-5 lg:sticky lg:top-24 space-y-4">
            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Preview Tampilan
                    </span>
                    <span class="text-[10px] font-bold text-slate-400">Otomatis berubah saat diedit</span>
                </div>

                <!-- Preview Canvas -->
                <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-inner transition-colors duration-300" :style="`background-color: ${themeBg};`">
                    
                    <!-- Mini Navbar -->
                    <div class="bg-white/90 px-4 py-3 flex items-center justify-between border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl overflow-hidden flex items-center justify-center text-white text-xs shrink-0"
                                 :style="!logoData ? `background: linear-gradient(135deg, ${themePrimary}, ${themeAccent});` : 'background: #fff;'">
                                <template x-if="logoData">
                                    <img :src="logoData" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!logoData">
                                    <i :class="'fas ' + logoIcon"></i>
                                </template>
                            </div>
                            <span class="font-black text-xs truncate max-w-[140px]" :style="`color: ${themeAccent};`" x-text="nama || 'Nama Laundry'"></span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-white" :style="`background-color: ${themePrimary};`">Portal Staf</span>
                    </div>

                    <!-- Mini Hero -->
                    <div class="p-6 text-center text-white space-y-2.5"
                         :style="`background: linear-gradient(135deg, ${themeAccent} 0%, ${themePrimary} 60%, ${themePastel} 100%);`">
                        <span class="inline-block px-3 py-1 rounded-full text-[10px] font-black shadow-xs"
                              :style="`background-color: ${themeSecondary}; color: ${themeAccent};`"
                              x-text="tagline || 'Solusi Laundry Anda'"></span>
                        <h3 class="text-lg font-black tracking-tight drop-shadow-xs" x-text="nama || 'Laundry Ar-Roniyah'"></h3>
                        <p class="text-[11px] text-white/90">Lacak status cucian Anda secara real-time.</p>
                        <div class="bg-white rounded-xl p-1.5 flex items-center gap-1.5 max-w-[240px] mx-auto shadow-md">
                            <span class="text-[10px] text-slate-400 pl-2 flex-1 text-left font-mono">LND-2026...</span>
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-white" :style="`background-color: ${themePrimary};`">Lacak</span>
                        </div>
                    </div>

                    <!-- Mini Info Card -->
                    <div class="p-4 space-y-2">
                        <div class="p-3 rounded-xl border flex items-center justify-between text-[11px]"
                             :style="`background-color: ${themePastel}40; border-color: ${themePastel};`">
                            <span class="font-bold text-slate-700"><i class="fab fa-whatsapp text-emerald-600 mr-1"></i> <span x-text="whatsapp"></span></span>
                            <span class="font-bold text-slate-700"><i class="fab fa-instagram text-pink-600 mr-1"></i> <span x-text="instagram"></span></span>
                        </div>
                        <p class="text-[10px] text-slate-500 text-center">
                            <i class="fas fa-map-marker-alt mr-1" :style="`color: ${themePrimary};`"></i>
                            <span x-text="alamat"></span>
                        </p>
                    </div>

                </div>
            </div>
        </div>

    </form>

</div>

<script>
function appearanceApp() {
    return {
        nama: <?= json_encode($cur['nama_laundry']); ?>,
        tagline: <?= json_encode($cur['tagline']); ?>,
        whatsapp: <?= json_encode($cur['whatsapp']); ?>,
        instagram: <?= json_encode($cur['instagram']); ?>,
        alamat: <?= json_encode($cur['alamat']); ?>,
        logoData: <?= json_encode($cur['logo_data']); ?>,
        logoIcon: <?= json_encode($cur['logo_icon']); ?>,
        removeLogoFlag: '0',

        themePrimary: <?= json_encode($cur['primary']); ?>,
        themePastel: <?= json_encode($cur['pastel']); ?>,
        themeSecondary: <?= json_encode($cur['secondary']); ?>,
        themeAccent: <?= json_encode($cur['accent']); ?>,
        themeBg: <?= json_encode($cur['bg']); ?>,

        availableIcons: [
            { icon: 'fa-soap', label: 'Sabun' },
            { icon: 'fa-shirt', label: 'Baju' },
            { icon: 'fa-basket-shopping', label: 'Keranjang' },
            { icon: 'fa-sparkles', label: 'Kilau' },
            { icon: 'fa-droplet', label: 'Air' },
            { icon: 'fa-wind', label: 'Segar' }
        ],

        presets: [
            {
                name: '🌸 Ar-Roniyah Pastel',
                desc: 'Pink #FFCED8 & Cream Kuning',
                primary: '#DB2777',
                pastel: '#FFCED8',
                secondary: '#FFE7B8',
                accent: '#701A45',
                bg: '#FFF9FA'
            },
            {
                name: '💎 Indigo Modern',
                desc: 'Elegan Indigo & Biru Langit',
                primary: '#4F46E5',
                pastel: '#E0E7FF',
                secondary: '#A5F3FC',
                accent: '#312E81',
                bg: '#F8FAFC'
            },
            {
                name: '🌿 Emerald Fresh',
                desc: 'Hijau Segar & Kuning Lembut',
                primary: '#059669',
                pastel: '#D1FAE5',
                secondary: '#FEF3C7',
                accent: '#064E3B',
                bg: '#F0FDF4'
            },
            {
                name: '🌊 Ocean Breeze',
                desc: 'Biru Laut & Putih Bersih',
                primary: '#0284C7',
                pastel: '#BAE6FD',
                secondary: '#E0F2FE',
                accent: '#0C4A6E',
                bg: '#F0F9FF'
            },
            {
                name: '🍑 Peach Blossom',
                desc: 'Hangat Peach & Aprikot',
                primary: '#EA580C',
                pastel: '#FED7AA',
                secondary: '#FFEDD5',
                accent: '#7C2D12',
                bg: '#FFF7ED'
            }
        ],

        applyPreset(p) {
            if (window.playTone) window.playTone('pop');
            this.themePrimary = p.primary;
            this.themePastel = p.pastel;
            this.themeSecondary = p.secondary;
            this.themeAccent = p.accent;
            this.themeBg = p.bg;
        },

        clearLogo() {
            this.logoData = '';
            this.removeLogoFlag = '1';
        },

        handleLogoUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.removeLogoFlag = '0';

            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = new Image();
                img.onload = () => {
                    // Resize to max 256x256 for fast loading & serverless compatibility
                    const canvas = document.createElement('canvas');
                    const maxDim = 256;
                    let w = img.width, h = img.height;
                    if (w > h && w > maxDim) {
                        h = Math.round(h * maxDim / w);
                        w = maxDim;
                    } else if (h >= w && h > maxDim) {
                        w = Math.round(w * maxDim / h);
                        h = maxDim;
                    }
                    canvas.width = w;
                    canvas.height = h;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    this.logoData = canvas.toDataURL('image/png');
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    };
}
</script>

<?php require_once '../layout/footer.php'; ?>
