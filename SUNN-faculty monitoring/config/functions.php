<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireApiAuth() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireRole(...$roles) {
    redirectIfNotLoggedIn();
    if (!empty($roles)) {
        $ok = false;
        foreach ($roles as $r) { if (hasRole($r)) { $ok = true; break; } }
        if (!$ok) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}

function isAdmin() { return hasRole('admin'); }
function isInstructor() { return hasRole('instructor'); }
function isStudent() { return hasRole('student'); }
function isDeptHead() { return hasRole('department_head'); }

function _permsTableExists() {
    try {
        $db = getDB();
        return (bool)$db->query("SHOW TABLES LIKE 'permissions'")->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function hasPermission($permission_key) {
    if (!_permsTableExists()) return true;
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.permission_key = ? AND up.granted = 1
        ");
        $stmt->execute([$user_id, $permission_key]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return true;
    }
}

function getUserPermissions($user_id = null) {
    if (!_permsTableExists()) return [];
    $db = getDB();
    $uid = $user_id ?? ($_SESSION['user_id'] ?? 0);
    if (!$uid) return [];
    try {
        $stmt = $db->prepare("
            SELECT p.permission_key, p.permission_name, p.module, up.granted
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ?
            ORDER BY p.module, p.permission_name
        ");
        $stmt->execute([$uid]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getAllPermissions() {
    if (!_permsTableExists()) return [];
    try {
        $db = getDB();
        return $db->query("SELECT * FROM permissions ORDER BY module, permission_name")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getUserPermissionKeys($user_id = null) {
    $perms = getUserPermissions($user_id);
    return array_column(array_filter($perms, fn($p) => $p['granted'] == 1), 'permission_key');
}

function syncUserPermissions($user_id, $granted_keys) {
    if (!_permsTableExists()) return;
    try {
        $db = getDB();
        $all_perms = $db->query("SELECT id, permission_key FROM permissions")->fetchAll();
        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$user_id]);
        $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?,?,1)");
        foreach ($all_perms as $p) {
            if (in_array($p['permission_key'], $granted_keys)) {
                $stmt->execute([$user_id, $p['id']]);
            }
        }
    } catch (Exception $e) {}
}

function getUser($user_id = null) {
    $db = getDB();
    $id = $user_id ?? $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getInstructorByUserId($user_id = null) {
    $db = getDB();
    $id = $user_id ?? $_SESSION['user_id'];
    $stmt = $db->prepare("
        SELECT i.*, d.name as department_name, u.email, u.first_name, u.last_name, u.profile_image
        FROM instructors i
        JOIN users u ON i.user_id = u.id
        LEFT JOIN departments d ON i.department_id = d.id
        WHERE i.user_id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getSetting($key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function isSpecialDay($date = null) {
    try {
        $db = getDB();
        if (!$db->query("SHOW TABLES LIKE 'special_days'")->fetchColumn()) return false;
        if (!$date) $date = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM special_days WHERE date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function logActivity($action, $description, $level = 'info') {
    $db = getDB();
    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, log_level) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $description, $ip, $ua, $level]);
}

function sendNotification($user_id, $title, $message, $type = 'info', $link = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type, $link]);
}

function getUnreadNotifications($user_id = null) {
    $db = getDB();
    $id = $user_id ?? $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function getNotificationCount($user_id = null) {
    $db = getDB();
    $id = $user_id ?? $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$id]);
    return $stmt->fetch()['count'];
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $timestamp);
}

function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'pending' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
        'present' => 'success',
        'late' => 'warning',
        'absent' => 'danger',
        'on_leave' => 'info',
        'approved' => 'success',
        'rejected' => 'danger',
        'suspended' => 'danger',
        'maintenance' => 'warning',
        'unverified' => 'secondary',
        'holiday' => 'danger',
        'suspension' => 'warning',
        'no_classes' => 'info',
    ];
    $badge = $badges[$status] ?? 'primary';
    return "<span class='badge bg-{$badge}'>{$status}</span>";
}

function formatTime($time) {
    return date('h:i A', strtotime($time));
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

function generatePassword($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function uploadImage($file, $target_dir, $prefix = 'img') {
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 5 * 1024 * 1024;
    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload failed'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) return ['success' => false, 'error' => 'Invalid file type'];
    if ($file['size'] > $max_size) return ['success' => false, 'error' => 'File too large'];
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $target_dir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'path' => $target_path, 'filename' => $filename];
    }
    return ['success' => false, 'error' => 'Failed to save file'];
}

function getDaySchedule($instructor_id, $day = null) {
    $db = getDB();
    if (!$day) $day = date('l');
    $stmt = $db->prepare("
        SELECT s.*, sub.name as subject_name, sub.code as subject_code,
               c.name as classroom_name, c.building
        FROM schedules s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN classrooms c ON s.classroom_id = c.id
        WHERE s.instructor_id = ? AND s.day_of_week = ? AND s.status = 'active'
        ORDER BY s.time_start
    ");
    $stmt->execute([$instructor_id, $day]);
    return $stmt->fetchAll();
}

function isWithinSchedule($instructor_id) {
    $day = date('l');
    $time = date('H:i:s');
    $schedules = getDaySchedule($instructor_id, $day);
    foreach ($schedules as $sched) {
        if ($time >= $sched['time_start'] && $time <= $sched['time_end']) {
            return $sched;
        }
    }
    return false;
}

function getAttendanceToday($instructor_id) {
    $db = getDB();
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT * FROM attendance_logs
        WHERE instructor_id = ? AND DATE(timestamp) = ?
        ORDER BY timestamp DESC
    ");
    $stmt->execute([$instructor_id, $today]);
    return $stmt->fetchAll();
}

function getAttendanceStats($instructor_id, $month = null, $year = null) {
    $db = getDB();
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' AND type = 'time_in' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'late' AND type = 'time_in' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
        FROM attendance_logs
        WHERE instructor_id = ? AND MONTH(timestamp) = ? AND YEAR(timestamp) = ?
    ");
    $stmt->execute([$instructor_id, $month, $year]);
    return $stmt->fetch();
}

function exportToExcel($data, $filename = 'export') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo '<table border="1">';
    if (!empty($data)) {
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</table>';
    exit;
}
