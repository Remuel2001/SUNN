<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');
requireApiAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Update last_activity on every request
$db = getDB();
$db->prepare("UPDATE users SET last_activity=NOW() WHERE id=?")->execute([$user_id]);

switch ($action) {
    case 'contacts':
        $where = '';
        if ($user_role === 'instructor' || $user_role === 'student' || $user_role === 'department_head') {
            $where = "AND (u.role='admin' OR u.role='instructor' OR u.role='department_head')";
        }
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.role, u.profile_image,
                   (u.last_activity IS NOT NULL AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) as online
            FROM users u WHERE u.status='active' AND u.id != ? $where
            ORDER BY u.first_name
        ");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'conversations':
        // Use positional params for native prepare compatibility
        $stmt = $db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.role, u.profile_image,
                (u.last_activity IS NOT NULL AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) as online,
                COALESCE((SELECT m.message FROM messages m WHERE (m.sender_id=? AND m.receiver_id=u.id) OR (m.sender_id=u.id AND m.receiver_id=?) ORDER BY m.created_at DESC LIMIT 1), '') as last_message,
                COALESCE((SELECT m.created_at FROM messages m WHERE (m.sender_id=? AND m.receiver_id=u.id) OR (m.sender_id=u.id AND m.receiver_id=?) ORDER BY m.created_at DESC LIMIT 1), '') as last_time,
                COALESCE((SELECT COUNT(*) FROM messages m WHERE m.sender_id=? AND m.receiver_id=u.id AND m.is_read=0), 0) as unread
            FROM users u
            WHERE u.id IN (
                SELECT DISTINCT CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END
                FROM messages m WHERE m.sender_id=? OR m.receiver_id=?
            )
            ORDER BY last_time DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'messages':
        $other_id = (int)($_GET['user_id'] ?? 0);
        if (!$other_id) { echo json_encode(['success' => false, 'message' => 'User ID required']); exit; }

        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role, u.profile_image
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $other_id, $other_id, $user_id]);

        // Mark messages as read AND seen
        $db->prepare("UPDATE messages SET is_read=1, seen_at=NOW() WHERE sender_id=? AND receiver_id=? AND seen_at IS NULL")->execute([$other_id, $user_id]);

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'send':
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$receiver_id || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Receiver and message required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?,?,'',?)");
        $stmt->execute([$user_id, $receiver_id, $message]);
        $msg_id = $db->lastInsertId();

        $sender = getUser($user_id);
        sendNotification($receiver_id, "Chat from {$sender['first_name']}", substr($message, 0, 100), 'info', BASE_URL . '/chat.php?user=' . $user_id);

        $stmt = $db->prepare("SELECT m.*, u.first_name, u.last_name, u.role, u.profile_image FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
        $stmt->execute([$msg_id]);

        echo json_encode(['success' => true, 'message' => 'Sent', 'data' => $stmt->fetch()]);
        break;

    case 'unread_total':
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'count' => (int)$stmt->fetchColumn()]);
        break;

    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) { echo json_encode(['success' => true, 'data' => []]); exit; }
        $like = '%' . $q . '%';
        $stmt = $db->prepare("
            SELECT m.*, u.first_name, u.last_name, u.role, u.profile_image
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id=? OR m.receiver_id=?) AND m.message LIKE ?
            ORDER BY m.created_at DESC LIMIT 30
        ");
        $stmt->execute([$user_id, $user_id, $like]);
        $results = $stmt->fetchAll();

        foreach ($results as &$r) {
            $other_id = ($r['sender_id'] == $user_id) ? $r['receiver_id'] : $r['sender_id'];
            $r['other_id'] = $other_id;
            $stmt2 = $db->prepare("SELECT first_name, last_name FROM users WHERE id=?");
            $stmt2->execute([$other_id]);
            $other = $stmt2->fetch();
            $r['other_name'] = $other ? $other['first_name'].' '.$other['last_name'] : 'Unknown';
        }
        echo json_encode(['success' => true, 'data' => $results]);
        break;

    case 'mark_read':
        $other_id = (int)($_POST['user_id'] ?? 0);
        if ($other_id) {
            $db->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$other_id, $user_id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_seen':
        $other_id = (int)($_POST['user_id'] ?? 0);
        if ($other_id) {
            $db->prepare("UPDATE messages SET is_read=1, seen_at=NOW() WHERE sender_id=? AND receiver_id=? AND seen_at IS NULL")->execute([$other_id, $user_id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'typing':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $target_id = (int)($_POST['user_id'] ?? 0);
            if (!$target_id) { echo json_encode(['success' => false]); exit; }
            $db->prepare("INSERT INTO chat_typing (user_id, conversation_with, is_typing, updated_at) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE is_typing=1, updated_at=NOW()")->execute([$user_id, $target_id]);
            echo json_encode(['success' => true]);
        } else {
            $target_id = (int)($_GET['user_id'] ?? 0);
            if (!$target_id) { echo json_encode(['success' => false, 'is_typing' => false]); exit; }
            $stmt = $db->prepare("SELECT is_typing FROM chat_typing WHERE user_id=? AND conversation_with=? AND updated_at >= DATE_SUB(NOW(), INTERVAL 4 SECOND)");
            $stmt->execute([$target_id, $user_id]);
            $row = $stmt->fetch();
            echo json_encode(['success' => true, 'is_typing' => !empty($row) && $row['is_typing']]);
        }
        break;

    case 'stop_typing':
        $target_id = (int)($_POST['user_id'] ?? 0);
        if ($target_id) {
            $db->prepare("UPDATE chat_typing SET is_typing=0 WHERE user_id=? AND conversation_with=?")->execute([$user_id, $target_id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'online_status':
        $check_id = (int)($_GET['user_id'] ?? 0);
        if (!$check_id) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("SELECT last_activity FROM users WHERE id=?");
        $stmt->execute([$check_id]);
        $row = $stmt->fetch();
        $online = $row && $row['last_activity'] && strtotime($row['last_activity']) >= time() - 120;
        echo json_encode(['success' => true, 'online' => $online]);
        break;

    case 'start_call':
        $callee_id = (int)($_POST['callee_id'] ?? 0);
        $type = $_POST['type'] ?? 'audio';
        if (!$callee_id) { echo json_encode(['success' => false, 'message' => 'No callee']); exit; }
        $stmt = $db->prepare("INSERT INTO calls (caller_id, callee_id, type, status) VALUES (?,?,?,'ringing')");
        $stmt->execute([$user_id, $callee_id, $type]);
        $call_id = $db->lastInsertId();
        echo json_encode(['success' => true, 'call_id' => $call_id]);
        break;

    case 'check_call':
        if (isset($_GET['missed'])) {
            $stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.profile_image FROM calls c JOIN users u ON c.caller_id=u.id WHERE c.callee_id=? AND c.status='missed' AND c.ended_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY c.created_at DESC LIMIT 10");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'calls' => $stmt->fetchAll()]);
            exit;
        }
        $stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name, u.profile_image FROM calls c JOIN users u ON c.caller_id=u.id WHERE (c.callee_id=? OR c.caller_id=?) AND c.status IN ('ringing','connected') ORDER BY c.created_at DESC LIMIT 1");
        $stmt->execute([$user_id, $user_id]);
        $call = $stmt->fetch();
        echo json_encode(['success' => true, 'call' => $call ?: null]);
        break;

    case 'update_call':
        $call_id = (int)($_POST['call_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!$call_id || !$status) { echo json_encode(['success' => false]); exit; }
        $now = date('Y-m-d H:i:s');
        if ($status === 'connected') {
            $db->prepare("UPDATE calls SET status=?, started_at=? WHERE id=?")->execute([$status, $now, $call_id]);
        } elseif (in_array($status, ['ended', 'rejected', 'missed'])) {
            $db->prepare("UPDATE calls SET status=?, ended_at=? WHERE id=?")->execute([$status, $now, $call_id]);
        } else {
            $db->prepare("UPDATE calls SET status=? WHERE id=?")->execute([$status, $call_id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'send_signal':
        $call_id = (int)($_POST['call_id'] ?? 0);
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $signal_type = $_POST['signal_type'] ?? '';
        $signal_data = $_POST['signal_data'] ?? '';
        if (!$call_id || !$receiver_id || !$signal_type || !$signal_data) { echo json_encode(['success' => false]); exit; }
        $stmt = $db->prepare("INSERT INTO call_signals (call_id, sender_id, receiver_id, signal_type, signal_data) VALUES (?,?,?,?,?)");
        $stmt->execute([$call_id, $user_id, $receiver_id, $signal_type, $signal_data]);
        echo json_encode(['success' => true]);
        break;

    case 'get_signals':
        $call_id = (int)($_GET['call_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);
        if (!$call_id) { echo json_encode(['success' => false, 'signals' => []]); exit; }
        $stmt = $db->prepare("SELECT * FROM call_signals WHERE call_id=? AND sender_id!=? AND id>? ORDER BY id ASC");
        $stmt->execute([$call_id, $user_id, $last_id]);
        echo json_encode(['success' => true, 'signals' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
