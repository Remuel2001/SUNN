<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$base = BASE_URL;
echo json_encode([
    "name" => "SUNN Faculty Monitoring",
    "short_name" => "SUNN",
    "start_url" => $base . "/login.php",
    "display" => "standalone",
    "background_color" => "#4f46e5",
    "theme_color" => "#4f46e5",
    "orientation" => "portrait",
    "icons" => [
        ["src" => $base . "/uploads/brand/icon-192.png", "sizes" => "192x192", "type" => "image/png"],
        ["src" => $base . "/uploads/brand/icon-512.png", "sizes" => "512x512", "type" => "image/png"]
    ]
]);
