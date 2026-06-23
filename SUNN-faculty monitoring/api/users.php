<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireApiAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_permissions':
        $user_id = (int)($_GET['user_id'] ?? 0);
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Missing user_id']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'permissions' => getUserPermissionKeys($user_id)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
