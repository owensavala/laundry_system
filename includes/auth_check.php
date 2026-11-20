<?php
// includes/auth_check.php - Strict RBAC Middleware

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function check_auth($allowed_roles = []) {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: " . base_url('auth/login.php?error=unauthorized'));
        exit();
    }

    $current_role = $_SESSION['role'];

    if (!empty($allowed_roles) && !in_array($current_role, $allowed_roles)) {
        // Redirect according to their actual role to prevent privilege escalation
        if ($current_role === 'superadmin') {
            header("Location: " . base_url('superadmin/index.php?error=forbidden'));
        } elseif ($current_role === 'admin') {
            header("Location: " . base_url('admin/index.php?error=forbidden'));
        } elseif ($current_role === 'customer') {
            header("Location: " . base_url('customer/index.php?error=forbidden'));
        } else {
            header("Location: " . base_url('auth/login.php?error=forbidden'));
        }
        exit();
    }
}
?>
