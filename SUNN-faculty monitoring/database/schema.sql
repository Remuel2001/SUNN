CREATE DATABASE IF NOT EXISTS sunn_faculty_monitoring;
USE sunn_faculty_monitoring;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'instructor', 'student', 'department_head') NOT NULL DEFAULT 'instructor',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    profile_image VARCHAR(255) DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    last_activity DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE instructors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    employee_id VARCHAR(50) UNIQUE NOT NULL,
    department_id INT DEFAULT NULL,
    specialization VARCHAR(200) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    department_id INT DEFAULT NULL,
    units INT DEFAULT 3,
    description TEXT DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE classrooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    building VARCHAR(100) DEFAULT NULL,
    floor INT DEFAULT NULL,
    capacity INT DEFAULT 30,
    camera_ip VARCHAR(100) DEFAULT NULL,
    status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instructor_id INT NOT NULL,
    subject_id INT NOT NULL,
    classroom_id INT NOT NULL,
    section VARCHAR(50) DEFAULT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    semester VARCHAR(20) DEFAULT NULL,
    school_year VARCHAR(20) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE
);

CREATE TABLE facial_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instructor_id INT NOT NULL,
    face_encoding LONGTEXT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    confidence_score DECIMAL(5,2) DEFAULT 0.00,
    is_primary TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE
);

CREATE TABLE attendance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instructor_id INT NOT NULL,
    schedule_id INT DEFAULT NULL,
    timestamp DATETIME NOT NULL,
    type ENUM('time_in', 'time_out') NOT NULL,
    status ENUM('present', 'late', 'absent', 'on_leave') NOT NULL DEFAULT 'present',
    image_path VARCHAR(255) DEFAULT NULL,
    confidence_score DECIMAL(5,2) DEFAULT 0.00,
    recognition_method ENUM('face_recognition', 'manual', 'qr_code') NOT NULL DEFAULT 'face_recognition',
    remarks TEXT DEFAULT NULL,
    latitude DECIMAL(10,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL
);

CREATE TABLE classroom_presence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instructor_id INT NOT NULL,
    classroom_id INT NOT NULL,
    schedule_id INT DEFAULT NULL,
    timestamp DATETIME NOT NULL,
    status ENUM('present', 'absent', 'unverified') NOT NULL DEFAULT 'unverified',
    verified_by ENUM('face_recognition', 'manual', 'qr_code') NOT NULL DEFAULT 'face_recognition',
    image_path VARCHAR(255) DEFAULT NULL,
    duration_minutes INT DEFAULT 0,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'danger', 'success') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    sent_via ENUM('system', 'email', 'sms', 'all') NOT NULL DEFAULT 'system',
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    log_level ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT DEFAULT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    seen_at DATETIME DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE SET NULL
);

CREATE TABLE leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    instructor_id INT NOT NULL,
    leave_type ENUM('sick', 'personal', 'official', 'emergency', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE special_days (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    type ENUM('holiday','suspension','no_classes') NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_special_date (date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE chat_typing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    conversation_with INT NOT NULL,
    is_typing TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_typing (user_id, conversation_with),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_with) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE calls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    caller_id INT NOT NULL,
    callee_id INT NOT NULL,
    type ENUM('audio', 'video') NOT NULL DEFAULT 'audio',
    status ENUM('ringing', 'connected', 'ended', 'rejected', 'missed') NOT NULL DEFAULT 'ringing',
    started_at DATETIME DEFAULT NULL,
    ended_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (callee_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE call_signals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    call_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    signal_type VARCHAR(20) NOT NULL,
    signal_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
);

CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    permission_key VARCHAR(100) UNIQUE NOT NULL,
    permission_name VARCHAR(200) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_perm (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_permissions_user ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_perm ON user_permissions(permission_id);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_attendance_instructor ON attendance_logs(instructor_id);
CREATE INDEX idx_attendance_timestamp ON attendance_logs(timestamp);
CREATE INDEX idx_attendance_status ON attendance_logs(status);
CREATE INDEX idx_classroom_presence_instructor ON classroom_presence(instructor_id);
CREATE INDEX idx_classroom_presence_timestamp ON classroom_presence(timestamp);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_schedules_instructor ON schedules(instructor_id);
CREATE INDEX idx_schedules_day ON schedules(day_of_week);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_receiver ON messages(receiver_id);
CREATE INDEX idx_messages_read ON messages(is_read);
CREATE INDEX idx_messages_created ON messages(created_at);

INSERT INTO users (username, password, email, first_name, last_name, role, status) VALUES
('admin', '$2y$10$HaW3iX7/eUa1QrZZ65zg3eaiQxXVl3NVQvBIOnAjjegss67nMOmS.', 'admin@sunn.edu', 'System', 'Administrator', 'admin', 'active'),
('jsmith', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'john.smith@sunn.edu', 'John', 'Smith', 'instructor', 'active'),
('mjane', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'jane.doe@sunn.edu', 'Jane', 'Doe', 'instructor', 'active'),
('rwilliams', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'r.williams@sunn.edu', 'Robert', 'Williams', 'instructor', 'active'),
('mbrown', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'm.brown@sunn.edu', 'Maria', 'Brown', 'instructor', 'active'),
('dhead1', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'dept.head@sunn.edu', 'David', 'Johnson', 'department_head', 'active'),
('student1', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'student1@sunn.edu', 'Alice', 'Garcia', 'student', 'active'),
('student2', '$2y$10$flViFoIq6G04RwmX3xzpGesTpZNhGRA6KqczvUuW9GWhpAmszsNg6', 'student2@sunn.edu', 'Bob', 'Martinez', 'student', 'active');

INSERT INTO departments (name, code, description) VALUES
('Computer Science', 'CS', 'Department of Computer Science and Information Technology'),
('Mathematics', 'MATH', 'Department of Mathematics and Statistics'),
('Engineering', 'ENG', 'Department of Engineering and Technology'),
('Business', 'BUS', 'Department of Business and Management');

INSERT INTO instructors (user_id, employee_id, department_id, specialization, phone, hire_date) VALUES
(2, 'FAC-001', 1, 'Software Engineering', '09171234567', '2022-06-01'),
(3, 'FAC-002', 1, 'Database Systems', '09172345678', '2021-08-15'),
(4, 'FAC-003', 2, 'Applied Mathematics', '09173456789', '2023-01-10'),
(5, 'FAC-004', 3, 'Electronics', '09174567890', '2022-11-20');

INSERT INTO subjects (name, code, department_id, units, description) VALUES
('Introduction to Programming', 'CS101', 1, 3, 'Fundamentals of programming using Python'),
('Data Structures', 'CS201', 1, 3, 'Advanced data structures and algorithms'),
('Database Management', 'CS301', 1, 3, 'Relational databases and SQL'),
('Calculus I', 'MATH101', 2, 3, 'Differential and integral calculus'),
('Linear Algebra', 'MATH201', 2, 3, 'Vector spaces and linear transformations'),
('Circuit Analysis', 'ENG101', 3, 3, 'Basic electrical circuit analysis'),
('Business Statistics', 'BUS101', 4, 3, 'Statistical methods for business');

INSERT INTO classrooms (name, building, floor, capacity) VALUES
('Room 101', 'Main Building', 1, 35),
('Room 102', 'Main Building', 1, 30),
('Room 201', 'Main Building', 2, 40),
('Room 202', 'Main Building', 2, 35),
('Lab A', 'Science Building', 1, 25),
('Lab B', 'Science Building', 2, 25);

INSERT INTO schedules (instructor_id, subject_id, classroom_id, section, day_of_week, time_start, time_end, semester, school_year) VALUES
(1, 1, 1, 'A', 'Monday', '08:00:00', '09:30:00', '1st Semester', '2025-2026'),
(1, 2, 3, 'A', 'Wednesday', '08:00:00', '09:30:00', '1st Semester', '2025-2026'),
(2, 3, 2, 'B', 'Monday', '10:00:00', '11:30:00', '1st Semester', '2025-2026'),
(2, 3, 5, 'B', 'Friday', '13:00:00', '14:30:00', '1st Semester', '2025-2026'),
(3, 4, 2, 'A', 'Tuesday', '08:00:00', '09:30:00', '1st Semester', '2025-2026'),
(3, 5, 4, 'A', 'Thursday', '10:00:00', '11:30:00', '1st Semester', '2025-2026'),
(4, 6, 6, 'A', 'Tuesday', '13:00:00', '14:30:00', '1st Semester', '2025-2026');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('school_name', 'SUNN Faculty Monitoring System', 'Name of the school'),
('time_in_start', '07:00:00', 'Start of time-in window'),
('time_in_end', '08:30:00', 'End of time-in window'),
('late_threshold', '15', 'Minutes after schedule start considered late'),
('absent_threshold', '60', 'Minutes after schedule start considered absent'),
('face_recognition_enabled', '1', 'Enable face recognition'),
('recognition_threshold', '0.35', 'Face recognition confidence threshold'),
('notification_email', 'notifications@sunn.edu', 'System notification email'),
('semester', '1st Semester', 'Current semester'),
('school_year', '2025-2026', 'Current school year');

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
('special_days.manage', 'Manage Special Days', 'Special Days', 'Manage holidays, suspensions, and no-class days');

-- Assign ALL permissions to admin user (id=1)
INSERT INTO user_permissions (user_id, permission_id)
SELECT 1, id FROM permissions;
