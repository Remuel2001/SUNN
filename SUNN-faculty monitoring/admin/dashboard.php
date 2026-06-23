<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Admin Dashboard';

$db = getDB();
$total_instructors = $db->query("SELECT COUNT(*) FROM instructors")->fetchColumn();
$total_students = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_classrooms = $db->query("SELECT COUNT(*) FROM classrooms")->fetchColumn();
$today_present = $db->query("SELECT COUNT(DISTINCT instructor_id) FROM attendance_logs WHERE DATE(timestamp)=CURDATE() AND type='time_in' AND status IN ('present','late')")->fetchColumn();
$today_absent = $db->query("SELECT COUNT(DISTINCT i.id) FROM instructors i WHERE i.id NOT IN (SELECT instructor_id FROM attendance_logs WHERE DATE(timestamp)=CURDATE() AND type='time_in')")->fetchColumn();
$total_schedules = $db->query("SELECT COUNT(*) FROM schedules WHERE status='active'")->fetchColumn();
$today_sched_count = $db->prepare("SELECT COUNT(*) FROM schedules WHERE day_of_week=? AND status='active'");
$today_sched_count->execute([date('l')]);
$today_sched_count = $today_sched_count->fetchColumn();

$recent_attendance = $db->query("
    SELECT a.*, u.first_name, u.last_name, u.profile_image, sub.name as subject_name
    FROM attendance_logs a
    JOIN instructors i ON a.instructor_id = i.id
    JOIN users u ON i.user_id = u.id
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    ORDER BY a.created_at DESC LIMIT 8
")->fetchAll();

$monthly_stats = $db->query("
    SELECT
        DATE_FORMAT(timestamp, '%Y-%m') as month,
        DATE_FORMAT(timestamp, '%b') as label,
        COUNT(*) as total,
        SUM(CASE WHEN status='present' AND type='time_in' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status='late' AND type='time_in' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
    FROM attendance_logs
    WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(timestamp, '%Y-%m'), DATE_FORMAT(timestamp, '%b')
    ORDER BY month
")->fetchAll();

$today_attendance_list = $db->query("
    SELECT u.first_name, u.last_name, a.status, a.type, a.timestamp, sub.name as subject
    FROM attendance_logs a
    JOIN instructors i ON a.instructor_id = i.id
    JOIN users u ON i.user_id = u.id
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE DATE(a.timestamp) = CURDATE()
    ORDER BY a.timestamp DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header d-flex justify-content-between align-items-center fade-in">
        <div>
            <h4 class="mb-1"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard</h4>
            <p class="text-muted mb-0 small">Overview of the faculty monitoring system</p>
        </div>
        <div id="liveClock" class="text-muted small text-end d-none d-md-block"></div>
    </div>

    <div class="row g-3 mb-4 fade-in" style="animation-delay:.1s">
        <div class="col-xl-2 col-lg-4 col-md-4 col-6">
            <div class="card stat-card primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label mb-1">Instructors</div>
                            <div class="stat-value"><?= $total_instructors ?></div>
                            <small class="text-muted">Total registered</small>
                        </div>
                        <i class="bi bi-people-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6">
            <div class="card stat-card info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label mb-1">Students</div>
                            <div class="stat-value"><?= $total_students ?></div>
                            <small class="text-muted">Total accounts</small>
                        </div>
                        <i class="bi bi-mortarboard-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6">
            <div class="card stat-card success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label mb-1">Present Today</div>
                            <div class="stat-value text-success"><?= $today_present ?></div>
                            <small class="text-muted"><?= $today_sched_count ?> classes today</small>
                        </div>
                        <i class="bi bi-check-circle-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6">
            <div class="card stat-card danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label mb-1">Absent Today</div>
                            <div class="stat-value text-danger"><?= $today_absent ?></div>
                            <small class="text-muted">Not yet clocked in</small>
                        </div>
                        <i class="bi bi-x-circle-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-md-4 col-6">
            <div class="card stat-card primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label mb-1">Classrooms</div>
                            <div class="stat-value"><?= $total_classrooms ?></div>
                            <small class="text-muted">Active rooms</small>
                        </div>
                        <i class="bi bi-door-open-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 fade-in" style="animation-delay:.2s">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-activity me-2 text-primary"></i>Monthly Attendance Trend</span>
                    <span class="badge bg-primary bg-opacity-10"><span class="live-dot"></span>Live</span>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1 fade-in" style="animation-delay:.3s">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2 text-primary"></i>Recent Attendance</span>
                    <a href="<?= BASE_URL ?>/admin/attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Instructor</th><th>Subject</th><th>Type</th><th>Status</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_attendance)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No attendance records yet today</td></tr>
                            <?php else: ?>
                            <?php foreach ($recent_attendance as $a): ?>
                            <tr>
                                <td>
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($a['first_name'].'+'.$a['last_name']) ?>&size=28&background=4f46e5&color=fff" class="rounded-circle me-2" style="width:28px;height:28px">
                                    <span class="fw-semibold small"><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></span>
                                </td>
                                <td><small><?= htmlspecialchars($a['subject_name'] ?? '—') ?></small></td>
                                <td><span class="badge bg-<?= $a['type']=='time_in'?'primary':'secondary' ?> bg-opacity-10"><?= $a['type'] == 'time_in' ? 'IN' : 'OUT' ?></span></td>
                                <td><?= getStatusBadge($a['status']) ?></td>
                                <td><small class="text-muted"><?= date('h:i A', strtotime($a['timestamp'])) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-list-check me-2 text-success"></i>Today's Activity</div>
                <div class="card-body p-0">
                    <?php if (empty($today_attendance_list)): ?>
                    <div class="empty-state"><i class="bi bi-clock"></i><p>No activity recorded today</p></div>
                    <?php else: ?>
                    <div class="attendance-timeline p-3">
                        <?php foreach ($today_attendance_list as $a): ?>
                        <div class="timeline-item <?= $a['status']=='present'?'success':($a['status']=='late'?'warning':'danger') ?>">
                            <div class="d-flex justify-content-between">
                                <strong class="small"><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></strong>
                                <span class="badge bg-<?= $a['status']=='present'?'success':($a['status']=='late'?'warning':'danger') ?> bg-opacity-10" style="font-size:.65rem"><?= $a['status'] ?></span>
                            </div>
                            <small class="text-muted"><?= $a['type'] == 'time_in' ? 'Clocked In' : 'Clocked Out' ?> · <?= date('h:i A', strtotime($a['timestamp'])) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const months = <?= json_encode(array_column($monthly_stats, 'label')) ?>;
const present = <?= json_encode(array_column($monthly_stats, 'present')) ?>;
const late = <?= json_encode(array_column($monthly_stats, 'late')) ?>;
const absent = <?= json_encode(array_column($monthly_stats, 'absent')) ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Present',
                data: present,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.08)',
                fill: true,
                tension: .4,
                pointRadius: 4,
                pointBackgroundColor: '#10b981',
                borderWidth: 2
            },
            {
                label: 'Late',
                data: late,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.08)',
                fill: true,
                tension: .4,
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b',
                borderWidth: 2
            },
            {
                label: 'Absent',
                data: absent,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                fill: true,
                tension: .4,
                pointRadius: 4,
                pointBackgroundColor: '#ef4444',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16, font: { size: 11 } } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
