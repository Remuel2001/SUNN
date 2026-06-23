<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Activity Logs';
$db = getDB();

$logs = $db->query("
    SELECT al.*, u.first_name, u.last_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 500
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-journal-text me-2"></i>Activity Logs</h4>
        <span class="text-muted">Last 500 entries</span>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>User</th><th>Action</th><th>Description</th><th>IP Address</th><th>Level</th><th>Date/Time</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No activity logs</td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars(($l['first_name']??'System').' '.($l['last_name']??'')) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($l['action']) ?></span></td>
                        <td><?= htmlspecialchars($l['description']) ?></td>
                        <td><code><?= htmlspecialchars($l['ip_address'] ?? 'N/A') ?></code></td>
                        <td><span class="badge bg-<?= $l['log_level']=='error'?'danger':($l['log_level']=='warning'?'warning':($l['log_level']=='critical'?'dark':'info')) ?>"><?= $l['log_level'] ?></span></td>
                        <td><?= formatDateTime($l['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
