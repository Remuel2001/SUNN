<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

define('BASE_PATH', dirname(__DIR__));

// For InfinityFree at root domain: ''
// For XAMPP: '/SUNN-faculty monitoring'
$base_url = '/SUNN-faculty monitoring';
define('BASE_URL', $base_url);
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('FACE_UPLOAD_PATH', UPLOAD_PATH . '/faces');
define('TEMP_PATH', UPLOAD_PATH . '/temp');
define('REPORT_PATH', BASE_PATH . '/reports');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
