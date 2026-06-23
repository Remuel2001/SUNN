<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');
$page_title = 'Instructor Dashboard';
$db = getDB();
$instructor = getInstructorByUserId();
if (!$instructor) { echo '<div class="alert alert-danger m-4">Instructor profile not found. Contact admin.</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }
$instructor_id = $instructor['id'];

$today_attendance = getAttendanceToday($instructor_id);
$has_clocked_in = !empty(array_filter($today_attendance, fn($a) => $a['type'] === 'time_in'));
$has_clocked_out = !empty(array_filter($today_attendance, fn($a) => $a['type'] === 'time_out'));
$schedules = getDaySchedule($instructor_id);
$current_schedule = isWithinSchedule($instructor_id);
$stats = getAttendanceStats($instructor_id);
$recent_logs = $db->prepare("
    SELECT a.*, sub.name as subject_name, s.time_start, s.time_end
    FROM attendance_logs a
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE a.instructor_id = ?
    ORDER BY a.timestamp DESC LIMIT 8
");
$recent_logs->execute([$instructor_id]);
$recent_logs = $recent_logs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 fade-in">
        <div>
            <h4 class="mb-1">
                <i class="bi bi-person-circle me-2 text-primary"></i>
                Welcome, <?= htmlspecialchars($instructor['first_name']) ?>!
            </h4>
            <p class="text-muted mb-0 small">
                <?= htmlspecialchars($instructor['employee_id']) ?>
                <?php if ($instructor['department_name']): ?>
                · <?= htmlspecialchars($instructor['department_name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div id="liveClock" class="text-muted small text-end d-none d-md-block"></div>
            <?php if (!$has_clocked_in): ?>
                <a href="<?= BASE_URL ?>/instructor/attendance.php" class="btn btn-success btn-sm glow">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Time In
                </a>
            <?php elseif (!$has_clocked_out): ?>
                <a href="<?= BASE_URL ?>/instructor/attendance.php" class="btn btn-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Time Out
                </a>
            <?php else: ?>
                <span class="badge bg-success bg-opacity-10 py-2 px-3"><i class="bi bi-check-circle me-1"></i>Completed</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-4 fade-in" style="animation-delay:.1s">
        <div class="col-md-3 col-6">
            <div class="card stat-card primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Present This Month</div>
                            <div class="stat-value"><?= $stats['present'] ?? 0 ?></div>
                        </div>
                        <i class="bi bi-check-circle-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Late</div>
                            <div class="stat-value"><?= $stats['late'] ?? 0 ?></div>
                        </div>
                        <i class="bi bi-clock-fill stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="stat-label">Classes Today</div>
                            <div class="stat-value"><?= count($schedules) ?></div>
                        </div>
                        <i class="bi bi-calendar-week stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card fade-in" style="animation-delay:.2s">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock me-2 text-primary"></i>Today's Attendance</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($today_attendance)): ?>
                    <div class="attendance-timeline">
                        <?php foreach ($today_attendance as $a): ?>
                        <div class="timeline-item <?= $a['status']=='present'?'success':($a['status']=='late'?'warning':'danger') ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= ucfirst(str_replace('_', ' ', $a['type'])) ?></strong>
                                    <span class="badge bg-<?= $a['status']=='present'?'success':($a['status']=='late'?'warning':'danger') ?> bg-opacity-10 ms-2"><?= $a['status'] ?></span>
                                </div>
                                <small class="text-muted"><?= date('h:i A', strtotime($a['timestamp'])) ?></small>
                            </div>
                            <small class="text-muted"><?= date('M d, Y', strtotime($a['timestamp'])) ?> · <?= str_replace('_', ' ', $a['recognition_method']) ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock"></i>
                        <p>You haven't clocked in yet today</p>
                        <a href="<?= BASE_URL ?>/instructor/attendance.php" class="btn btn-primary"><i class="bi bi-camera me-1"></i>Clock In Now</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-4 fade-in" style="animation-delay:.25s">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-week me-2 text-primary"></i>Today's Schedule</span>
                    <a href="<?= BASE_URL ?>/instructor/schedule.php" class="btn btn-sm btn-outline-primary">Full Schedule</a>
                </div>
                <div class="card-body">
                    <?php if (empty($schedules)): ?>
                    <div class="empty-state"><i class="bi bi-calendar-x"></i><p>No classes scheduled today</p></div>
                    <?php else: ?>
                    <?php foreach ($schedules as $s): ?>
                    <?php
                        $now = date('H:i:s');
                        $is_ongoing = $now >= $s['time_start'] && $now <= $s['time_end'];
                    ?>
                    <div class="schedule-card" style="<?= $is_ongoing ? 'border-left-color:var(--success)' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="subject"><?= htmlspecialchars($s['subject_code']) ?> — <?= htmlspecialchars($s['subject_name']) ?></div>
                                <div class="room"><i class="bi bi-door-open me-1"></i><?= htmlspecialchars($s['classroom_name']) ?> · <?= htmlspecialchars($s['section'] ?: 'N/A') ?></div>
                            </div>
                            <div class="text-end">
                                <div class="time fw-semibold"><?= formatTime($s['time_start']) ?> — <?= formatTime($s['time_end']) ?></div>
                                <?php if ($is_ongoing): ?>
                                    <span class="badge bg-success bg-opacity-10 mt-1"><span class="live-dot"></span>Ongoing</span>
                                <?php elseif ($now < $s['time_start']): ?>
                                    <span class="badge bg-info bg-opacity-10 mt-1">Upcoming</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 mt-1">Ended</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card fade-in" style="animation-delay:.2s">
                <div class="card-header"><i class="bi bi-clock-history me-2 text-muted"></i>Recent Activity</div>
                <div class="card-body p-0">
                    <?php if (empty($recent_logs)): ?>
                    <div class="empty-state py-4"><i class="bi bi-clock-history"></i><p class="mb-0">No recent activity</p></div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_logs as $log): ?>
                        <div class="list-group-item border-0 py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?= $log['type']=='time_in'?'primary':'secondary' ?> bg-opacity-10"><?= $log['type'] == 'time_in' ? 'IN' : 'OUT' ?></span>
                                <span class="badge bg-<?= $log['status']=='present'?'success':($log['status']=='late'?'warning':'danger') ?> bg-opacity-10" style="font-size:.65rem"><?= $log['status'] ?></span>
                            </div>
                            <small class="text-muted d-block"><?= formatDateTime($log['timestamp']) ?></small>
                            <?php if ($log['subject_name']): ?>
                            <small class="text-muted"><?= htmlspecialchars($log['subject_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
