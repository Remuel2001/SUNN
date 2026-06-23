<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Classroom Monitor';
$db = getDB();

$classrooms = $db->query("SELECT * FROM classrooms WHERE status='active'")->fetchAll();
$selected_room = $_GET['classroom_id'] ?? null;

if ($selected_room) {
    $presence = $db->prepare("
        SELECT cp.*, u.first_name, u.last_name, sub.name as subject_name, s.time_start, s.time_end
        FROM classroom_presence cp
        JOIN instructors i ON cp.instructor_id = i.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN schedules s ON cp.schedule_id = s.id
        LEFT JOIN subjects sub ON s.subject_id = sub.id
        WHERE cp.classroom_id = ? AND DATE(cp.timestamp) = CURDATE()
        ORDER BY cp.timestamp DESC
    ");
    $presence->execute([$selected_room]);
    $presence = $presence->fetchAll();
}

$today_schedules = $db->prepare("
    SELECT s.*, u.first_name, u.last_name, sub.name as subject_name, sub.code as subject_code,
           c.name as classroom_name
    FROM schedules s
    JOIN instructors i ON s.instructor_id = i.id
    JOIN users u ON i.user_id = u.id
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN classrooms c ON s.classroom_id = c.id
    WHERE s.day_of_week = ? AND s.status = 'active'
    ORDER BY s.time_start
");
$today_schedules->execute([date('l')]);
$today_schedules = $today_schedules->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-camera-video me-2"></i>Classroom Monitoring</h4>
        <span class="badge bg-primary fs-6"><i class="bi bi-calendar me-1"></i><?= date('l, F d, Y') ?></span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-door-open me-2"></i>Today's Schedule by Classroom</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Time</th><th>Instructor</th><th>Subject</th><th>Classroom</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($today_schedules)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No schedules for today</td></tr>
                            <?php else: ?>
                            <?php foreach ($today_schedules as $s): ?>
                            <?php
                                $current_time = date('H:i:s');
                                $is_ongoing = ($current_time >= $s['time_start'] && $current_time <= $s['time_end']);
                                $is_upcoming = ($current_time < $s['time_start']);
                            ?>
                            <tr class="<?= $is_ongoing ? 'table-primary' : '' ?>">
                                <td><?= formatTime($s['time_start']).' - '.formatTime($s['time_end']) ?></td>
                                <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                                <td><?= htmlspecialchars($s['subject_code'].' - '.$s['subject_name']) ?></td>
                                <td><?= htmlspecialchars($s['classroom_name']) ?></td>
                                <td>
                                    <?php if ($is_ongoing): ?>
                                        <span class="badge bg-success"><i class="bi bi-play-fill"></i> Ongoing</span>
                                    <?php elseif ($is_upcoming): ?>
                                        <span class="badge bg-info"><i class="bi bi-clock"></i> Upcoming</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-check"></i> Ended</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-search me-2"></i>Check Classroom Presence</div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <select name="classroom_id" class="form-select select2" required>
                                <option value="">Select Classroom</option>
                                <?php foreach ($classrooms as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $selected_room==$r['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($r['name'].' - '.$r['building'].' (Floor '.$r['floor'].')') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Check</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-building me-2"></i>Classroom Status</div>
                <div class="card-body">
                    <?php if (empty($classrooms)): ?>
                    <div class="empty-state"><i class="bi bi-door-open"></i><p>No classrooms configured</p></div>
                    <?php else: ?>
                    <?php foreach ($classrooms as $r): ?>
                    <div class="d-flex align-items-center justify-content-between mb-3 p-2 border rounded">
                        <div>
                            <h6 class="mb-0"><?= htmlspecialchars($r['name']) ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($r['building']) ?> - Floor <?= $r['floor'] ?></small>
                        </div>
                        <span class="badge bg-<?= $r['status']=='active'?'success':'secondary' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_room && !empty($presence)): ?>
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-clock-history me-2"></i>Room Activity Today</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($presence as $p): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></strong>
                                <span class="badge bg-<?= $p['status']=='present'?'success':'secondary' ?>"><?= $p['status'] ?></span>
                            </div>
                            <small class="text-muted"><?= formatDateTime($p['timestamp']) ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
