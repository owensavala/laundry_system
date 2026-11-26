<?php
// config/migrate.php - Auto-migration and database synchronization based on PRD

function run_migrations($conn) {
    // Performance optimization: Check if schema version is already up-to-date (Version 5)
    // Avoid running heavy DDL queries on every request.
    $target_version = 5;
    $ver_check = @mysqli_query($conn, "SELECT schema_version FROM settings WHERE id=1 LIMIT 1");
    if ($ver_check && mysqli_num_rows($ver_check) > 0) {
        $row_ver = mysqli_fetch_assoc($ver_check);
        if (isset($row_ver['schema_version']) && (int)$row_ver['schema_version'] >= $target_version) {
            return; // Fast path! Schema is fully up-to-date, 0 DDL overhead.
        }
    }

    // 1. Settings Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT PRIMARY KEY,
        `nama_laundry` VARCHAR(150) NOT NULL DEFAULT 'Clean&Fresh Laundry',
        `alamat` TEXT NULL,
        `whatsapp` VARCHAR(30) NULL,
        `logo` VARCHAR(255) NULL,
        `wa_gateway_provider` VARCHAR(50) DEFAULT 'manual',
        `wa_api_key` VARCHAR(255) NULL,
        `wa_domain` VARCHAR(255) NULL DEFAULT 'https://api.fonnte.com',
        `wa_template_order` TEXT NULL,
        `wa_template_ready` TEXT NULL,
        `receipt_footer` TEXT NULL,
        `schema_version` INT DEFAULT 1,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $set_cols = [];
    $qs = mysqli_query($conn, "SHOW COLUMNS FROM settings");
    if ($qs) {
        while ($r = mysqli_fetch_assoc($qs)) {
            $set_cols[] = $r['Field'];
        }
    }
    if (!in_array('wa_domain', $set_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN wa_domain VARCHAR(255) NULL DEFAULT 'https://api.fonnte.com' AFTER wa_api_key");
    }
    if (!in_array('receipt_footer', $set_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN receipt_footer TEXT NULL AFTER wa_template_ready");
    }
    if (!in_array('schema_version', $set_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN schema_version INT DEFAULT 1 AFTER receipt_footer");
    }

    $cek_set = mysqli_query($conn, "SELECT id FROM settings WHERE id=1");
    if ($cek_set && mysqli_num_rows($cek_set) == 0) {
        $old_nama = "Clean&Fresh Laundry";
        $old_alamat = "Jl. Sudirman No. 123, Jakarta";
        $old_wa = "628123456789";
        
        $cek_old = mysqli_query($conn, "SHOW TABLES LIKE 'pengaturan'");
        if ($cek_old && mysqli_num_rows($cek_old) > 0) {
            $old_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pengaturan LIMIT 1"));
            if ($old_row) {
                $old_nama = $old_row['nama_laundry'] ?? $old_nama;
                $old_alamat = $old_row['alamat'] ?? $old_alamat;
                $old_wa = $old_row['whatsapp'] ?? $old_wa;
            }
        }
        $tpl_order = "Halo {nama}, pesanan {no_nota} sedang diproses. Cek status cucian Anda di {link_tracking}";
        $tpl_ready = "Halo {nama}, cucian Anda dengan no nota {no_nota} sudah selesai dan siap diambil!";
        
        $stmt = mysqli_prepare($conn, "INSERT INTO settings (id, nama_laundry, alamat, whatsapp, wa_gateway_provider, wa_template_order, wa_template_ready) VALUES (1, ?, ?, ?, 'manual', ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", $old_nama, $old_alamat, $old_wa, $tpl_order, $tpl_ready);
            mysqli_stmt_execute($stmt);
        }
    }

    // 2. Users Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `username` VARCHAR(100) NULL,
        `password` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `role` ENUM('superadmin', 'admin', 'customer') NOT NULL DEFAULT 'admin',
        `address` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add columns if table already existed without them
    $user_cols = [];
    $qc = mysqli_query($conn, "SHOW COLUMNS FROM users");
    if ($qc) {
        while ($r = mysqli_fetch_assoc($qc)) {
            $user_cols[] = $r['Field'];
        }
    }

    if (in_array('id_user', $user_cols) && !in_array('id', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users CHANGE id_user id INT AUTO_INCREMENT");
        $user_cols[] = 'id';
    }
    if (!in_array('name', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN name VARCHAR(150) NOT NULL AFTER id");
        mysqli_query($conn, "UPDATE users SET name=username WHERE name='' OR name IS NULL");
    }
    if (!in_array('email', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER name");
        mysqli_query($conn, "UPDATE users SET email=CONCAT(username, '@laundry.local') WHERE email IS NULL OR email=''");
    }
    if (!in_array('phone', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER password");
    }
    if (!in_array('address', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN address TEXT NULL AFTER role");
    }
    if (!in_array('created_at', $user_cols)) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    // Ensure role column accepts 'superadmin' and 'admin'
    mysqli_query($conn, "ALTER TABLE users MODIFY COLUMN role ENUM('superadmin', 'admin', 'customer') NOT NULL DEFAULT 'admin'");
    mysqli_query($conn, "UPDATE users SET role='admin' WHERE role='kasir'");

    // GUARANTEED SUPERADMIN ACCOUNT
    // Check if user 'superadmin' exists
    $cek_super_uname = mysqli_query($conn, "SELECT id, password, role FROM users WHERE username='superadmin' LIMIT 1");
    $pw_super = password_hash("admin123", PASSWORD_DEFAULT);
    if ($cek_super_uname && mysqli_num_rows($cek_super_uname) > 0) {
        $row_super = mysqli_fetch_assoc($cek_super_uname);
        $sid = $row_super['id'];
        mysqli_query($conn, "UPDATE users SET role='superadmin', password='$pw_super' WHERE id='$sid'");
    } else {
        mysqli_query($conn, "INSERT INTO users (name, email, username, password, phone, role, address) 
            VALUES ('Owner Laundry', 'owner@laundry.com', 'superadmin', '$pw_super', '081234567890', 'superadmin', 'Kantor Pusat')");
    }

    // GUARANTEED ADMIN (KASIR) ACCOUNT
    $cek_admin_uname = mysqli_query($conn, "SELECT id, password, role FROM users WHERE username='admin' LIMIT 1");
    $pw_admin = password_hash("kasir123", PASSWORD_DEFAULT);
    if ($cek_admin_uname && mysqli_num_rows($cek_admin_uname) > 0) {
        $row_admin = mysqli_fetch_assoc($cek_admin_uname);
        $aid = $row_admin['id'];
        // Ensure its role is admin
        mysqli_query($conn, "UPDATE users SET role='admin', password='$pw_admin' WHERE id='$aid'");
    } else {
        mysqli_query($conn, "INSERT INTO users (name, email, username, password, phone, role, address) 
            VALUES ('Kasir Utama', 'kasir@laundry.com', 'admin', '$pw_admin', '081298765432', 'admin', 'Outlet 1')");
    }

    // 3. Services Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `services` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `service_name` VARCHAR(150) NOT NULL,
        `service_type` ENUM('kiloan', 'satuan') NOT NULL DEFAULT 'kiloan',
        `price` DECIMAL(12,2) NOT NULL,
        `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
        `duration_hours` INT NOT NULL DEFAULT 48,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Migrate from legacy 'layanan' table if services is empty
    $cek_svc = mysqli_query($conn, "SELECT id FROM services LIMIT 1");
    if ($cek_svc && mysqli_num_rows($cek_svc) == 0) {
        $cek_lay = mysqli_query($conn, "SHOW TABLES LIKE 'layanan'");
        if ($cek_lay && mysqli_num_rows($cek_lay) > 0) {
            $ql = mysqli_query($conn, "SELECT * FROM layanan");
            while ($row = mysqli_fetch_assoc($ql)) {
                $sname = mysqli_real_escape_string($conn, $row['nama_layanan']);
                $sprice = (float)$row['harga'];
                mysqli_query($conn, "INSERT INTO services (service_name, service_type, price, unit, duration_hours) 
                    VALUES ('$sname', 'kiloan', '$sprice', 'kg', 48)");
            }
        }
        $cek_svc2 = mysqli_query($conn, "SELECT id FROM services LIMIT 1");
        if ($cek_svc2 && mysqli_num_rows($cek_svc2) == 0) {
            mysqli_query($conn, "INSERT INTO services (service_name, service_type, price, unit, duration_hours) VALUES
                ('Cuci Kering Kiloan (Reguler 2 Hari)', 'kiloan', 7000, 'kg', 48),
                ('Cuci Setrika Kiloan (Express 1 Hari)', 'kiloan', 12000, 'kg', 24),
                ('Setrika Saja (Reguler)', 'kiloan', 5000, 'kg', 24),
                ('Cuci Bed Cover (Satuan)', 'satuan', 25000, 'pcs', 72),
                ('Cuci Jas / Blazer (Satuan)', 'satuan', 35000, 'pcs', 48),
                ('Cuci Karpet (Satuan)', 'satuan', 45000, 'pcs', 72)
            ");
        }
    }

    // 4. Orders Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_code` VARCHAR(50) NOT NULL UNIQUE,
        `customer_id` INT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(30) NULL,
        `admin_id` INT NULL,
        `status` ENUM('pending', 'processing', 'ironing', 'ready', 'completed') NOT NULL DEFAULT 'pending',
        `payment_status` ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
        `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Tunai',
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `notes` TEXT NULL,
        `estimate_completed_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`order_code`),
        INDEX (`customer_id`),
        INDEX (`status`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check columns of orders
    $order_cols = [];
    $qo = mysqli_query($conn, "SHOW COLUMNS FROM orders");
    if ($qo) {
        while ($r = mysqli_fetch_assoc($qo)) {
            $order_cols[] = $r['Field'];
        }
    }
    if (!in_array('estimate_completed_at', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN estimate_completed_at DATETIME NULL AFTER notes");
    }
    if (!in_array('processing_at', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN processing_at DATETIME NULL AFTER estimate_completed_at");
    }
    if (!in_array('ironing_at', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN ironing_at DATETIME NULL AFTER processing_at");
    }
    if (!in_array('ready_at', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN ready_at DATETIME NULL AFTER ironing_at");
    }
    if (!in_array('discount_amount', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(12,2) DEFAULT 0 AFTER total_amount");
    }
    if (!in_array('cash_paid', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN cash_paid DECIMAL(12,2) DEFAULT 0 AFTER discount_amount");
    }
    if (!in_array('cash_change', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN cash_change DECIMAL(12,2) DEFAULT 0 AFTER cash_paid");
    }
    if (!in_array('pickup_id', $order_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN pickup_id INT NULL AFTER customer_id");
    }

    // 5. Order Details Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `order_details` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `service_id` INT NOT NULL,
        `qty` FLOAT NOT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL,
        INDEX (`order_id`),
        INDEX (`service_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Pickup Requests Table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pickup_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT NULL,
        `order_id` INT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(30) NOT NULL,
        `pickup_address` TEXT NOT NULL,
        `notes` TEXT NULL,
        `status` ENUM('pending', 'confirmed', 'picked_up', 'cancelled') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pick_cols = [];
    $qpc = mysqli_query($conn, "SHOW COLUMNS FROM pickup_requests");
    if ($qpc) {
        while ($r = mysqli_fetch_assoc($qpc)) {
            $pick_cols[] = $r['Field'];
        }
    }
    if (!in_array('order_id', $pick_cols)) {
        mysqli_query($conn, "ALTER TABLE pickup_requests ADD COLUMN order_id INT NULL AFTER customer_id");
    }

    // Seed sample pickup requests if empty so the admin page is not blank
    $cek_pick = mysqli_query($conn, "SELECT id FROM pickup_requests LIMIT 1");
    if ($cek_pick && mysqli_num_rows($cek_pick) == 0) {
        mysqli_query($conn, "INSERT INTO pickup_requests (customer_name, customer_phone, pickup_address, notes, status, created_at) VALUES 
            ('Ibu Rahmawati', '081234567890', 'Jl. Melati No. 42, RT 01/RW 03 (Pagar Hitam)', '2 kantong pakaian campur + 1 sprei', 'pending', NOW()),
            ('Pak Hendra', '085712345678', 'Komplek Griya Asri Blok B No. 7', 'Mohon jemput sore sekitar jam 16.00', 'confirmed', DATE_SUB(NOW(), INTERVAL 2 HOUR))
        ");
    }

    // 7. Pengeluaran Table - ENSURE columns exist (FIX FOR FATAL ERROR)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `pengeluaran` (
        `id_pengeluaran` INT AUTO_INCREMENT PRIMARY KEY,
        `keterangan` VARCHAR(255) NOT NULL,
        `jumlah_biaya` DECIMAL(12,2) NOT NULL,
        `id_user` INT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $peng_cols = [];
    $qp = mysqli_query($conn, "SHOW COLUMNS FROM pengeluaran");
    if ($qp) {
        while ($r = mysqli_fetch_assoc($qp)) {
            $peng_cols[] = $r['Field'];
        }
    }
    if (!in_array('created_at', $peng_cols)) {
        mysqli_query($conn, "ALTER TABLE pengeluaran ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
        // If there's an existing date column like tgl or tgl_pengeluaran, sync it
        if (in_array('tgl_pengeluaran', $peng_cols)) {
            mysqli_query($conn, "UPDATE pengeluaran SET created_at = tgl_pengeluaran WHERE created_at IS NULL");
        } elseif (in_array('tanggal', $peng_cols)) {
            mysqli_query($conn, "UPDATE pengeluaran SET created_at = tanggal WHERE created_at IS NULL");
        }
    }

    // 9. Performance Indexes (High-Throughput Optimization)
    $safe_add_index = function($table, $index_name, $cols) use ($conn) {
        $chk = @mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
        if ($chk && mysqli_num_rows($chk) == 0) {
            @mysqli_query($conn, "ALTER TABLE `$table` ADD INDEX `$index_name` ($cols)");
        }
    };

    $safe_add_index('orders', 'idx_orders_phone', 'customer_phone');
    $safe_add_index('orders', 'idx_orders_pay_created', 'payment_status, created_at');
    $safe_add_index('orders', 'idx_orders_status_created', 'status, created_at');
    $safe_add_index('orders', 'idx_orders_pickup', 'pickup_id');
    $safe_add_index('pickup_requests', 'idx_pickup_status_created', 'status, created_at');
    $safe_add_index('pickup_requests', 'idx_pickup_phone', 'customer_phone');
    $safe_add_index('pengeluaran', 'idx_pengeluaran_created', 'created_at');
    $safe_add_index('users', 'idx_users_role_phone', 'role, phone');
    $safe_add_index('order_details', 'idx_order_service', 'order_id, service_id');

    // 10. Schema Version 4 Enhancements: Modern POS & Tracking Attributes
    // Check columns of orders
    $qo4 = mysqli_query($conn, "SHOW COLUMNS FROM orders");
    $o4_cols = [];
    if ($qo4) {
        while ($r = mysqli_fetch_assoc($qo4)) {
            $o4_cols[] = $r['Field'];
        }
    }
    if (!in_array('perfume', $o4_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN perfume VARCHAR(50) DEFAULT 'Sakura Blossom' AFTER notes");
    }
    if (!in_array('speed', $o4_cols)) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN speed VARCHAR(20) DEFAULT 'regular' AFTER perfume");
    }

    // Check columns of order_details
    $qod4 = mysqli_query($conn, "SHOW COLUMNS FROM order_details");
    $od4_cols = [];
    if ($qod4) {
        while ($r = mysqli_fetch_assoc($qod4)) {
            $od4_cols[] = $r['Field'];
        }
    }
    if (!in_array('notes', $od4_cols)) {
        mysqli_query($conn, "ALTER TABLE order_details ADD COLUMN notes VARCHAR(255) NULL AFTER subtotal");
    }

    // Check columns of services
    $qs4 = mysqli_query($conn, "SHOW COLUMNS FROM services");
    $s4_cols = [];
    if ($qs4) {
        while ($r = mysqli_fetch_assoc($qs4)) {
            $s4_cols[] = $r['Field'];
        }
    }
    if (!in_array('icon', $s4_cols)) {
        mysqli_query($conn, "ALTER TABLE services ADD COLUMN icon VARCHAR(50) DEFAULT 'fa-shirt' AFTER unit");
    }
    if (!in_array('category', $s4_cols)) {
        mysqli_query($conn, "ALTER TABLE services ADD COLUMN category VARCHAR(50) DEFAULT 'Kiloan' AFTER icon");
    }

    // Update service icons and categories
    mysqli_query($conn, "UPDATE services SET icon='fa-basket-shopping', category='Kiloan' WHERE service_type='kiloan' AND service_name LIKE '%kiloan%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-bolt', category='Setrika' WHERE service_name LIKE '%setrika%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-bed', category='Bedcover' WHERE service_name LIKE '%bed%cover%' OR service_name LIKE '%seprey%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-user-tie', category='Satuan' WHERE service_name LIKE '%jas%' OR service_name LIKE '%blazer%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-rug', category='Karpet' WHERE service_name LIKE '%karpet%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-paw', category='Boneka' WHERE service_name LIKE '%boneka%'");
    mysqli_query($conn, "UPDATE services SET icon='fa-shirt' WHERE icon IS NULL OR icon='' OR icon='fa-teddy-bear'");
    mysqli_query($conn, "UPDATE services SET category=CASE WHEN service_type='kiloan' THEN 'Kiloan' ELSE 'Satuan' END WHERE category IS NULL OR category=''");

    // Mark migration version 4 as complete
    mysqli_query($conn, "UPDATE settings SET schema_version = 4 WHERE id = 1");

    // --- MIGRATION VERSION 5: Laundry Ar-Roniyah Branding, Pastel Theme & Custom Appearance ---
    $qs5 = mysqli_query($conn, "SHOW COLUMNS FROM settings");
    $s5_cols = [];
    if ($qs5) {
        while ($r = mysqli_fetch_assoc($qs5)) {
            $s5_cols[] = $r['Field'];
        }
    }
    if (!in_array('tagline', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN tagline VARCHAR(150) DEFAULT 'Solusi Laundry Anda' AFTER nama_laundry");
    }
    if (!in_array('instagram', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN instagram VARCHAR(100) DEFAULT '@laundry_ar-roniyah' AFTER whatsapp");
    }
    if (!in_array('logo_data', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN logo_data LONGTEXT NULL AFTER logo");
    }
    if (!in_array('logo_icon', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN logo_icon VARCHAR(50) DEFAULT 'fa-soap' AFTER logo_data");
    }
    if (!in_array('theme_primary', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN theme_primary VARCHAR(20) DEFAULT '#DB2777' AFTER logo_icon");
    }
    if (!in_array('theme_pastel', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN theme_pastel VARCHAR(20) DEFAULT '#FFCED8' AFTER theme_primary");
    }
    if (!in_array('theme_secondary', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN theme_secondary VARCHAR(20) DEFAULT '#FFE7B8' AFTER theme_pastel");
    }
    if (!in_array('theme_accent', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN theme_accent VARCHAR(20) DEFAULT '#701A45' AFTER theme_secondary");
    }
    if (!in_array('theme_bg', $s5_cols)) {
        mysqli_query($conn, "ALTER TABLE settings ADD COLUMN theme_bg VARCHAR(20) DEFAULT '#FFF9FA' AFTER theme_accent");
    }

    // Apply Laundry Ar-Roniyah info & pastel palette if upgrading from < v5
    $cur_v = isset($row_ver['schema_version']) ? (int)$row_ver['schema_version'] : 1;
    if ($cur_v < 5) {
        $stmt_brand = mysqli_prepare($conn, "UPDATE settings SET nama_laundry=?, tagline=?, alamat=?, whatsapp=?, instagram=?, theme_primary=?, theme_pastel=?, theme_secondary=?, theme_accent=?, theme_bg=?, schema_version=5 WHERE id=1");
        $b_nama = 'Laundry Ar-Roniyah';
        $b_tag  = 'Solusi Laundry Anda';
        $b_addr = 'Jln. Raya Pelabuhan Kupal';
        $b_wa   = '081253996394';
        $b_ig   = '@laundry_ar-roniyah';
        $b_p    = '#DB2777';
        $b_pas  = '#FFCED8';
        $b_sec  = '#FFE7B8';
        $b_acc  = '#701A45';
        $b_bg   = '#FFF9FA';
        if ($stmt_brand) {
            mysqli_stmt_bind_param($stmt_brand, "ssssssssss", $b_nama, $b_tag, $b_addr, $b_wa, $b_ig, $b_p, $b_pas, $b_sec, $b_acc, $b_bg);
            mysqli_stmt_execute($stmt_brand);
        }

        // Ensure all 7 services from the Ar-Roniyah banner exist cleanly
        $banner_services = [
            ['Cuci Setrika', 'kiloan', 8000, 'kg', 48, 'fa-shirt', 'Kiloan'],
            ['Cuci Lipat', 'kiloan', 7000, 'kg', 48, 'fa-box-archive', 'Kiloan'],
            ['Setrika Saja', 'kiloan', 5000, 'kg', 24, 'fa-bolt', 'Setrika'],
            ['Cuci Sepatu', 'satuan', 25000, 'pasang', 48, 'fa-shoe-prints', 'Satuan'],
            ['Cuci Gorden', 'satuan', 20000, 'pcs', 48, 'fa-rug', 'Satuan'],
            ['Cuci Seprai & Bed Cover', 'satuan', 25000, 'pcs', 72, 'fa-bed', 'Bedcover'],
            ['Cuci Express', 'kiloan', 14000, 'kg', 12, 'fa-gauge-high', 'Express'],
        ];
        foreach ($banner_services as $bs) {
            $name_esc = mysqli_real_escape_string($conn, $bs[0]);
            $chk = mysqli_query($conn, "SELECT id FROM services WHERE LOWER(service_name) = LOWER('$name_esc') LIMIT 1");
            if ($chk && mysqli_num_rows($chk) == 0) {
                $stmt_s = mysqli_prepare($conn, "INSERT INTO services (service_name, service_type, price, unit, duration_hours, icon, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt_s) {
                    mysqli_stmt_bind_param($stmt_s, "ssdsiss", $bs[0], $bs[1], $bs[2], $bs[3], $bs[4], $bs[5], $bs[6]);
                    mysqli_stmt_execute($stmt_s);
                }
            }
        }
        // Standardize naming for existing "cuci seprey" -> "Cuci Seprai & Bed Cover" if no duplicate
        mysqli_query($conn, "UPDATE services SET icon='fa-bed', category='Bedcover' WHERE service_name LIKE '%seprai%' OR service_name LIKE '%seprey%' OR service_name LIKE '%bed%cover%'");
        mysqli_query($conn, "UPDATE services SET icon='fa-shoe-prints', category='Satuan' WHERE service_name LIKE '%sepatu%'");
        mysqli_query($conn, "UPDATE services SET icon='fa-rug', category='Satuan' WHERE service_name LIKE '%gorden%'");
        mysqli_query($conn, "UPDATE services SET icon='fa-gauge-high', category='Express' WHERE service_name LIKE '%express%'");
    } else {
        mysqli_query($conn, "UPDATE settings SET schema_version = 5 WHERE id = 1");
    }
}
