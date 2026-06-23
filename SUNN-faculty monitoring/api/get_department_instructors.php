<?php
require_once __DIR__ . '/../config/config.php';
requireApiAuth();
$db = getDB();
$dept_id = (int)($_GET['department_id'] ?? 0);
if (!$dept_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No department ID provided']);
    exit;
}
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.status as user_status,
               i.employee_id, i.specialization, i.phone,
               (SELECT COUNT(*) FROM facial_data WHERE instructor_id=i.id AND status='active') as face_count
        FROM users u
        JOIN instructors i ON u.id = i.user_id
        WHERE i.department_id = ? AND u.role = 'instructor'
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    $stmt->execute([$dept_id]);
    $instructors = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'instructors' => $instructors]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
