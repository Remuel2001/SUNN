<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'clock_in':
    case 'clock_out':
        requireApiAuth();
        if (!isInstructor()) {
            echo json_encode(['success' => false, 'message' => 'Only instructors can clock in/out']);
            exit;
        }

        $db = getDB();
        $instructor = getInstructorByUserId();
        if (!$instructor) {
            echo json_encode(['success' => false, 'message' => 'Instructor profile not found. Contact admin.']);
            exit;
        }
        $type = $action === 'clock_in' ? 'time_in' : 'time_out';

        $specialDay = isSpecialDay();
        if ($specialDay) {
            $label = $specialDay['type'] === 'holiday' ? 'Holiday' : ($specialDay['type'] === 'suspension' ? 'Classes Suspended' : 'No Classes');
            echo json_encode(['success' => false, 'message' => $label . ': ' . ($specialDay['reason'] ?: 'No classes today')]);
            exit;
        }

        $schedule_id = (int)($_POST['schedule_id'] ?? 0);

        if ($schedule_id) {
            $check = $db->prepare("SELECT id, time_start, time_end FROM schedules WHERE id=? AND instructor_id=? AND day_of_week=? AND status='active'");
            $check->execute([$schedule_id, $instructor['id'], date('l')]);
            $schedule = $check->fetch();
            if (!$schedule) {
                echo json_encode(['success' => false, 'message' => 'Invalid schedule for today']);
                exit;
            }
        } else {
            $schedule = isWithinSchedule($instructor['id']);
            if (!$schedule) {
                echo json_encode(['success' => false, 'message' => 'No active class right now based on your schedule']);
                exit;
            }
            $schedule_id = $schedule['id'];
        }

        $existing = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type=?");
        $existing->execute([$instructor['id'], $schedule_id, $type]);
        if ($existing->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => "Already clocked $type for this class"]);
            exit;
        }

        if ($type === 'time_out') {
            $has_in = $db->prepare("SELECT COUNT(*) FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_in'");
            $has_in->execute([$instructor['id'], $schedule_id]);
            if (!$has_in->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'You must clock in first before clocking out']);
                exit;
            }
        }

        $current_time = date('H:i:s');
        $status = 'present';
        $late_threshold = getSetting('late_threshold') ?: 15;

        if ($schedule && $type === 'time_in') {
            $mins_late = (strtotime($current_time) - strtotime($schedule['time_start'])) / 60;
            if ($mins_late > $late_threshold) $status = 'late';
        }

        $image_path = null;
        $ev_dir = FACE_UPLOAD_PATH . '/evidence/' . $instructor['id'];
        if (!is_dir($ev_dir)) mkdir($ev_dir, 0755, true);

        if (!empty($_FILES['face_image']) && $_FILES['face_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['face_image'], $ev_dir, 'att');
            if ($upload['success']) $image_path = $upload['filename'];
        } elseif (!empty($_POST['face_data_url'])) {
            $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/i', '', $_POST['face_data_url']));
            if ($data) {
                $filename = 'att_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
                if (file_put_contents($ev_dir . '/' . $filename, $data)) {
                    $image_path = $filename;
                }
            }
        }

        $stmt = $db->prepare("INSERT INTO attendance_logs (instructor_id, schedule_id, timestamp, type, status, image_path, recognition_method) VALUES (?,?,NOW(),?,?,?,'face_recognition')");
        $stmt->execute([$instructor['id'], $schedule_id, $type, $status, $image_path]);
        $attendance_id = $db->lastInsertId();

        logActivity('Attendance', "Instructor clocked $type for schedule $schedule_id as $status");

        if ($status === 'late') {
            sendNotification($instructor['user_id'], 'Late Attendance', 'You have been marked as late.', 'warning');
        }

        $subject_name = '';
        $time_start = '';
        $time_end = '';
        if ($schedule) {
            $sinfo = $db->prepare("SELECT sub.name FROM schedules s JOIN subjects sub ON s.subject_id=sub.id WHERE s.id=?");
            $sinfo->execute([$schedule_id]);
            $srow = $sinfo->fetch();
            if ($srow) $subject_name = $srow['name'];
            $time_start = $schedule['time_start'];
            $time_end = $schedule['time_end'];
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully clocked $type for " . ($subject_name ?: 'class'),
            'status' => $status,
            'attendance_id' => $attendance_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'schedule_id' => $schedule_id,
            'subject' => $subject_name,
            'time_start' => $time_start,
            'time_end' => $time_end
        ]);
        break;

    case 'today':
        requireApiAuth();
        $db = getDB();
        $instructor_id = null;
        if (isInstructor()) {
            $instructor = getInstructorByUserId();
            $instructor_id = $instructor['id'];
        } elseif (isAdmin() && isset($_GET['instructor_id'])) {
            $instructor_id = $_GET['instructor_id'];
        }

        if (!$instructor_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid instructor']);
            exit;
        }

        $logs = getAttendanceToday($instructor_id);
        echo json_encode(['success' => true, 'data' => $logs]);
        break;

    case 'stats':
        requireApiAuth();
        $db = getDB();
        $instructor_id = $_GET['instructor_id'] ?? null;
        if (!$instructor_id && isInstructor()) {
            $instructor = getInstructorByUserId();
            $instructor_id = $instructor['id'];
        }
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');

        if ($instructor_id) {
            $stats = getAttendanceStats($instructor_id, $month, $year);
            echo json_encode(['success' => true, 'data' => $stats]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid instructor']);
        }
        break;

    case 'attach_evidence':
        requireApiAuth();
        if (!isInstructor()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $db = getDB();
        $instructor = getInstructorByUserId();
        $attendance_id = (int)($_POST['attendance_id'] ?? 0);
        $type = $_POST['type'] ?? 'time_in';

        if (!$attendance_id) {
            $stmt = $db->prepare("SELECT id FROM attendance_logs WHERE instructor_id=? AND DATE(timestamp)=CURDATE() AND type=? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$instructor['id'], $type]);
            $row = $stmt->fetch();
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'No attendance record found']);
                exit;
            }
            $attendance_id = $row['id'];
        }

        $image_path = null;
        if (!empty($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['evidence_file'], FACE_UPLOAD_PATH . '/evidence/' . $instructor['id'], 'evidence');
            if ($upload['success']) $image_path = $upload['filename'];
        } elseif (!empty($_POST['evidence_image'])) {
            $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/i', '', $_POST['evidence_image']));
            if ($data) {
                $dir = FACE_UPLOAD_PATH . '/evidence/' . $instructor['id'];
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filename = 'evidence_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
                if (file_put_contents($dir . '/' . $filename, $data)) {
                    $image_path = $filename;
                }
            }
        }

        if (!$image_path) {
            echo json_encode(['success' => false, 'message' => 'No image provided']);
            exit;
        }

        $stmt = $db->prepare("UPDATE attendance_logs SET image_path=? WHERE id=?");
        $stmt->execute([$image_path, $attendance_id]);

        $remarks = sanitize($_POST['remarks'] ?? '');
        if ($remarks) {
            $stmt = $db->prepare("UPDATE attendance_logs SET remarks=CONCAT(COALESCE(remarks,''),?) WHERE id=?");
            $stmt->execute(["\nEvidence: $remarks", $attendance_id]);
        }

        echo json_encode(['success' => true, 'message' => 'Evidence attached', 'filename' => $image_path]);
        break;

    case 'all':
        requireApiAuth();
        $db = getDB();
        $date = $_GET['date'] ?? date('Y-m-d');
        $logs = $db->prepare("
            SELECT a.*, u.first_name, u.last_name
            FROM attendance_logs a
            JOIN instructors i ON a.instructor_id = i.id
            JOIN users u ON i.user_id = u.id
            WHERE DATE(a.timestamp) = ?
            ORDER BY a.timestamp DESC
        ");
        $logs->execute([$date]);
        echo json_encode(['success' => true, 'data' => $logs->fetchAll()]);
        break;

    case 'schedules_today':
        requireApiAuth();
        $db = getDB();
        $instructor = getInstructorByUserId();
        if (!$instructor) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        $schedules = getDaySchedule($instructor['id'], date('l'));
        $statuses = [];
        foreach ($schedules as &$s) {
            $sid = $s['id'];
            $in = $db->prepare("SELECT id, timestamp, status, image_path FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_in' LIMIT 1");
            $in->execute([$instructor['id'], $sid]);
            $s['time_in'] = $in->fetch();

            $out = $db->prepare("SELECT id, timestamp, status, image_path FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_out' LIMIT 1");
            $out->execute([$instructor['id'], $sid]);
            $s['time_out'] = $out->fetch();

            $s['can_clock_in'] = empty($s['time_in']);
            $s['can_clock_out'] = !empty($s['time_in']) && empty($s['time_out']);
        }
        echo json_encode(['success' => true, 'schedules' => $schedules, 'current_time' => date('H:i:s')]);
        break;

    case 'detect_schedule':
        requireApiAuth();
        $db = getDB();
        $instructor = getInstructorByUserId();
        if (!$instructor) { echo json_encode(['success' => false, 'message' => 'Not found']); exit; }

        $specialDay = isSpecialDay();
        if ($specialDay) {
            $label = $specialDay['type'] === 'holiday' ? 'Holiday' : ($specialDay['type'] === 'suspension' ? 'Classes Suspended' : 'No Classes');
            echo json_encode(['success' => false, 'message' => $label . ': ' . ($specialDay['reason'] ?: 'No classes today'), 'special_day' => true, 'special_day_type' => $specialDay['type'], 'special_day_reason' => $specialDay['reason']]);
            exit;
        }

        $now = date('H:i:s');
        $schedules = getDaySchedule($instructor['id'], date('l'));
        $current = null;
        foreach ($schedules as $s) {
            if ($now >= $s['time_start'] && $now <= $s['time_end']) {
                $current = $s;
                break;
            }
        }
        if (!$current) {
            echo json_encode(['success' => false, 'message' => 'No active class right now', 'has_schedule' => !empty($schedules)]);
            exit;
        }

        $in_rec = $db->prepare("SELECT id, timestamp, status FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_in' LIMIT 1");
        $in_rec->execute([$instructor['id'], $current['id']]);
        $in_data = $in_rec->fetch();

        $out_rec = $db->prepare("SELECT id, timestamp, status FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_out' LIMIT 1");
        $out_rec->execute([$instructor['id'], $current['id']]);
        $out_data = $out_rec->fetch();

        $can_clock_in = empty($in_data);
        $can_clock_out = !empty($in_data) && empty($out_data);

        echo json_encode([
            'success' => true,
            'schedule' => $current,
            'can_clock_in' => $can_clock_in,
            'can_clock_out' => $can_clock_out,
            'has_clocked_in' => !empty($in_data),
            'has_clocked_out' => !empty($out_data),
            'current_time' => $now
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
