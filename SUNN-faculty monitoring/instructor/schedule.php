<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');
$page_title = 'My Schedule';
$db = getDB();
$instructor = getInstructorByUserId();
$instructor_id = $instructor['id'];

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$weekly_schedule = [];
foreach ($days as $day) {
    $weekly_schedule[$day] = getDaySchedule($instructor_id, $day);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-calendar-week me-2"></i>My Teaching Schedule</h4>

    <div class="row">
        <?php foreach ($days as $day): ?>
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card h-100 <?= $day == date('l') ? 'border-primary' : '' ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-day me-1"></i><?= $day ?></span>
                    <?php if ($day == date('l')): ?><span class="badge bg-primary">Today</span><?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($weekly_schedule[$day])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-emoji-neutral" style="font-size:2rem"></i>
                        <p class="mt-2 mb-0">No classes</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($weekly_schedule[$day] as $s): ?>
                    <div class="border-start border-3 border-primary ps-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($s['subject_code']) ?></strong>
                            <span class="badge bg-secondary"><?= htmlspecialchars($s['section'] ?: 'N/A') ?></span>
                        </div>
                        <small><?= htmlspecialchars($s['subject_name']) ?></small>
                        <div class="text-muted small">
                            <i class="bi bi-clock"></i> <?= formatTime($s['time_start']).' - '.formatTime($s['time_end']) ?>
                            <br><i class="bi bi-door-open"></i> <?= htmlspecialchars($s['classroom_name'].' ('.$s['building'].')') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
