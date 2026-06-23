<?php
require_once __DIR__ . '/../config/config.php';
logActivity('Logout', 'User logged out');
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
