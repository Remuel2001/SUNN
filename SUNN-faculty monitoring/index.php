<?php
require_once __DIR__ . '/config/config.php';
redirectIfNotLoggedIn();

$role = $_SESSION['user_role'];
$redirect = match($role) {
    'admin' => '/admin/dashboard.php',
    'instructor' => '/instructor/dashboard.php',
    'student' => '/student/dashboard.php',
    'department_head' => '/department_head/dashboard.php',
    default => '/login.php'
};
header('Location: ' . BASE_URL . $redirect);
exit;
