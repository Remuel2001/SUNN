<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Schedules';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    try {
        if ($action === 'add' || $action === 'edit') {
            $instructor_id = $_POST['instructor_id']; $subject_id = $_POST['subject_id'];
            $classroom_id = $_POST['classroom_id']; $day = $_POST['day_of_week'];
            $time_start = $_POST['time_start']; $time_end = $_POST['time_end'];
            $section = sanitize($_POST['section'] ?? '');
            if ($action === 'add') {
                $db->prepare("INSERT INTO schedules (instructor_id, subject_id, classroom_id, day_of_week, time_start, time_end, section) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$instructor_id, $subject_id, $classroom_id, $day, $time_start, $time_end, $section]);
                $success = 'Schedule added';
            } else {
                $db->prepare("UPDATE schedules SET instructor_id=?, subject_id=?, classroom_id=?, day_of_week=?, time_start=?, time_end=?, section=? WHERE id=?")
                    ->execute([$instructor_id, $subject_id, $classroom_id, $day, $time_start, $time_end, $section, $_POST['id']]);
                $success = 'Schedule updated';
            }
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM schedules WHERE id=?")->execute([$_POST['id']]);
            $success = 'Schedule deleted';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$schedules = $db->query("
    SELECT s.*, u.first_name, u.last_name, sub.name as subject_name, sub.code as subject_code,
           c.name as classroom_name, c.building
    FROM schedules s
    JOIN instructors i ON s.instructor_id = i.id
    JOIN users u ON i.user_id = u.id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN classrooms c ON s.classroom_id = c.id
    ORDER BY s.day_of_week, s.time_start
")->fetchAll();

$instructors = $db->query("SELECT u.id as user_id, u.first_name, u.last_name, i.id as instructor_id FROM users u JOIN instructors i ON u.id=i.user_id WHERE u.role='instructor' AND u.status='active' ORDER BY u.last_name")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE status='active'")->fetchAll();
$classrooms = $db->query("SELECT * FROM classrooms WHERE status='active'")->fetchAll();
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-calendar-week me-2"></i>Schedules</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#schedModal" onclick="$('#schedAction').val('add'); $('#schedId').val(''); $('.sched-field').val('');"><i class="bi bi-plus-lg"></i> Add Schedule</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>Instructor</th><th>Subject</th><th>Classroom</th><th>Day</th><th>Time</th><th>Section</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                        <td><?= htmlspecialchars($s['subject_code'].' - '.$s['subject_name']) ?></td>
                        <td><?= htmlspecialchars($s['classroom_name']) ?></td>
                        <td><?= $s['day_of_week'] ?></td>
                        <td><?= formatTime($s['time_start']).' - '.formatTime($s['time_end']) ?></td>
                        <td><?= htmlspecialchars($s['section'] ?: 'N/A') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editSched(<?= json_encode($s) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" style="display:inline" class="delete-record">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="schedModal">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="schedAction"><input type="hidden" name="id" id="schedId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Instructor</label>
                        <select name="instructor_id" id="schedInstructor" class="form-select select2" required>
                            <option value="">Select Instructor</option>
                            <?php foreach ($instructors as $inst): ?>
                            <option value="<?= $inst['instructor_id'] ?>"><?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Subject</label>
                        <select name="subject_id" id="schedSubject" class="form-select select2" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['code'].' - '.$sub['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Classroom</label>
                        <select name="classroom_id" id="schedClassroom" class="form-select select2" required>
                            <option value="">Select Classroom</option>
                            <?php foreach ($classrooms as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name'].' ('.$r['building'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Day of Week</label>
                        <select name="day_of_week" id="schedDay" class="form-select" required>
                            <?php foreach ($days as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="time_start" id="schedStart" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">End Time</label><input type="time" name="time_end" id="schedEnd" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Section</label><input type="text" name="section" id="schedSection" class="form-control" placeholder="e.g. BSIT-3A"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<script>
function editSched(s) {
    $('#schedAction').val('edit'); $('#schedId').val(s.id); $('#schedInstructor').val(s.instructor_id).trigger('change');
    $('#schedSubject').val(s.subject_id).trigger('change'); $('#schedClassroom').val(s.classroom_id).trigger('change');
    $('#schedDay').val(s.day_of_week); $('#schedStart').val(s.time_start); $('#schedEnd').val(s.time_end); $('#schedSection').val(s.section);
    new bootstrap.Modal(document.getElementById('schedModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
