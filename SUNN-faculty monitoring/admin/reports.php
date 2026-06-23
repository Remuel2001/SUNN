<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Reports & Analytics';
$db = getDB();

$report_type = $_GET['type'] ?? 'dashboard';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$dept_id = (int)($_GET['department_id'] ?? 0);
$instructor_id = (int)($_GET['instructor_id'] ?? 0);

// Dashboard stats
$total_instructors = $db->query("SELECT COUNT(*) FROM instructors")->fetchColumn();
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$today_attendance = $db->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp)=CURDATE() AND type='time_in'")->fetchColumn();
$today_late = $db->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp)=CURDATE() AND type='time_in' AND status='late'")->fetchColumn();
$dept_list = $db->query("SELECT id, name FROM departments WHERE status='active' ORDER BY name")->fetchAll();
$inst_list = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u JOIN instructors i ON u.id=i.user_id WHERE u.role='instructor' AND u.status='active' ORDER BY u.last_name")->fetchAll();

// Attendance summary
$attendance_summary = $db->prepare("
    SELECT DATE(timestamp) as date, COUNT(*) as total,
           SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late,
           SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent
    FROM attendance_logs
    WHERE MONTH(timestamp) = ? AND YEAR(timestamp) = ?
    GROUP BY DATE(timestamp) ORDER BY date
");
$attendance_summary->execute([$month, $year]);
$attendance_summary = $attendance_summary->fetchAll();

// Instructor report
if ($instructor_id) {
    $inst_report = $db->prepare("
        SELECT DATE(timestamp) as date, type, status, TIME(timestamp) as time_val
        FROM attendance_logs
        WHERE instructor_id = (SELECT id FROM instructors WHERE user_id=?) AND MONTH(timestamp)=? AND YEAR(timestamp)=?
        ORDER BY timestamp DESC
    ");
    $inst_report->execute([$instructor_id, $month, $year]);
    $inst_report = $inst_report->fetchAll();
} else {
    $inst_report = [];
}

// Department report
if ($dept_id) {
    $dept_report = $db->prepare("
        SELECT u.first_name, u.last_name, DATE(a.timestamp) as date, a.type, a.status, TIME(a.timestamp) as time_val
        FROM attendance_logs a
        JOIN instructors i ON a.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE i.department_id = ? AND MONTH(a.timestamp)=? AND YEAR(a.timestamp)=?
        ORDER BY u.last_name, a.timestamp
    ");
    $dept_report->execute([$dept_id, $month, $year]);
    $dept_report = $dept_report->fetchAll();
} else {
    $dept_report = [];
}

// Export
if (isset($_GET['export'])) {
    if ($report_type === 'attendance') {
        $data = [];
        foreach ($attendance_summary as $row) {
            $data[] = ['Date' => $row['date'], 'Total' => $row['total'], 'Present' => $row['present'], 'Late' => $row['late'], 'Absent' => $row['absent']];
        }
        exportToExcel($data, "attendance_report_$month-$year");
    } elseif ($report_type === 'instructor' && $instructor_id) {
        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id=?")->execute([$instructor_id]);
        $u = $db->prepare("SELECT first_name, last_name FROM users WHERE id=?");
        $u->execute([$instructor_id]);
        $uname = $u->fetch();
        $data = [];
        foreach ($inst_report as $row) {
            $data[] = ['Date' => $row['date'], 'Type' => $row['type'], 'Status' => $row['status'], 'Time' => $row['time_val']];
        }
        $name = $uname ? $uname['first_name'] . '_' . $uname['last_name'] : 'instructor';
        exportToExcel($data, "{$name}_attendance_$month-$year");
    } elseif ($report_type === 'department' && $dept_id) {
        $d = $db->prepare("SELECT name FROM departments WHERE id=?");
        $d->execute([$dept_id]);
        $dname = $d->fetchColumn();
        $data = [];
        foreach ($dept_report as $row) {
            $data[] = ['Instructor' => $row['first_name'] . ' ' . $row['last_name'], 'Date' => $row['date'], 'Type' => $row['type'], 'Status' => $row['status']];
        }
        exportToExcel($data, ($dname ?: 'department') . "_attendance_$month-$year");
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 fade-in mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Reports & Analytics</h4>
        <p class="text-muted mb-0 small">Attendance and performance analytics</p>
    </div>
    <div class="d-flex gap-2">
        <a href="?type=dashboard" class="btn btn-sm <?= $report_type==='dashboard'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="?type=attendance&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm <?= $report_type==='attendance'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-calendar-range me-1"></i>Attendance</a>
        <a href="?type=instructor" class="btn btn-sm <?= $report_type==='instructor'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-person me-1"></i>Instructor</a>
        <a href="?type=department" class="btn btn-sm <?= $report_type==='department'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-building me-1"></i>Department</a>
    </div>
</div>

<?php if ($report_type === 'dashboard'): ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card primary"><div class="card-body">
            <div class="stat-label">Total Instructors</div>
            <div class="stat-value"><?= $total_instructors ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card info"><div class="card-body">
            <div class="stat-label">Active Users</div>
            <div class="stat-value"><?= $total_users ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success"><div class="card-body">
            <div class="stat-label">Today Present</div>
            <div class="stat-value"><?= $today_attendance ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning"><div class="card-body">
            <div class="stat-label">Today Late</div>
            <div class="stat-value"><?= $today_late ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><i class="bi bi-building me-2 text-primary"></i>Departments</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Department</th><th>Instructors</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($dept_list as $d):
                        $cnt = $db->prepare("SELECT COUNT(*) FROM instructors WHERE department_id=?");
                        $cnt->execute([$d['id']]);
                    ?>
                    <tr><td><?= htmlspecialchars($d['name']) ?></td><td><?= $cnt->fetchColumn() ?></td><td><a href="?type=department&department_id=<?= $d['id'] ?>&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-primary">View</a></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><i class="bi bi-person me-2 text-primary"></i>Instructors</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Name</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($inst_list as $inst): ?>
                    <tr><td><?= htmlspecialchars($inst['first_name'] . ' ' . $inst['last_name']) ?></td><td><a href="?type=instructor&instructor_id=<?= $inst['id'] ?>&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-primary">View Report</a></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<?php elseif ($report_type === 'attendance'): ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="attendance">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Month</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): $v = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $v ?>" <?= $month===$v?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Year</label>
                <select name="year" class="form-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year==(string)$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>View</button>
            </div>
            <div class="col-md-2">
                <a href="?type=attendance&month=<?= $month ?>&year=<?= $year ?>&export=1" class="btn btn-success w-100"><i class="bi bi-download me-1"></i>Export</a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($attendance_summary)): ?>
<div class="row g-4 fade-in">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Daily Attendance — <?= date('F', mktime(0,0,0,(int)$month,1)).' '.$year ?></div>
            <div class="card-body">
                <div class="chart-container" style="height:300px"><canvas id="attendanceChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>Summary</div>
            <div class="card-body">
                <?php
                $total_att = max(array_sum(array_column($attendance_summary, 'total')), 1);
                $total_present = array_sum(array_column($attendance_summary, 'present'));
                $total_late = array_sum(array_column($attendance_summary, 'late'));
                $total_absent = array_sum(array_column($attendance_summary, 'absent'));
                ?>
                <div class="chart-container" style="height:200px"><canvas id="pieChart"></canvas></div>
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-3" style="background:var(--success-bg)">
                        <span class="small fw-semibold"><i class="bi bi-circle-fill text-success me-2" style="font-size:.5rem"></i>Present</span>
                        <span class="fw-bold"><?= $total_present ?> <small class="text-muted fw-normal">(<?= round($total_present/$total_att*100) ?>%)</small></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-3" style="background:var(--warning-bg)">
                        <span class="small fw-semibold"><i class="bi bi-circle-fill text-warning me-2" style="font-size:.5rem"></i>Late</span>
                        <span class="fw-bold"><?= $total_late ?> <small class="text-muted fw-normal">(<?= round($total_late/$total_att*100) ?>%)</small></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-2 rounded-3" style="background:var(--danger-bg)">
                        <span class="small fw-semibold"><i class="bi bi-circle-fill text-danger me-2" style="font-size:.5rem"></i>Absent</span>
                        <span class="fw-bold"><?= $total_absent ?> <small class="text-muted fw-normal">(<?= round($total_absent/$total_att*100) ?>%)</small></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><i class="bi bi-table me-2 text-primary"></i>Attendance Log</div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover datatable mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Present</th><th>Late</th><th>Absent</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($attendance_summary as $row): ?>
                <tr><td><?= date('M d, Y', strtotime($row['date'])) ?></td><td><?= $row['present'] ?></td><td><?= $row['late'] ?></td><td><?= $row['absent'] ?></td><td><?= $row['total'] ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No attendance records for this month</div>
<?php endif; ?>

<?php elseif ($report_type === 'instructor'): ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="instructor">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Instructor</label>
                <select name="instructor_id" class="form-select" required>
                    <option value="">Select Instructor</option>
                    <?php foreach ($inst_list as $inst): ?>
                    <option value="<?= $inst['id'] ?>" <?= $instructor_id===$inst['id']?'selected':'' ?>><?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Month</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): $v = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $v ?>" <?= $month===$v?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Year</label>
                <select name="year" class="form-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year==(string)$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>View</button>
            </div>
            <?php if ($instructor_id && !empty($inst_report)): ?>
            <div class="col-md-2">
                <a href="?type=instructor&instructor_id=<?= $instructor_id ?>&month=<?= $month ?>&year=<?= $year ?>&export=1" class="btn btn-success w-100"><i class="bi bi-download me-1"></i>Export</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($inst_report)): ?>
<div class="card">
    <div class="card-header"><i class="bi bi-person me-2 text-primary"></i>Instructor Attendance</div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover datatable mb-0">
            <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($inst_report as $row): ?>
                <tr><td><?= date('M d, Y', strtotime($row['date'])) ?></td><td><?= ucfirst($row['type']) ?></td><td><?= getStatusBadge($row['status']) ?></td><td><?= htmlspecialchars($row['time_val'] ?? '-') ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($instructor_id): ?>
<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No attendance records found</div>
<?php else: ?>
<div class="text-center py-5 text-muted"><i class="bi bi-person fs-1 d-block mb-2"></i>Select an instructor to view their attendance report</div>
<?php endif; ?>

<?php elseif ($report_type === 'department'): ?>

<div class="card mb-4 fade-in">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="type" value="department">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Department</label>
                <select name="department_id" class="form-select" required>
                    <option value="">Select Department</option>
                    <?php foreach ($dept_list as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $dept_id===$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Month</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): $v = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $v ?>" <?= $month===$v?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Year</label>
                <select name="year" class="form-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year==(string)$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>View</button>
            </div>
            <?php if ($dept_id && !empty($dept_report)): ?>
            <div class="col-md-2">
                <a href="?type=department&department_id=<?= $dept_id ?>&month=<?= $month ?>&year=<?= $year ?>&export=1" class="btn btn-success w-100"><i class="bi bi-download me-1"></i>Export</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($dept_report)): ?>
<div class="card">
    <div class="card-header"><i class="bi bi-building me-2 text-primary"></i>Department Attendance</div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover datatable mb-0">
            <thead class="table-light"><tr><th>Instructor</th><th>Date</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($dept_report as $row): ?>
                <tr><td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td><td><?= date('M d, Y', strtotime($row['date'])) ?></td><td><?= ucfirst($row['type']) ?></td><td><?= getStatusBadge($row['status']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($dept_id): ?>
<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No attendance records found</div>
<?php else: ?>
<div class="text-center py-5 text-muted"><i class="bi bi-building fs-1 d-block mb-2"></i>Select a department to view attendance</div>
<?php endif; ?>

<?php endif; ?>

</div>

<script>
<?php if ($report_type === 'attendance' && !empty($attendance_summary)): ?>
new Chart(document.getElementById('attendanceChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d M', strtotime($d['date'])), $attendance_summary)) ?>,
        datasets: [
            { label: 'Present', data: <?= json_encode(array_column($attendance_summary, 'present')) ?>, backgroundColor: 'rgba(16,185,129,.7)', borderRadius: 4 },
            { label: 'Late', data: <?= json_encode(array_column($attendance_summary, 'late')) ?>, backgroundColor: 'rgba(245,158,11,.7)', borderRadius: 4 },
            { label: 'Absent', data: <?= json_encode(array_column($attendance_summary, 'absent')) ?>, backgroundColor: 'rgba(239,68,68,.7)', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } } }
    }
});
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Late', 'Absent'],
        datasets: [{ data: [<?= $total_present ?>, <?= $total_late ?>, <?= $total_absent ?>], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderWidth: 0 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        cutout: '65%'
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
