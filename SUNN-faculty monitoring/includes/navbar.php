<?php
$current_page = basename($_SERVER['PHP_SELF']);
$notif_count = getNotificationCount();
$user_role = $_SESSION['user_role'] ?? '';
?>
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
            <img src="<?= BASE_URL ?>/uploads/brand/SUNN_Logo.png" alt="SUNN" class="brand-header-img me-2" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <i class="bi bi-camera-video-fill fs-5" style="display:none"></i>
            <span class="d-none d-sm-inline">SUNN</span><span class="fw-light d-none d-sm-inline"> Faculty Monitor</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" style="color:rgba(255,255,255,.8)">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 <?= in_array($current_page, ['dashboard.php', 'index.php']) ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">
                        <i class="bi bi-speedometer2"></i><span class="d-none d-lg-inline">Dashboard</span>
                    </a>
                </li>
                <?php if ($user_role === 'admin' || $user_role === 'department_head'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-people-fill"></i><span class="d-none d-lg-inline">Management</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/instructors.php"><i class="bi bi-person-video3 me-2 text-primary"></i>Instructors</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/face_registration.php"><i class="bi bi-camera-fill me-2 text-success"></i>Face Registration</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/departments.php"><i class="bi bi-building me-2 text-info"></i>Departments</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/subjects.php"><i class="bi bi-book me-2 text-warning"></i>Subjects</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/classrooms.php"><i class="bi bi-door-open me-2 text-secondary"></i>Classrooms</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/schedules.php"><i class="bi bi-calendar-week me-2 text-danger"></i>Schedules</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/special_days.php"><i class="bi bi-calendar-x me-2 text-danger"></i>Special Days</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/users.php"><i class="bi bi-person-badge me-2 text-primary"></i>Users</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-clipboard-data-fill"></i><span class="d-none d-lg-inline">Monitoring</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/attendance.php"><i class="bi bi-clock-history me-2 text-primary"></i>Attendance Logs</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/classroom_monitor.php"><i class="bi bi-camera-video me-2 text-success"></i>Classroom Monitor</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 <?= strpos($current_page, 'reports') !== false ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">
                        <i class="bi bi-file-earmark-bar-graph"></i><span class="d-none d-lg-inline">Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 <?= $current_page === 'chat.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/chat.php">
                        <i class="bi bi-chat-dots-fill"></i><span class="d-none d-lg-inline">Chat</span>
                    </a>
                </li>
                <?php elseif ($user_role === 'instructor'): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1" href="<?= BASE_URL ?>/instructor/attendance.php"><i class="bi bi-clock"></i><span class="d-none d-lg-inline">Attendance</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1" href="<?= BASE_URL ?>/instructor/schedule.php"><i class="bi bi-calendar"></i><span class="d-none d-lg-inline">Schedule</span></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 <?= $current_page === 'chat.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/chat.php">
                        <i class="bi bi-chat-dots-fill"></i><span class="d-none d-lg-inline">Chat</span>
                    </a>
                </li>
                <?php elseif ($user_role === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1 <?= $current_page === 'chat.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/chat.php">
                        <i class="bi bi-chat-dots-fill"></i><span class="d-none d-lg-inline">Chat</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-center">
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill fs-6"></i>
                        <?php if ($notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;padding:.25em .5em">
                            <?= $notif_count > 99 ? '99+' : $notif_count ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 340px;">
                        <li class="d-flex justify-content-between align-items-center px-3 py-2">
                            <h6 class="mb-0 fw-bold small">Notifications</h6>
                            <form method="POST" action="<?= BASE_URL ?>/admin/notifications.php" style="display:inline">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button class="btn btn-sm btn-link text-decoration-none p-0 small">Mark all read</button>
                            </form>
                        </li>
                        <li><hr class="dropdown-divider my-0"></li>
                        <li><div id="notificationList">
                            <?php
                            $notifications = getUnreadNotifications();
                            if (empty($notifications)): ?>
                                <div class="text-center text-muted py-4 small">
                                    <i class="bi bi-bell-slash d-block mb-2 fs-4"></i>
                                    No new notifications
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                <a class="dropdown-item notification-item py-2 px-3" href="<?= $notif['link'] ?: '#' ?>" data-id="<?= $notif['id'] ?>">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi bi-<?= $notif['type'] == 'danger' ? 'exclamation-circle' : ($notif['type'] == 'warning' ? 'exclamation-triangle' : 'info-circle') ?> text-<?= $notif['type'] ?> mt-1"></i>
                                        <div class="flex-grow-1">
                                            <small class="fw-semibold d-block"><?= htmlspecialchars($notif['title']) ?></small>
                                            <small class="text-muted d-block text-truncate" style="max-width:250px"><?= htmlspecialchars($notif['message']) ?></small>
                                            <small class="text-muted" style="font-size:.65rem"><?= timeAgo($notif['created_at']) ?></small>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div></li>
                        <li><hr class="dropdown-divider my-0"></li>
                        <li><a class="dropdown-item text-center small py-2" href="<?= BASE_URL ?>/admin/notifications.php">View all notifications</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown ms-1">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-6"></i>
                        <span class="small d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 border-bottom">
                            <small class="text-muted d-block">Signed in as</small>
                            <span class="fw-semibold small"><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
                            <br><span class="badge bg-primary bg-opacity-10 mt-1"><?= ucfirst(str_replace('_', ' ', $user_role)) ?></span>
                        </li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <?php if ($user_role === 'admin'): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/settings.php"><i class="bi bi-tools me-2"></i>System Settings</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/activity_logs.php"><i class="bi bi-journal-text me-2"></i>Activity Logs</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div style="padding-top: var(--nav-height);"></div>
