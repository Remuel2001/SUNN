<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
header('Location: ' . BASE_URL . '/chat.php');
exit;
