<?php
// admin/cetak_nota.php - Professional Thermal Receipt Printer (80mm / 58mm)
require_once '../config/database.php';
require_once '../includes/whatsapp.php';

$id = (int)($_GET['id'] ?? 0);

$q_order = mysqli_query($conn, "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.admin_id = u.id 
    WHERE o.id = '$id'");
$order = mysqli_fetch_assoc($q_order);

if (!$order) {
    die("Data pesanan tidak ditemukan.");
}

$set = get_settings($conn);

// Fetch order items
$q_items = mysqli_query($conn, "SELECT od.*, s.service_name, s.unit, s.price as unit_price 
    FROM order_details od 
    LEFT JOIN services s ON od.service_id = s.id 
    WHERE od.order_id = '$id'");

$tracking_url = base_url("index.php?kode=" . $order['order_code']);

$wa_message = build_wa_message($set['wa_template_order'], [
    'customer_name' => $order['customer_name'],
    'order_code' => $order['order_code'],
    'total_amount' => $order['total_amount'],
    'link_tracking' => $tracking_url,
    'laundry_name' => $set['nama_laundry']
]);
$wa_url = get_wa_direct_link($order['customer_phone'], $wa_message);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #<?= htmlspecialchars($order['order_code']); ?> - <?= htmlspecialchars($set['nama_laundry']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @page {
            margin: 0;
            size: auto;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f1f5f9;
            color: #000;
        }
        .receipt-card {
            width: 80mm;
            max-width: 100%;
            margin: 20px auto;
            background: #fff;
            padding: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
            transition: width 0.2s ease;
        }
        .receipt-58mm {
            width: 58mm !important;
            padding: 10px !important;
            font-size: 10px !important;
        }
        @media print {
            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-card {
                width: 100% !important;
                margin: 0 !important;
                padding: 4mm !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
        }
        .dashed-line {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .double-line {
            border-top: 2px solid #000;
            margin: 8px 0;
        }
    </style>
</head>
<body>

    <!-- Control Buttons (No-Print) -->
    <div class="no-print max-w-lg mx-auto p-4 text-center space-y-2">
        <div class="flex flex-wrap gap-2 justify-center">
            <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-md hover:bg-indigo-700 transition flex items-center gap-1.5">
                <i class="fas fa-print"></i> Cetak Struk
            </button>
            <button type="button" onclick="toggleReceiptWidth('80mm')" id="btn80" class="px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition">
                Lebar 80mm
            </button>
            <button type="button" onclick="toggleReceiptWidth('58mm')" id="btn58" class="px-3 py-2 bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-bold transition">
                Lebar 58mm
            </button>
            <?php if (!empty($order['customer_phone'])): ?>
                <a href="<?= $wa_url; ?>" target="_blank" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-md hover:bg-emerald-700 transition flex items-center gap-1.5">
                    <i class="fab fa-whatsapp"></i> Kirim WA
                </a>
            <?php endif; ?>
            <a href="pos.php" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-300 transition">
                &larr; POS
            </a>
        </div>
        <p class="text-[11px] text-slate-400">Pilih lebar kertas printer kasir Anda (80mm atau 58mm) lalu tekan Cetak.</p>
    </div>

    <!-- Thermal Receipt Card -->
    <div id="receiptContainer" class="receipt-card text-xs">
        
        <!-- Header -->
        <div class="text-center">
            <?php if (!empty($set['logo_data'])): ?>
                <img src="<?= htmlspecialchars($set['logo_data']); ?>" alt="Logo" class="w-12 h-12 object-contain mx-auto mb-1 rounded-lg grayscale">
            <?php endif; ?>
            <h2 class="text-base font-black uppercase tracking-wider"><?= htmlspecialchars($set['nama_laundry']); ?></h2>
            <?php if (!empty($set['tagline'])): ?>
                <p class="text-[10px] font-bold uppercase tracking-wide"><?= htmlspecialchars($set['tagline']); ?></p>
            <?php endif; ?>
            <p class="text-[11px] leading-tight mt-0.5"><?= htmlspecialchars($set['alamat']); ?></p>
            <p class="text-[11px]">WA: <?= htmlspecialchars($set['whatsapp']); ?><?= !empty($set['instagram']) ? ' | IG: ' . htmlspecialchars($set['instagram']) : ''; ?></p>
        </div>

        <div class="dashed-line"></div>

        <!-- Meta Info -->
        <table class="w-full text-[11px]">
            <tr>
                <td class="text-slate-600">No. Nota</td>
                <td class="text-right font-black"><?= htmlspecialchars($order['order_code']); ?></td>
            </tr>
            <tr>
                <td class="text-slate-600">Tanggal</td>
                <td class="text-right"><?= date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
            </tr>
            <tr>
                <td class="text-slate-600">Pelanggan</td>
                <td class="text-right font-bold"><?= htmlspecialchars($order['customer_name']); ?></td>
            </tr>
            <?php if (!empty($order['customer_phone'])): ?>
            <tr>
                <td class="text-slate-600">No. HP</td>
                <td class="text-right"><?= htmlspecialchars($order['customer_phone']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="text-slate-600">Kasir</td>
                <td class="text-right"><?= htmlspecialchars($order['cashier_name'] ?? 'Kasir'); ?></td>
            </tr>
        </table>

        <div class="dashed-line"></div>

        <!-- Items Table -->
        <table class="w-full text-[11px] mb-1">
            <thead>
                <tr class="font-bold border-b border-black">
                    <th class="text-left pb-1">Layanan</th>
                    <th class="text-center pb-1">Qty</th>
                    <th class="text-right pb-1">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($it = mysqli_fetch_assoc($q_items)): ?>
                <tr>
                    <td class="py-1">
                        <span class="font-bold block"><?= htmlspecialchars($it['service_name'] ?? 'Layanan'); ?></span>
                        <span class="text-[10px] text-slate-500">@ Rp <?= number_format($it['subtotal'] / ($it['qty'] ?: 1), 0, ',', '.'); ?>/<?= $it['unit']; ?></span>
                    </td>
                    <td class="text-center py-1 font-bold"><?= $it['qty']; ?> <?= $it['unit']; ?></td>
                    <td class="text-right py-1 font-bold font-mono">Rp <?= number_format($it['subtotal'], 0, ',', '.'); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="dashed-line"></div>

        <!-- Total and Payment Info -->
        <table class="w-full text-xs">
            <?php 
            $discount = (float)($order['discount_amount'] ?? 0);
            $subtotal_before_discount = (float)$order['total_amount'] + $discount;
            if ($discount > 0): 
            ?>
            <tr>
                <td class="text-slate-600 text-[11px]">Subtotal</td>
                <td class="text-right font-mono text-[11px]">Rp <?= number_format($subtotal_before_discount, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-emerald-700 text-[11px]">Diskon</td>
                <td class="text-right font-mono text-emerald-700 text-[11px]">- Rp <?= number_format($discount, 0, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="font-black text-sm">
                <td>TOTAL</td>
                <td class="text-right font-mono">Rp <?= number_format($order['total_amount'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="pt-1 text-[11px] text-slate-600">Status Bayar</td>
                <td class="pt-1 text-right font-bold text-[11px]">
                    <?= $order['payment_status'] === 'paid' ? 'LUNAS' : 'BELUM LUNAS'; ?>
                </td>
            </tr>
            <tr>
                <td class="text-[11px] text-slate-600">Metode Bayar</td>
                <td class="text-right text-[11px]"><?= htmlspecialchars($order['payment_method']); ?></td>
            </tr>
            <?php if (!empty($order['cash_paid']) && (float)$order['cash_paid'] > 0): ?>
            <tr>
                <td class="text-[11px] text-slate-600">Tunai Diterima</td>
                <td class="text-right font-mono text-[11px]">Rp <?= number_format($order['cash_paid'], 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-[11px] text-slate-600">Kembalian</td>
                <td class="text-right font-mono font-bold text-[11px]">Rp <?= number_format($order['cash_change'] ?? 0, 0, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($order['estimate_completed_at'])): ?>
            <tr>
                <td class="pt-1 text-[10px] text-slate-500">Estimasi Selesai</td>
                <td class="pt-1 text-right text-[10px] font-bold"><?= date('d/m/Y H:i', strtotime($order['estimate_completed_at'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if (!empty($order['notes'])): ?>
            <div class="mt-2 pt-2 border-t border-dashed border-slate-300 text-[10px]">
                <strong>Catatan:</strong> <?= htmlspecialchars($order['notes']); ?>
            </div>
        <?php endif; ?>

        <div class="double-line"></div>

        <!-- QR Code for Direct Mobile Tracking -->
        <div class="text-center my-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Scan Tracking Cepat</p>
            <div id="qrcode" class="flex justify-center items-center mx-auto p-1.5 bg-white border border-slate-200 inline-block rounded"></div>
            <p class="text-[9px] text-slate-500 mt-1">Arahkan kamera smartphone ke kode di atas</p>
        </div>

        <div class="dashed-line"></div>

        <!-- Footer Notice -->
        <div class="text-center text-[10px] space-y-1">
            <p class="font-bold">Terima kasih atas kepercayaan Anda!</p>
            <p class="text-[9px] text-slate-500 break-all"><?= $tracking_url; ?></p>
            <?php if (!empty($set['receipt_footer'])): ?>
                <p class="text-[9px] text-slate-500 mt-2 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($set['receipt_footer']); ?></p>
            <?php else: ?>
                <p class="text-[9px] text-slate-400 mt-2 leading-tight">Syarat & Ketentuan: Barang yang tidak diambil dalam 30 hari di luar tanggung jawab pihak laundry. Kerusakan pakaian akibat luntur bawaan bahan bukan kelalaian kami.</p>
            <?php endif; ?>
        </div>

    </div>

    <script>
        function toggleReceiptWidth(width) {
            const container = document.getElementById('receiptContainer');
            const btn80 = document.getElementById('btn80');
            const btn58 = document.getElementById('btn58');
            if (width === '58mm') {
                container.classList.add('receipt-58mm');
                btn58.className = 'px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition';
                btn80.className = 'px-3 py-2 bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-bold transition';
            } else {
                container.classList.remove('receipt-58mm');
                btn80.className = 'px-3 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition';
                btn58.className = 'px-3 py-2 bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-bold transition';
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const qrTarget = document.getElementById("qrcode");
            if (qrTarget) {
                new QRCode(qrTarget, {
                    text: "<?= $tracking_url; ?>",
                    width: 96,
                    height: 96,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
    </script>

</body>
</html>
