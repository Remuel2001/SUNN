<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        $instructor_id = $_POST['instructor_id'] ?? 0;
        $descriptor = $_POST['descriptor'] ?? '';
        $image = $_POST['image'] ?? '';

        if (!$instructor_id || !$descriptor) {
            echo json_encode(['success' => false, 'message' => 'Missing instructor_id or descriptor']);
            exit;
        }

        if (!is_string($descriptor)) {
            echo json_encode(['success' => false, 'message' => 'Invalid descriptor format']);
            exit;
        }

        $decoded = json_decode($descriptor, true);
        if (!$decoded || !is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => 'Invalid descriptor data']);
            exit;
        }

        $db = getDB();
        $encoding_json = json_encode($decoded);

        $image_path = null;
        if (!empty($_FILES['face_image']) && $_FILES['face_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['face_image'], FACE_UPLOAD_PATH . '/' . $instructor_id, 'face');
            $image_path = $upload['success'] ? $upload['filename'] : null;
        } elseif ($image && !empty($_POST['save_image'])) {
            $img_dir = FACE_UPLOAD_PATH . '/' . $instructor_id;
            if (!is_dir($img_dir)) mkdir($img_dir, 0755, true);
            $filename = 'face_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
            $path = $img_dir . '/' . $filename;
            $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/i', '', $image));
            if (file_put_contents($path, $data)) {
                $image_path = $filename;
            }
        }

        $stmt = $db->prepare("SELECT id FROM facial_data WHERE instructor_id=? AND is_primary=1");
        $stmt->execute([$instructor_id]);
        $existing = $stmt->fetch();

        try {
            if ($existing) {
                $stmt = $db->prepare("UPDATE facial_data SET face_encoding=?, image_path=?, confidence_score=0.95 WHERE instructor_id=? AND is_primary=1");
                $stmt->execute([$encoding_json, $image_path, $instructor_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO facial_data (instructor_id, face_encoding, image_path, confidence_score, is_primary) VALUES (?,?,?,0.95,1)");
                $stmt->execute([$instructor_id, $encoding_json, $image_path]);
            }

            logActivity('Face Registration', "Face registered for instructor ID: $instructor_id");
            echo json_encode([
                'success' => true,
                'message' => 'Face registered successfully',
                'encoding_length' => count($decoded),
                'instructor_id' => $instructor_id
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'verify':
        $instructor_id = $_POST['instructor_id'] ?? 0;
        $descriptor = $_POST['descriptor'] ?? '';

        if (!$instructor_id || !$descriptor) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $decoded = json_decode($descriptor, true);
        if (!$decoded || !is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => 'Invalid descriptor']);
            exit;
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id, face_encoding, confidence_score FROM facial_data WHERE instructor_id=? AND status='active' ORDER BY is_primary DESC");
        $stmt->execute([$instructor_id]);
        $all_stored = $stmt->fetchAll();

        if (empty($all_stored)) {
            echo json_encode(['success' => false, 'message' => 'No face data registered. Contact admin.']);
            exit;
        }

        $best_similarity = 0;
        $best_id = null;
        $threshold = (float)(getSetting('recognition_threshold') ?: 0.35);

        foreach ($all_stored as $stored) {
            $stored_encoding = json_decode($stored['face_encoding'], true);
            if (!$stored_encoding) continue;

            $sim = 0;
            $len = min(count($decoded), count($stored_encoding));
            for ($i = 0; $i < $len; $i++) {
                $sim += min((float)$decoded[$i], (float)$stored_encoding[$i]);
            }

            if ($sim > $best_similarity) {
                $best_similarity = $sim;
                $best_id = $stored['id'];
            }
        }

        $match = $best_similarity >= $threshold;

        error_log("FACE_VERIFY instructor=$instructor_id best_similarity=" . round($best_similarity, 4) . " threshold=$threshold match=" . ($match ? 'yes' : 'no') . " records_checked=" . count($all_stored));

        echo json_encode([
            'success' => $match,
            'match' => $match,
            'message' => $match ? 'Face verified successfully' : 'Face does not match',
            'similarity' => round($best_similarity * 100, 2),
            'confidence' => round($best_similarity, 4),
            'threshold' => $threshold,
            'records_checked' => count($all_stored)
        ]);
        break;

    case 'detect':
        $descriptor = $_POST['descriptor'] ?? '';
        $image = $_POST['image'] ?? '';

        if ($descriptor) {
            $d = json_decode($descriptor, true);
            if ($d && is_array($d)) {
                echo json_encode(['success' => true, 'num_faces' => 1, 'encoding_length' => count($d)]);
                exit;
            }
        }

        if ($image) {
            $data = base64_decode(preg_replace('/^data:image\/\w+;base64,/i', '', $image));
            $im = @imagecreatefromstring($data);
            if ($im === false) {
                echo json_encode(['success' => false, 'error' => 'Invalid image']);
                exit;
            }
            $w = imagesx($im);
            $h = imagesy($im);
            imagedestroy($im);
            if ($w > 10 && $h > 10) {
                echo json_encode(['success' => true, 'num_faces' => 1, 'message' => 'Image captured successfully']);
            } else {
                echo json_encode(['success' => false, 'num_faces' => 0, 'error' => 'Image too small']);
            }
            exit;
        }

        echo json_encode(['success' => false, 'num_faces' => 0, 'error' => 'No data provided']);
        break;

    case 'check_status':
        $db = getDB();
        $instructor_id = $_GET['instructor_id'] ?? 0;
        $stmt = $db->prepare("SELECT COUNT(*) as count, MAX(confidence_score) as best_confidence, MAX(created_at) as last_registered FROM facial_data WHERE instructor_id=? AND status='active'");
        $stmt->execute([$instructor_id]);
        $status = $stmt->fetch();
        echo json_encode([
            'success' => true,
            'has_face' => $status['count'] > 0,
            'count' => (int)$status['count'],
            'best_confidence' => $status['best_confidence'],
            'last_registered' => $status['last_registered']
        ]);
        break;

    case 'get_faces':
        $db = getDB();
        $stmt = $db->query("
            SELECT f.*, u.first_name, u.last_name, u.profile_image
            FROM facial_data f
            JOIN instructors i ON f.instructor_id = i.id
            JOIN users u ON i.user_id = u.id
            WHERE f.status='active'
            ORDER BY u.last_name
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
}
