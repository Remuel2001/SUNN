<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireApiAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'mark_read':
        $id = $_POST['id'] ?? $_GET['id'] ?? 0;
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_read':
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        break;

    case 'count':
        echo json_encode(['success' => true, 'count' => getNotificationCount()]);
        break;

    case 'list':
        $notifications = getUnreadNotifications();
        echo json_encode(['success' => true, 'data' => $notifications]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
