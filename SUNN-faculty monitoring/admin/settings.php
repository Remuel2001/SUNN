<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'System Settings';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];
    foreach ($settings as $key => $value) {
        $db->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key=?")->execute([$value, $key]);
    }
    $success = 'Settings updated successfully';
    logActivity('Update Settings', 'System settings were updated');
}

$all_settings = $db->query("SELECT * FROM system_settings ORDER BY setting_key")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-tools me-2"></i>System Settings</h4>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row g-4">
                    <?php foreach ($all_settings as $s): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-bold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $s['setting_key']))) ?></label>
                        <?php if (in_array($s['setting_key'], ['face_recognition_enabled'])): ?>
                        <select name="settings[<?= $s['setting_key'] ?>]" class="form-select">
                            <option value="1" <?= $s['setting_value']=='1'?'selected':'' ?>>Enabled</option>
                            <option value="0" <?= $s['setting_value']=='0'?'selected':'' ?>>Disabled</option>
                        </select>
                        <?php else: ?>
                        <input type="text" name="settings[<?= $s['setting_key'] ?>]" class="form-control" value="<?= htmlspecialchars($s['setting_value']) ?>">
                        <?php endif; ?>
                        <small class="text-muted"><?= htmlspecialchars($s['description'] ?? '') ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
