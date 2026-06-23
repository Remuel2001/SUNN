<?php
require_once __DIR__ . '/../config/config.php';

try {
    $db = getDB();

    $has_special_days = $db->query("SHOW TABLES LIKE 'special_days'")->fetchColumn();
    if (!$has_special_days) {
        $db->exec("
            CREATE TABLE special_days (
                id INT PRIMARY KEY AUTO_INCREMENT,
                date DATE NOT NULL,
                type ENUM('holiday','suspension','no_classes') NOT NULL,
                reason VARCHAR(255) DEFAULT NULL,
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_special_date (date),
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
        echo "<div class='alert alert-success'>Special days table created!</div>";
    } else {
        echo "<div class='alert alert-info'>Special days table already exists.</div>";
    }

    $has_permissions = $db->query("SHOW TABLES LIKE 'permissions'")->fetchColumn();
    if (!$has_permissions) {
        $db->exec("
            CREATE TABLE permissions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                permission_key VARCHAR(100) UNIQUE NOT NULL,
                permission_name VARCHAR(200) NOT NULL,
                module VARCHAR(50) NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("
            CREATE TABLE user_permissions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                permission_id INT NOT NULL,
                granted TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_perm (user_id, permission_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            )
        ");
        $db->exec("
            INSERT INTO permissions (permission_key, permission_name, module, description) VALUES
            ('dashboard.access', 'Access Dashboard', 'Dashboard', 'View the main dashboard'),
            ('users.view', 'View Users', 'Users', 'View user accounts list'),
            ('users.create', 'Create Users', 'Users', 'Add new user accounts'),
            ('users.edit', 'Edit Users', 'Users', 'Modify existing user accounts'),
            ('users.delete', 'Delete Users', 'Users', 'Remove user accounts'),
            ('instructors.view', 'View Instructors', 'Instructors', 'View instructor profiles'),
            ('instructors.manage', 'Manage Instructors', 'Instructors', 'Add/edit/delete instructors'),
            ('subjects.view', 'View Subjects', 'Subjects', 'View subject list'),
            ('subjects.manage', 'Manage Subjects', 'Subjects', 'Add/edit/delete subjects'),
            ('classrooms.view', 'View Classrooms', 'Classrooms', 'View classroom list'),
            ('classrooms.manage', 'Manage Classrooms', 'Classrooms', 'Add/edit/delete classrooms'),
            ('schedules.view', 'View Schedules', 'Schedules', 'View schedule list'),
            ('schedules.manage', 'Manage Schedules', 'Schedules', 'Add/edit/delete schedules'),
            ('attendance.view', 'View Attendance', 'Attendance', 'View attendance records'),
            ('attendance.clock', 'Clock In/Out', 'Attendance', 'Clock in and out'),
            ('attendance.manage', 'Manage Attendance', 'Attendance', 'Modify attendance records'),
            ('face_data.manage', 'Manage Face Data', 'Face Recognition', 'Register and manage facial data'),
            ('reports.view', 'View Reports', 'Reports', 'Access reports and analytics'),
            ('settings.access', 'Access Settings', 'Settings', 'Modify system settings'),
            ('activity_logs.view', 'View Activity Logs', 'Activity Logs', 'View system audit trail'),
            ('notifications.manage', 'Manage Notifications', 'Notifications', 'Send and manage notifications'),
            ('leave.create', 'Create Leave Requests', 'Leave', 'Submit leave requests'),
            ('leave.approve', 'Approve Leave', 'Leave', 'Approve or reject leave requests'),
            ('special_days.manage', 'Manage Special Days', 'Special Days', 'Manage holidays, suspensions, and no-class days')
        ");
        $db->exec("INSERT INTO user_permissions (user_id, permission_id) SELECT 1, id FROM permissions");
        echo "<div class='alert alert-success'>Permissions tables created and seeded!</div>";
    } else {
        echo "<div class='alert alert-info'>Permissions tables already exist.</div>";
    }

    $has_typing = $db->query("SHOW TABLES LIKE 'chat_typing'")->fetchColumn();
    if (!$has_typing) {
        $db->exec("
            CREATE TABLE chat_typing (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                conversation_with INT NOT NULL,
                is_typing TINYINT(1) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_typing (user_id, conversation_with),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (conversation_with) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "<div class='alert alert-success'>Chat typing table created!</div>";
    }

    $has_seen = $db->query("SHOW COLUMNS FROM messages LIKE 'seen_at'")->fetchColumn();
    if (!$has_seen) {
        $db->exec("ALTER TABLE messages ADD COLUMN seen_at DATETIME DEFAULT NULL AFTER is_read");
        echo "<div class='alert alert-success'>Added seen_at column to messages!</div>";
    }

    $has_activity = $db->query("SHOW COLUMNS FROM users LIKE 'last_activity'")->fetchColumn();
    if (!$has_activity) {
        $db->exec("ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login");
        echo "<div class='alert alert-success'>Added last_activity column to users!</div>";
    }

    $tables = $db->query("SHOW TABLES LIKE 'messages'")->fetchColumn();
    if (!$tables) {
        $db->exec("
            CREATE TABLE messages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sender_id INT NOT NULL,
                receiver_id INT DEFAULT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                parent_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE SET NULL
            )
        ");
        echo "<div class='alert alert-success'>Messages table created successfully!</div>";
    } else {
        echo "<div class='alert alert-info'>Messages table already exists.</div>";
    }

    $indexes = $db->query("SHOW INDEX FROM messages WHERE Key_name='idx_messages_sender'")->fetchColumn();
    if (!$indexes) {
        $db->exec("ALTER TABLE messages ADD INDEX idx_messages_sender (sender_id)");
        $db->exec("ALTER TABLE messages ADD INDEX idx_messages_receiver (receiver_id)");
        $db->exec("ALTER TABLE messages ADD INDEX idx_messages_read (is_read)");
        $db->exec("ALTER TABLE messages ADD INDEX idx_messages_created (created_at)");
        echo "<div class='alert alert-success'>Messages table indexes added!</div>";
    }

    $has_sp_perm = $db->query("SELECT COUNT(*) FROM permissions WHERE permission_key='special_days.manage'")->fetchColumn();
    if (!$has_sp_perm) {
        $db->exec("INSERT INTO permissions (permission_key, permission_name, module, description) VALUES ('special_days.manage', 'Manage Special Days', 'Special Days', 'Manage holidays, suspensions, and no-class days')");
        $pid = $db->lastInsertId();
        $db->exec("INSERT IGNORE INTO user_permissions (user_id, permission_id) SELECT id, $pid FROM users WHERE role='admin'");
        echo "<div class='alert alert-success'>Special Days permission added and granted to admins!</div>";
    }

    $has_calls = $db->query("SHOW TABLES LIKE 'calls'")->fetchColumn();
    if (!$has_calls) {
        $db->exec("CREATE TABLE calls (id INT PRIMARY KEY AUTO_INCREMENT, caller_id INT NOT NULL, callee_id INT NOT NULL, type ENUM('audio','video') NOT NULL DEFAULT 'audio', status ENUM('ringing','connected','ended','rejected','missed') NOT NULL DEFAULT 'ringing', started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (callee_id) REFERENCES users(id) ON DELETE CASCADE)");
        $db->exec("CREATE TABLE call_signals (id INT PRIMARY KEY AUTO_INCREMENT, call_id INT NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, signal_type VARCHAR(20) NOT NULL, signal_data LONGTEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE)");
        echo "<div class='alert alert-success'>Call tables created!</div>";
    } else {
        $has_receiver = $db->query("SHOW COLUMNS FROM call_signals LIKE 'receiver_id'")->fetchColumn();
        if (!$has_receiver) {
            $db->exec("ALTER TABLE call_signals ADD COLUMN receiver_id INT NOT NULL AFTER sender_id");
            echo "<div class='alert alert-success'>Added receiver_id to call_signals!</div>";
        }
    }

    echo "<p><a href='" . BASE_URL . "/login.php'>Go to Login</a></p>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Migration failed: " . $e->getMessage() . "</div>";
}
