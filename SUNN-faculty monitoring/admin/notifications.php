<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Notifications';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([$_POST['id']]);
    } elseif ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
    } elseif ($action === 'send') {
        $user_id = $_POST['user_id']; $title = sanitize($_POST['title']); $message = sanitize($_POST['message']);
        $type = $_POST['type']; sendNotification($user_id, $title, $message, $type);
        $success = 'Notification sent';
    }
}

$notifications = $db->prepare("SELECT n.*, u.first_name, u.last_name FROM notifications n JOIN users u ON n.user_id=u.id ORDER BY n.created_at DESC");
$notifications->execute();
$notifications = $notifications->fetchAll();
$users = $db->query("SELECT id, first_name, last_name, role FROM users WHERE status='active' ORDER BY last_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-bell-fill me-2"></i>Notifications</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#notifModal"><i class="bi bi-send me-1"></i>Send Notification</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span><i class="bi bi-list me-2"></i>Notification History</span>
            <form method="POST"><input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all me-1"></i>Mark All Read</button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php if (empty($notifications)): ?>
                <div class="empty-state"><i class="bi bi-bell-slash"></i><p>No notifications</p></div>
                <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start <?= !$n['is_read'] ? 'fw-bold' : '' ?>">
                    <div class="ms-2 me-auto">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-<?= $n['type']=='danger'?'exclamation-circle':($n['type']=='warning'?'exclamation-triangle':'info-circle') ?> text-<?= $n['type'] ?> me-2"></i>
                            <span><?= htmlspecialchars($n['title']) ?></span>
                        </div>
                        <small class="text-muted"><?= htmlspecialchars($n['message']) ?></small><br>
                        <small class="text-muted">To: <?= htmlspecialchars($n['first_name'].' '.$n['last_name']) ?> | <?= timeAgo($n['created_at']) ?></small>
                    </div>
                    <?php if (!$n['is_read']): ?>
                    <form method="POST"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?= $n['id'] ?>">
                        <button class="btn btn-sm btn-link"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="notifModal"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <div class="modal-header"><h5 class="modal-title">Send Notification</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="action" value="send">
            <div class="mb-3"><label class="form-label">Recipient</label>
                <select name="user_id" class="form-select select2" required>
                    <option value="">Select User</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']).' ('.ucfirst($u['role']).')' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="3" required></textarea></div>
            <div class="mb-3"><label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="info">Info</option><option value="success">Success</option>
                    <option value="warning">Warning</option><option value="danger">Danger</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send</button></div>
    </form>
</div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
