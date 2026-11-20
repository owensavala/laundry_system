<?php
// api/index.php - Vercel Serverless Entrypoint & Front Controller
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = urldecode($path);

// Normalize path
if ($path === '/' || $path === '') {
    $target = __DIR__ . '/../index.php';
} else {
    $target = __DIR__ . '/..' . $path;
}

// If target is directory, look for index.php inside it
if (is_dir($target)) {
    $target = rtrim($target, '/') . '/index.php';
}

// If it's a PHP file
if (file_exists($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    chdir(dirname($target));
    $_SERVER['SCRIPT_FILENAME'] = realpath($target);
    $_SERVER['PHP_SELF'] = $path;
    require $target;
    exit;
}

// If it's a static file
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

// Fallback to index.php
chdir(__DIR__ . '/..');
require __DIR__ . '/../index.php';
