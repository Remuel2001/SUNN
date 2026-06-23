<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$setup_msg = '';

if (isset($_GET['setup']) && $_GET['setup'] === 'success') {
    $setup_msg = 'Database setup complete! You can now login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logActivity('Login', "User {$user['username']} logged in");

            $redirect = match($user['role']) {
                'admin' => '/admin/dashboard.php',
                'instructor' => '/instructor/dashboard.php',
                'student' => '/student/dashboard.php',
                'department_head' => '/department_head/dashboard.php',
                default => '/index.php'
            };
            header('Location: ' . BASE_URL . $redirect);
            exit;
        } else {
            $error = 'Invalid username or password';
            logActivity('Login Failed', "Failed login attempt for username: $username", 'warning');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - SUNN Faculty Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card-wrapper">
            <div class="login-header-section">
                <img src="<?= BASE_URL ?>/uploads/brand/SUNN-HEADER.png" alt="SUNN Faculty" class="login-header-full">
            </div>
            <div class="login-form-section">
                <div class="text-center">
                    <img src="<?= BASE_URL ?>/uploads/brand/SUNN_Logo.png" alt="SUNN Logo" class="logo-img" 
                         onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='block';">
                    <i id="logoFallback" class="bi bi-camera-video-fill" style="display:none;font-size:2rem;color:var(--primary)"></i>
                    <h4>Welcome Back</h4>
                    <p class="subtitle">Sign in to your account to continue</p>
                </div>

                <?php if ($setup_msg): ?>
                <div class="alert alert-success alert-dismissible fade show py-2 small"><?= $setup_msg ?><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i><?= $error ?>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                            <button class="password-toggle" type="button" onclick="togglePassword()" tabindex="-1">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>

                <div class="login-divider">SUNN Faculty Monitoring</div>

            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const pwd = document.querySelector('[name="password"]');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            pwd.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
