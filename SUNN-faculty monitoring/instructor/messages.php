<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor', 'student', 'department_head');
header('Location: ' . BASE_URL . '/chat.php');
exit;
