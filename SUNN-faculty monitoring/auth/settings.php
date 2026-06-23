<?php
require_once __DIR__ . '/../config/config.php';
redirectIfNotLoggedIn();
$page_title = 'Account Settings';
$user = getUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    if (isset($_POST['update_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            logActivity('Password Change', 'User changed their password');
            $success = 'Password updated successfully';
        }
    } elseif (isset($_POST['update_preferences'])) {
        $theme = sanitize($_POST['theme'] ?? 'light');
            $notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $prefs = json_encode(['theme' => $theme, 'email_notifications' => $notifications]);
            $db->prepare("UPDATE users SET preferences=? WHERE id=?")->execute([$prefs, $user['id']]);
            $success = 'Preferences updated';
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card fade-in mb-4">
                <div class="card-header"><i class="bi bi-lock me-2"></i>Change Password</div>
                <div class="card-body">
                    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
                    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                            <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                            <div class="col-md-4"><label class="form-label">Confirm Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                        </div>
                        <div class="mt-3"><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Password</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
