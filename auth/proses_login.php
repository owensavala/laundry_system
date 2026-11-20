<?php
// auth/proses_login.php - High Reliability Staff Login Processor
session_start();
require_once __DIR__ . '/../config/database.php';

$identifier = trim($_POST['identifier'] ?? $_POST['username'] ?? '');
$password   = $_POST['password'] ?? '';

if (empty($identifier) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

// 1. Direct Fail-Safe for Superadmin (Owner)
if (strtolower($identifier) === 'superadmin' && $password === 'admin123') {
    // Ensure superadmin row exists in database
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='superadmin' LIMIT 1");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $row = mysqli_fetch_assoc($cek);
        $uid = $row['id'];
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET role='superadmin', password='$hash' WHERE id='$uid'");
    } else {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO users (name, email, username, password, phone, role, address) 
            VALUES ('Owner Laundry', 'owner@laundry.com', 'superadmin', '$hash', '081234567890', 'superadmin', 'Kantor Pusat')");
        $uid = mysqli_insert_id($conn);
        $row = [
            'id' => $uid,
            'name' => 'Owner Laundry',
            'username' => 'superadmin',
            'email' => 'owner@laundry.com',
            'phone' => '081234567890',
            'role' => 'superadmin'
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = $row['id'];
    $_SESSION['id_user']  = $row['id'];
    $_SESSION['name']     = $row['name'] ?: 'Superadmin (Owner)';
    $_SESSION['username'] = 'superadmin';
    $_SESSION['role']     = 'superadmin';
    $_SESSION['email']    = $row['email'] ?? 'owner@laundry.com';
    $_SESSION['phone']    = $row['phone'] ?? '081234567890';

    header("Location: ../superadmin/index.php");
    exit();
}

// 2. Direct Fail-Safe for Admin (Kasir)
if (strtolower($identifier) === 'admin' && ($password === 'kasir123' || $password === 'admin')) {
    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='admin' LIMIT 1");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $row = mysqli_fetch_assoc($cek);
        $uid = $row['id'];
        $hash = password_hash('kasir123', PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET role='admin', password='$hash' WHERE id='$uid'");
    } else {
        $hash = password_hash('kasir123', PASSWORD_DEFAULT);
        mysqli_query($conn, "INSERT INTO users (name, email, username, password, phone, role, address) 
            VALUES ('Kasir Utama', 'kasir@laundry.com', 'admin', '$hash', '081298765432', 'admin', 'Outlet 1')");
        $uid = mysqli_insert_id($conn);
        $row = [
            'id' => $uid,
            'name' => 'Kasir Utama',
            'username' => 'admin',
            'email' => 'kasir@laundry.com',
            'phone' => '081298765432',
            'role' => 'admin'
        ];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = $row['id'];
    $_SESSION['id_user']  = $row['id'];
    $_SESSION['name']     = $row['name'] ?: 'Kasir Utama';
    $_SESSION['username'] = 'admin';
    $_SESSION['role']     = 'admin';
    $_SESSION['email']    = $row['email'] ?? 'kasir@laundry.com';
    $_SESSION['phone']    = $row['phone'] ?? '081298765432';

    header("Location: ../admin/index.php");
    exit();
}

// 3. Database search for other employee accounts
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) OR phone = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "sss", $identifier, $identifier, $identifier);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $verified = false;
    
    // Check password hash
    if (password_verify($password, $row['password'])) {
        $verified = true;
    } elseif ($password === $row['password']) {
        // Plain text match
        $verified = true;
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $uid = $row['id'];
        mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id='$uid'");
    }

    if ($verified) {
        session_regenerate_id(true);
    $_SESSION['user_id']  = $row['id'];
        $_SESSION['id_user']  = $row['id'];
        $_SESSION['name']     = $row['name'] ?: $row['username'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        $_SESSION['email']    = $row['email'];
        $_SESSION['phone']    = $row['phone'];

        if ($row['role'] === 'superadmin') {
            header("Location: ../superadmin/index.php");
        } else {
            header("Location: ../admin/index.php");
        }
        exit();
    }
}

// Login failed
echo "<script>alert('Username/Email atau Password salah! Silakan gunakan tombol demo di halaman login.'); window.location='login.php';</script>";
exit();
?>