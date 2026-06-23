<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireApiAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

switch ($action) {
    case 'send':
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        $receiver_id = $_POST['receiver_id'] ?? null;
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if (empty($subject) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
            exit;
        }

        if (!$receiver_id) {
            if ($user_role === 'instructor' || $user_role === 'student' || $user_role === 'department_head') {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM users WHERE role='admin' AND status='active' LIMIT 1");
                $stmt->execute();
                $admin = $stmt->fetch();
                $receiver_id = $admin ? $admin['id'] : null;
            }
            if (!$receiver_id) {
                echo json_encode(['success' => false, 'message' => 'No admin available']);
                exit;
            }
        }

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, parent_id) VALUES (?,?,?,?,?)");
        $stmt->execute([$user_id, $receiver_id, $subject, $message, $parent_id]);

        $sender = getUser($user_id);
        $notif_msg = "New message from {$sender['first_name']} {$sender['last_name']}: $subject";
        sendNotification($receiver_id, 'New Message', $notif_msg, 'info', BASE_URL . '/chat.php');

        logActivity('Send Message', "Message sent: $subject");
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        break;

    case 'inbox':
        $db = getDB();
        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role as sender_role,
                   (SELECT COUNT(*) FROM messages WHERE parent_id=m.id AND is_read=0) as replies_unread
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ? AND m.parent_id IS NULL
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $messages = $stmt->fetchAll();

        foreach ($messages as &$msg) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE parent_id=? AND is_read=0");
            $stmt->execute([$msg['id']]);
            $msg['unread_replies'] = $stmt->fetchColumn();
        }
        echo json_encode(['success' => true, 'data' => $messages]);
        break;

    case 'sent':
        $db = getDB();
        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role as receiver_role
            FROM messages m
            LEFT JOIN users u ON m.receiver_id = u.id
            WHERE m.sender_id = ? AND m.parent_id IS NULL
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'thread':
        $message_id = (int)($_GET['id'] ?? 0);
        if (!$message_id) {
            echo json_encode(['success' => false, 'message' => 'Message ID required']);
            exit;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT m.*, u.first_name, u.last_name, u.role FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
        $stmt->execute([$message_id]);
        $parent = $stmt->fetch();
        if (!$parent) {
            echo json_encode(['success' => false, 'message' => 'Message not found']);
            exit;
        }

        $stmt = $db->prepare("UPDATE messages SET is_read=1 WHERE id=? AND receiver_id=?");
        $stmt->execute([$message_id, $user_id]);

        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role FROM messages m
            JOIN users u ON m.sender_id=u.id
            WHERE m.parent_id=? OR m.id=?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$message_id, $message_id]);
        $thread = $stmt->fetchAll();

        echo json_encode(['success' => true, 'parent' => $parent, 'thread' => $thread]);
        break;

    case 'unread_count':
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM messages
            WHERE receiver_id=? AND is_read=0 AND parent_id IS NULL
        ");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'count' => (int)$stmt->fetchColumn()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
