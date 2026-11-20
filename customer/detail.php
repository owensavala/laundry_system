<?php
// customer/detail.php - Customer Order Detail View
require_once '../includes/auth_check.php';
check_auth(['customer']);

$id = (int)($_GET['id'] ?? 0);
$cust_id = (int)$_SESSION['user_id'];
$cust_phone = $_SESSION['phone'] ?? '';

$q = mysqli_query($conn, "SELECT * FROM orders WHERE id='$id' AND (customer_id='$cust_id' OR customer_phone='$cust_phone')");
$order = mysqli_fetch_assoc($q);

if (!$order) {
    header("Location: index.php");
    exit();
}

require_once '../layout/header.php';

$q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit 
    FROM order_details od 
    LEFT JOIN services s ON od.service_id = s.id 
    WHERE od.order_id = '{$order['id']}'");

$st = $order['status'];
$step = 1;
if ($st === 'processing') $step = 2;
elseif ($st === 'ironing') $step = 3;
elseif ($st === 'ready') $step = 4;
elseif ($st === 'completed') $step = 5;
?>

<div class="mb-6 flex items-center justify-between">
    <a href="index.php" class="text-xs font-bold text-slate-500 hover:text-indigo-600 transition flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Kembali ke Riwayat Cucian
    </a>
    <a href="../admin/cetak_nota.php?id=<?= $order['id']; ?>" target="_blank" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
        <i class="fas fa-print"></i> Cetak / Simpan Struk
    </a>
</div>

<div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-100 p-6 sm:p-8">
    <div class="flex justify-between items-start border-b border-slate-100 pb-4 mb-6">
        <div>
            <span class="text-xs text-slate-400 font-bold uppercase">Nota Cucian</span>
            <h2 class="text-2xl font-black text-indigo-600 font-mono mt-0.5"><?= htmlspecialchars($order['order_code']); ?></h2>
            <p class="text-xs text-slate-500 mt-1">Tanggal Masuk: <?= date('d M Y, H:i', strtotime($order['created_at'])); ?></p>
        </div>
        <div class="text-right">
            <span class="text-xs text-slate-400 font-bold uppercase">Total Tagihan</span>
            <h3 class="text-2xl font-black text-slate-900 font-mono mt-0.5">Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?></h3>
            <span class="inline-block mt-1 px-3 py-0.5 rounded-full text-xs font-extrabold uppercase <?= $order['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?= $order['payment_status'] === 'paid' ? 'Lunas (' . $order['payment_method'] . ')' : 'Belum Lunas'; ?>
            </span>
        </div>
    </div>

    <!-- Stepper Tracker -->
    <div class="mb-8 px-2 sm:px-6">
        <div class="relative flex justify-between items-center mb-2">
            <div class="absolute top-1/2 left-0 w-full h-1 bg-slate-200 -z-10 rounded-full"></div>
            <div class="absolute top-1/2 left-0 h-1 bg-indigo-600 -z-10 rounded-full transition-all duration-700" style="width: <?= ($step - 1) * 25; ?>%;"></div>

            <div class="flex flex-col items-center bg-white px-2">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 1 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <p class="text-[10px] font-bold mt-1.5 <?= $step >= 1 ? 'text-indigo-600' : 'text-slate-400'; ?>">Menunggu</p>
            </div>

            <div class="flex flex-col items-center bg-white px-2">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 2 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                    <i class="fas fa-soap"></i>
                </div>
                <p class="text-[10px] font-bold mt-1.5 <?= $step >= 2 ? 'text-indigo-600' : 'text-slate-400'; ?>">Dicuci</p>
            </div>

            <div class="flex flex-col items-center bg-white px-2">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 3 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                    <i class="fas fa-temperature-high"></i>
                </div>
                <p class="text-[10px] font-bold mt-1.5 <?= $step >= 3 ? 'text-indigo-600' : 'text-slate-400'; ?>">Disetrika</p>
            </div>

            <div class="flex flex-col items-center bg-white px-2">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 4 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                    <i class="fas fa-check"></i>
                </div>
                <p class="text-[10px] font-bold mt-1.5 <?= $step >= 4 ? 'text-indigo-600' : 'text-slate-400'; ?>">Selesai</p>
            </div>

            <div class="flex flex-col items-center bg-white px-2">
                <div class="w-8 h-8 rounded-full border-2 flex items-center justify-center text-xs font-bold <?= $step >= 5 ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 text-slate-300'; ?>">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <p class="text-[10px] font-bold mt-1.5 <?= $step >= 5 ? 'text-indigo-600' : 'text-slate-400'; ?>">Diambil</p>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="border border-slate-100 rounded-2xl overflow-hidden mb-6">
        <table class="w-full text-xs text-left">
            <thead class="bg-slate-50 text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100">
                <tr>
                    <th class="p-3">Layanan</th>
                    <th class="p-3 text-center">Jumlah</th>
                    <th class="p-3 text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php while ($it = mysqli_fetch_assoc($q_items)): ?>
                <tr>
                    <td class="p-3 font-semibold text-slate-800"><?= htmlspecialchars($it['service_name']); ?></td>
                    <td class="p-3 text-center font-bold text-slate-600"><?= $it['qty']; ?> <?= $it['unit']; ?></td>
                    <td class="p-3 text-right font-mono font-bold text-indigo-600">Rp <?= number_format($it['subtotal'], 0, ',', '.'); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($order['notes'])): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 p-3.5 rounded-2xl text-xs mb-6">
        <strong>Catatan Kasir:</strong> <?= htmlspecialchars($order['notes']); ?>
    </div>
    <?php endif; ?>

    <div class="text-center pt-2">
        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $sys_settings['whatsapp']); ?>?text=Halo%20Admin,%20mau%20tanya%20status%20cucian%20<?= $order['order_code']; ?>" 
           target="_blank" class="inline-flex items-center gap-2 text-emerald-600 font-bold text-xs hover:underline">
            <i class="fab fa-whatsapp text-lg"></i>
            <span>Hubungi Admin Toko</span>
        </a>
    </div>
</div>

<?php require_once '../layout/footer.php'; ?>
