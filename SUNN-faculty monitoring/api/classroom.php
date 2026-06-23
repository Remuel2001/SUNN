<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'check_presence':
        requireApiAuth();
        $db = getDB();
        $classroom_id = $_POST['classroom_id'] ?? $_GET['classroom_id'] ?? 0;
        $instructor_id = $_POST['instructor_id'] ?? $_GET['instructor_id'] ?? 0;

        if (!$classroom_id || !$instructor_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }

        $schedule = $db->prepare("
            SELECT id FROM schedules
            WHERE instructor_id=? AND classroom_id=? AND day_of_week=? AND time_start<=CURTIME() AND time_end>=CURTIME() AND status='active'
        ");
        $schedule->execute([$instructor_id, $classroom_id, date('l')]);
        $schedule = $schedule->fetch();

        if ($schedule) {
            $stmt = $db->prepare("INSERT INTO classroom_presence (instructor_id, classroom_id, schedule_id, timestamp, status, verified_by) VALUES (?,?,?,NOW(),'present','face_recognition')");
            $stmt->execute([$instructor_id, $classroom_id, $schedule['id']]);
            echo json_encode(['success' => true, 'message' => 'Instructor present in classroom', 'schedule_matched' => true]);
        } else {
            $stmt = $db->prepare("INSERT INTO classroom_presence (instructor_id, classroom_id, timestamp, status, verified_by) VALUES (?,?,NOW(),'unverified','face_recognition')");
            $stmt->execute([$instructor_id, $classroom_id]);
            echo json_encode(['success' => true, 'message' => 'Presence logged but no active schedule found', 'schedule_matched' => false]);
        }
        break;

    case 'get_schedule':
        $db = getDB();
        $classroom_id = $_GET['classroom_id'] ?? 0;
        $day = $_GET['day'] ?? date('l');

        $stmt = $db->prepare("
            SELECT s.*, u.first_name, u.last_name, sub.name as subject_name, sub.code as subject_code
            FROM schedules s
            JOIN instructors i ON s.instructor_id = i.id
            JOIN users u ON i.user_id = u.id
            JOIN subjects sub ON s.subject_id = sub.id
            WHERE s.classroom_id = ? AND s.day_of_week = ? AND s.status = 'active'
            ORDER BY s.time_start
        ");
        $stmt->execute([$classroom_id, $day]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'room_status':
        $db = getDB();
        $stmt = $db->query("
            SELECT c.*,
                   (SELECT COUNT(*) FROM classroom_presence WHERE classroom_id=c.id AND DATE(timestamp)=CURDATE()) as today_visits,
                   (SELECT COUNT(*) FROM schedules WHERE classroom_id=c.id AND day_of_week=DATE_FORMAT(CURDATE(), '%W') AND status='active') as scheduled_classes
            FROM classrooms c WHERE c.status='active'
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
