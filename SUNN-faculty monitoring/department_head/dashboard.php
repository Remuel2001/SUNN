<?php
require_once __DIR__ . '/../config/config.php';
requireRole('department_head');
$page_title = 'Department Head Dashboard';
$db = getDB();

$user = getUser();
$instructor = getInstructorByUserId();
$dept_id = $instructor['department_id'];

$dept_instructors = $db->prepare("
    SELECT u.first_name, u.last_name, u.email, u.status,
           i.employee_id, i.specialization,
           (SELECT COUNT(*) FROM attendance_logs WHERE instructor_id=i.id AND DATE(timestamp)=CURDATE() AND type='time_in') as attended_today
    FROM users u
    JOIN instructors i ON u.id = i.user_id
    WHERE i.department_id = ? AND u.role = 'instructor'
    ORDER BY u.last_name
");
$dept_instructors->execute([$dept_id]);
$dept_instructors = $dept_instructors->fetchAll();

$dept_attendance = $db->prepare("
    SELECT DATE(a.timestamp) as date, COUNT(DISTINCT a.instructor_id) as present_count,
           (SELECT COUNT(*) FROM instructors WHERE department_id=?) as total_instructors
    FROM attendance_logs a
    JOIN instructors i ON a.instructor_id = i.id
    WHERE i.department_id = ? AND a.type='time_in' AND a.timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.timestamp)
    ORDER BY date
");
$dept_attendance->execute([$dept_id, $dept_id]);
$dept_attendance = $dept_attendance->fetchAll();
$dept_total = count($dept_instructors);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-building me-2"></i>Department Dashboard</h4>
    <p class="text-muted"><?= htmlspecialchars($instructor['department_name'] ?? 'Department') ?> | <?= $dept_total ?> Instructor(s)</p>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card primary">
                <div class="card-body">
                    <div class="stat-label">Department Instructors</div>
                    <div class="stat-value"><?= $dept_total ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card success">
                <div class="card-body">
                    <div class="stat-label">Present Today</div>
                    <div class="stat-value"><?= count(array_filter($dept_instructors, fn($i) => $i['attended_today'] > 0)) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-people-fill me-2"></i>Department Instructors</div>
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>Instructor</th><th>Employee ID</th><th>Present Today</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_instructors as $inst): ?>
                    <tr>
                        <td><?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?></td>
                        <td><?= htmlspecialchars($inst['employee_id']) ?></td>
                        <td><?= $inst['attended_today'] > 0 ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                        <td><?= getStatusBadge($inst['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
