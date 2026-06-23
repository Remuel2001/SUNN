<?php
require_once __DIR__ . '/../config/config.php';
redirectIfNotLoggedIn();
$page_title = 'My Profile';
$user = getUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);

    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $error = 'Passwords do not match';
        } else {
            $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, password=? WHERE id=?")
               ->execute([$first_name, $last_name, $email, $password, $user['id']]);
            $success = 'Profile and password updated successfully';
            logActivity('Profile Update', 'User updated profile and password');
        }
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadImage($_FILES['profile_image'], UPLOAD_PATH . '/profiles', 'profile');
        if ($upload['success']) {
            $db->prepare("UPDATE users SET profile_image=? WHERE id=?")->execute([$upload['filename'], $user['id']]);
            $success = 'Profile image updated';
        }
    }

    $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?")
       ->execute([$first_name, $last_name, $email, $user['id']]);
    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
    $user = getUser();
    $success = $success ?? 'Profile updated successfully';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card fade-in">
                <div class="card-header">
                    <i class="bi bi-person-circle me-2"></i>My Profile
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <div class="mb-3">
                                    <img src="<?= $user['profile_image'] ? BASE_URL . '/uploads/profiles/' . $user['profile_image'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['first_name'] . '+' . $user['last_name']) . '&size=150&background=4e73df&color=fff' ?>"
                                         class="rounded-circle img-thumbnail" style="width:150px;height:150px;object-fit:cover;">
                                </div>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-8">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">New Password (leave blank to keep current)</label>
                                        <input type="password" name="new_password" class="form-control" minlength="6">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
