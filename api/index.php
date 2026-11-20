<?php
// api/index.php - Vercel Serverless Entrypoint & Front Controller
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = urldecode($path);

// If request is root, load root index.php
if ($path === '/' || $path === '') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/..' . $path;

// If target is directory, check for index.php inside it
if (is_dir($target)) {
    $target_index = rtrim($target, '/') . '/index.php';
    if (file_exists($target_index)) {
        require $target_index;
        exit;
    }
}

// If target is a PHP file that exists
if (file_exists($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    require $target;
    exit;
}

// If not found or static asset fallback
if (file_exists($target) && !is_dir($target)) {
    $mime_types = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    if (isset($mime_types[$ext])) {
        header('Content-Type: ' . $mime_types[$ext]);
    }
    readfile($target);
    exit;
}

// Fallback to main index.php
require __DIR__ . '/../index.php';
