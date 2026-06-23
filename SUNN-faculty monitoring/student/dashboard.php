<?php
require_once __DIR__ . '/../config/config.php';
requireRole('student');
$page_title = 'Student Dashboard';
$db = getDB();

$instructors = $db->query("
    SELECT u.id as user_id, u.first_name, u.last_name, u.profile_image,
           d.name as department_name
    FROM users u
    JOIN instructors i ON u.id = i.user_id
    LEFT JOIN departments d ON i.department_id = d.id
    WHERE u.role = 'instructor' AND u.status = 'active'
    ORDER BY u.last_name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-mortarboard me-2"></i>Student Dashboard</h4>
        <div id="liveClock" class="text-muted small"></div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-person-video3 me-2"></i>Instructors to Evaluate</div>
        <div class="card-body">
            <?php if (empty($instructors)): ?>
            <div class="empty-state"><i class="bi bi-people"></i><p>No instructors available</p></div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($instructors as $inst): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($inst['first_name'].'+'.$inst['last_name']) ?>&size=80&background=4e73df&color=fff"
                                 class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover">
                            <h6><?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($inst['department_name'] ?? 'No Department') ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
