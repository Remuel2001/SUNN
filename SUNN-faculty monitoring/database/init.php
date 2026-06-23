<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
    $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $lines = explode("\n", $sql);
    $statement = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) continue;
        $statement .= "\n" . $trimmed;
        if (strpos($trimmed, ';') !== false && substr(rtrim($trimmed), -1) === ';') {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                    echo "<div class='alert alert-warning py-1 small'>" . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            $statement = '';
        }
    }

    $dirs = [
        __DIR__ . '/../uploads',
        __DIR__ . '/../uploads/faces',
        __DIR__ . '/../uploads/faces/evidence',
        __DIR__ . '/../uploads/profiles',
        __DIR__ . '/../uploads/brand',
        __DIR__ . '/../uploads/temp',
        __DIR__ . '/../reports'
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    header('Location: ' . BASE_URL . '/login.php?setup=success');
    exit;
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage());
}
