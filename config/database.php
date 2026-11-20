<?php
// config/database.php
// Disable fatal exception throwing on connection failure to allow fallback handling
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$pass = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? '')));
$db   = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: ($_ENV['DB_NAME'] ?? 'db_laundry'));
$port = (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306));

// Attempt connection
$conn = @mysqli_connect($host, $user, $pass, $db, $port);
if (!$conn && ($host === '127.0.0.1' || $host === 'localhost')) {
    $conn = @mysqli_connect("localhost", $user, $pass, $db, $port);
}

if (!$conn) {
    // If on localhost and database doesn't exist, try connecting without db and create it
    if ($host === '127.0.0.1' || $host === 'localhost') {
        $conn_init = @mysqli_connect($host, $user, $pass, null, $port) ?: @mysqli_connect("localhost", $user, $pass, null, $port);
        if ($conn_init) {
            mysqli_query($conn_init, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            mysqli_close($conn_init);
            $conn = @mysqli_connect($host, $user, $pass, $db, $port) ?: @mysqli_connect("localhost", $user, $pass, $db, $port);
        }
    }
    
    if (!$conn) {
        $err = mysqli_connect_error();
        $is_cloud = (getenv('VERCEL') || getenv('VERCEL_ENV') || getenv('AWS_REGION') || !empty($_ENV['VERCEL']));
        
        if ($is_cloud) {
            die("<div style='font-family:system-ui,sans-serif;padding:32px;background:#f8fafc;color:#1e293b;max-width:680px;margin:40px auto;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 10px 25px -5px rgba(0,0,0,0.05);'>
                <div style='background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;font-weight:700;margin-bottom:16px;'>⚠️ Database Cloud Belum Dikonfigurasi di Vercel</div>
                <h2 style='margin:0 0 12px;font-size:20px;color:#0f172a;'>Koneksi ke Database MySQL Gagal</h2>
                <p style='color:#64748b;font-size:14px;line-height:1.6;'>Aplikasi ini di-hosting di serverless cloud Vercel. Agar aplikasi dapat menyimpan data transaksi, tambahkan <strong>Environment Variables</strong> di dashboard Vercel Anda (Project Settings → Environment Variables):</p>
                <div style='background:#0f172a;color:#38bdf8;padding:16px;border-radius:12px;font-family:monospace;font-size:13px;line-height:1.8;margin:16px 0;'>
                    DB_HOST = nama-host-mysql.com<br>
                    DB_PORT = 3306<br>
                    DB_USER = username_db<br>
                    DB_PASS = password_db<br>
                    DB_NAME = db_laundry
                </div>
                <p style='color:#64748b;font-size:13px;'>Anda dapat menggunakan MySQL gratis dari <strong>Aiven, TiDB Cloud, PlanetScale, Railway, atau Clever-Cloud</strong>.</p>
                <div style='background:#f1f5f9;color:#475569;padding:10px 14px;border-radius:8px;font-size:12px;font-family:monospace;'>Detail Error: " . htmlspecialchars($err) . "</div>
            </div>");
        } else {
            die("<div style='font-family:sans-serif;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;max-width:600px;margin:50px auto;'>
                <h3 style='margin-top:0'>Koneksi Database Gagal</h3>
                <p>Pastikan MySQL di XAMPP Control Panel sudah dalam status <strong>RUNNING</strong>.</p>
                <p><small>" . htmlspecialchars($err) . "</small></p>
            </div>");
        }
    }
}

mysqli_set_charset($conn, "utf8mb4");

// Auto migration to ensure all tables from PRD exist
require_once __DIR__ . '/migrate.php';
run_migrations($conn);

// Helper function to get base url
if (!function_exists('base_url')) {
    function base_url($path = '') {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Find path relative to document root
        $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? 'c:/xampp/htdocs'));
        $app_root = str_replace('\\', '/', realpath(dirname(__DIR__)));
        $relative_dir = str_replace($doc_root, '', $app_root);
        $relative_dir = '/' . trim($relative_dir, '/');
        if ($relative_dir === '/') $relative_dir = '';
        
        $clean_path = ltrim($path, '/');
        return $protocol . $host . $relative_dir . ($clean_path ? '/' . $clean_path : '');
    }
}

// Helper function to get system settings
if (!function_exists('get_settings')) {
    function get_settings($conn) {
        $q = mysqli_query($conn, "SELECT * FROM settings WHERE id=1");
        if ($q && mysqli_num_rows($q) > 0) {
            return mysqli_fetch_assoc($q);
        }
        return [
            'nama_laundry' => 'Clean&Fresh Laundry',
            'alamat' => 'Jl. Laundry No. 123',
            'whatsapp' => '628123456789',
            'wa_gateway_provider' => 'manual',
            'wa_template_order' => 'Halo {nama}, pesanan {no_nota} sedang diproses. Cek status di {link_tracking}',
            'wa_template_ready' => 'Halo {nama}, cucian Anda dengan no nota {no_nota} sudah selesai dan siap diambil!'
        ];
    }
}
?>