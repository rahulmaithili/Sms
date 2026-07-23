<?php
/**
 * Developed by Rameez Scripts
 * PWA Manifest — dynamic branding
 */
require_once 'config.php';
$branding = getSiteBranding();
$name = $branding['site_name'];
$logo = $branding['site_logo'];

header('Content-Type: application/manifest+json');
echo json_encode([
    'name' => $name,
    'short_name' => mb_substr($name, 0, 12),
    'description' => $name . ' — Subscription Management System',
    'start_url' => './dashboard.php',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#001f3f',
    'theme_color' => '#001f3f',
    'icons' => [
        ['src' => $logo, 'sizes' => '160x160', 'type' => 'image/png', 'purpose' => 'any maskable']
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
