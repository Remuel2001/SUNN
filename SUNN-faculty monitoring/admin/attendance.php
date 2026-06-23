<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Attendance Logs';
$db = getDB();

$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_instructor = $_GET['instructor'] ?? '';
$filter_status = $_GET['status'] ?? '';

$sql = "SELECT a.*, u.first_name, u.last_name, u.profile_image, sub.name as subject_name, s.time_start, s.time_end
        FROM attendance_logs a
        JOIN instructors i ON a.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN schedules s ON a.schedule_id = s.id
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        WHERE 1=1";
$params = [];
if ($filter_date) { $sql .= " AND DATE(a.timestamp) = ?"; $params[] = $filter_date; }
if ($filter_instructor) { $sql .= " AND a.instructor_id = ?"; $params[] = $filter_instructor; }
if ($filter_status) { $sql .= " AND a.status = ?"; $params[] = $filter_status; }
$sql .= " ORDER BY a.timestamp DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$instructors = $db->query("SELECT u.id as user_id, u.first_name, u.last_name, i.id as instructor_id FROM users u JOIN instructors i ON u.id=i.user_id WHERE u.role='instructor' AND u.status='active' ORDER BY u.last_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 fade-in">
        <div>
            <h4 class="mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>Attendance Logs</h4>
            <p class="text-muted mb-0 small">Monitor instructor attendance records</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/reports.php?type=attendance&date=<?= $filter_date ?>" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Export
        </a>
    </div>

    <div class="card mb-4 fade-in" style="animation-delay:.1s">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= $filter_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Instructor</label>
                    <select name="instructor" class="form-select select2">
                        <option value="">All Instructors</option>
                        <?php foreach ($instructors as $inst): ?>
                        <option value="<?= $inst['instructor_id'] ?>" <?= $filter_instructor == $inst['instructor_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="present" <?= $filter_status=='present'?'selected':'' ?>>Present</option>
                        <option value="late" <?= $filter_status=='late'?'selected':'' ?>>Late</option>
                        <option value="absent" <?= $filter_status=='absent'?'selected':'' ?>>Absent</option>
                        <option value="on_leave" <?= $filter_status=='on_leave'?'selected':'' ?>>On Leave</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card fade-in" style="animation-delay:.15s">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0">
                    <thead>
                        <tr><th>Instructor</th><th>Type</th><th>Status</th><th>Subject</th><th>Schedule</th><th>Timestamp</th><th>Method</th><th>Confidence</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No attendance records found</td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $a): ?>
                        <tr>
                            <td>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($a['first_name'].'+'.$a['last_name']) ?>&size=28&background=4f46e5&color=fff" class="rounded-circle me-2" style="width:28px;height:28px">
                                <span class="fw-semibold small"><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></span>
                            </td>
                            <td><span class="badge bg-<?= $a['type']=='time_in'?'primary':'secondary' ?> bg-opacity-10"><?= $a['type'] == 'time_in' ? 'IN' : 'OUT' ?></span></td>
                            <td><?= getStatusBadge($a['status']) ?></td>
                            <td><small><?= htmlspecialchars($a['subject_name'] ?? '—') ?></small></td>
                            <td><small class="text-muted"><?= $a['time_start'] ? formatTime($a['time_start']).' — '.formatTime($a['time_end']) : '—' ?></small></td>
                            <td><small><?= formatDateTime($a['timestamp']) ?></small></td>
                            <td><small class="text-muted"><?= str_replace('_', ' ', $a['recognition_method']) ?></small></td>
                            <td><?= $a['confidence_score'] ? number_format($a['confidence_score'] * 100, 1).'%' : '<span class="text-muted">—</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
