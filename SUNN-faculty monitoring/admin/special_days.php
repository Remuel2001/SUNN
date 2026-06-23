<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Special Days';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $date = sanitize($_POST['date']);
    $type = $_POST['type'];
    $reason = sanitize($_POST['reason'] ?? '');
    try {
        if ($action === 'add') {
            $db->prepare("INSERT INTO special_days (date, type, reason, created_by) VALUES (?,?,?,?)")->execute([$date, $type, $reason, $_SESSION['user_id']]);
            $success = 'Special day added';
        } elseif ($action === 'edit') {
            $db->prepare("UPDATE special_days SET date=?, type=?, reason=? WHERE id=?")->execute([$date, $type, $reason, $_POST['id']]);
            $success = 'Special day updated';
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM special_days WHERE id=?")->execute([$_POST['id']]);
            $success = 'Special day removed';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$days = $db->query("SELECT sd.*, u.first_name, u.last_name FROM special_days sd LEFT JOIN users u ON sd.created_by = u.id ORDER BY sd.date DESC")->fetchAll();
$type_labels = ['holiday' => 'Holiday', 'suspension' => 'Suspension', 'no_classes' => 'No Classes'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-calendar-x me-2"></i>Special Days</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dayModal" onclick="$('#dayAction').val('add'); $('#dayId').val(''); $('#dayDate').val(''); $('#dayType').val('holiday'); $('#dayReason').val('');"><i class="bi bi-plus-lg"></i> Add Special Day</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>Date</th><th>Type</th><th>Reason</th><th>Added By</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $d): ?>
                    <tr>
                        <td><strong><?= date('M d, Y', strtotime($d['date'])) ?></strong> <small class="text-muted">(<?= date('l', strtotime($d['date'])) ?>)</small></td>
                        <td><?= getStatusBadge($d['type']) ?> <?= $type_labels[$d['type']] ?></td>
                        <td><?= htmlspecialchars($d['reason'] ?: '—') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editDay(<?= json_encode($d) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" style="display:inline" class="delete-record">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($days)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-calendar-check me-1"></i>No special days set</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="dayModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Special Day</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="dayAction"><input type="hidden" name="id" id="dayId">
                <div class="mb-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" id="dayDate" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" id="dayType" class="form-select">
                        <option value="holiday">Holiday</option>
                        <option value="suspension">Suspension</option>
                        <option value="no_classes">No Classes</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <textarea name="reason" id="dayReason" class="form-control" rows="2" placeholder="e.g. Independence Day, Typhoon, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </form>
    </div></div>
</div>

<script>
function editDay(d) {
    $('#dayAction').val('edit');
    $('#dayId').val(d.id);
    $('#dayDate').val(d.date);
    $('#dayType').val(d.type);
    $('#dayReason').val(d.reason || '');
    $('#dayModal').modal('show');
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
