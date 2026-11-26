<?php
// manifest.php - Dynamic PWA Web App Manifest
header('Content-Type: application/manifest+json; charset=utf-8');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/theme.php';

$set = get_settings($conn);
$theme = get_brand_theme($set);

$icons = [];
if (!empty($theme['logo_data']) && strpos($theme['logo_data'], 'data:image/') === 0) {
    $icons[] = [
        'src' => $theme['logo_data'],
        'sizes' => '256x256',
        'type' => 'image/png',
        'purpose' => 'any maskable'
    ];
} else {
    // Clean inline SVG icon fallback using brand primary & accent colors
    $p = $theme['primary'];
    $a = $theme['accent'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="' . $p . '"/><stop offset="100%" stop-color="' . $a . '"/></linearGradient></defs><rect width="192" height="192" rx="44" fill="url(#g)"/><circle cx="96" cy="96" r="46" fill="none" stroke="#ffffff" stroke-width="12"/><circle cx="96" cy="96" r="20" fill="#FFE7B8"/></svg>';
    $icons[] = [
        'src' => 'data:image/svg+xml;base64,' . base64_encode($svg),
        'sizes' => '192x192',
        'type' => 'image/svg+xml',
        'purpose' => 'any maskable'
    ];
}

echo json_encode([
    'name' => $theme['nama_laundry'] . ' - ' . $theme['tagline'],
    'short_name' => $theme['nama_laundry'],
    'description' => $theme['tagline'] . ' - Lacak cucian real-time & layanan laundry terpercaya.',
    'start_url' => './index.php',
    'display' => 'standalone',
    'background_color' => $theme['bg'],
    'theme_color' => $theme['primary'],
    'orientation' => 'portrait-primary',
    'icons' => $icons
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
