<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Instructors';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $user_id = $_POST['user_id'] ?? null;
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $username = sanitize($_POST['username']);
        $employee_id = sanitize($_POST['employee_id']);
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $phone = sanitize($_POST['phone'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');

        try {
            if ($action === 'add') {
                $password = password_hash($_POST['password'] ?? 'password123', PASSWORD_DEFAULT);
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?,?,?,?,?,'instructor')");
                $stmt->execute([$username, $password, $email, $first_name, $last_name]);
                $new_user_id = $db->lastInsertId();
                $stmt = $db->prepare("INSERT INTO instructors (user_id, employee_id, department_id, phone, specialization) VALUES (?,?,?,?,?)");
                $stmt->execute([$new_user_id, $employee_id, $department_id, $phone, $specialization]);
                $db->commit();
                logActivity('Add Instructor', "Added instructor: $first_name $last_name ($employee_id)");
                $success = 'Instructor added successfully';
            } else {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
                $stmt->execute([$first_name, $last_name, $email, $user_id]);
                $stmt = $db->prepare("UPDATE instructors SET employee_id=?, department_id=?, phone=?, specialization=? WHERE user_id=?");
                $stmt->execute([$employee_id, $department_id, $phone, $specialization, $user_id]);
                $db->commit();
                logActivity('Edit Instructor', "Updated instructor: $first_name $last_name");
                $success = 'Instructor updated successfully';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            logActivity('Delete Instructor', "Deleted instructor ID: $id");
            $success = 'Instructor deleted successfully';
        } catch (Exception $e) {
            $error = 'Cannot delete: ' . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$status, $id]);
        $success = 'Status updated';
    }
}

$instructors = $db->query("
    SELECT u.*, i.*, d.name as department_name,
           (SELECT COUNT(*) FROM facial_data WHERE instructor_id=i.id AND status='active') as face_count
    FROM users u
    JOIN instructors i ON u.id = i.user_id
    LEFT JOIN departments d ON i.department_id = d.id
    WHERE u.role = 'instructor'
    ORDER BY u.last_name ASC
")->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status='active'")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold"><i class="bi bi-people-fill me-2"></i>Manage Instructors</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#instructorModal" onclick="resetForm()">
            <i class="bi bi-plus-lg me-1"></i>Add Instructor
        </button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0">
                    <thead class="table-light">
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Employee ID</th><th>Department</th><th>Face</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instructors as $inst): ?>
                        <tr>
                            <td><?= $inst['id'] ?></td>
                            <td>
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($inst['first_name'].'+'.$inst['last_name']) ?>&size=32&background=4e73df&color=fff"
                                     class="rounded-circle me-2" style="width:32px;height:32px">
                                <?= htmlspecialchars($inst['first_name'].' '.$inst['last_name']) ?>
                            </td>
                            <td><?= htmlspecialchars($inst['email']) ?></td>
                            <td><?= htmlspecialchars($inst['employee_id']) ?></td>
                            <td><?= htmlspecialchars($inst['department_name'] ?? 'N/A') ?></td>
                            <td><?= $inst['face_count'] > 0 ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Registered</span>' : '<span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>None</span>' ?></td>
                            <td><?= getStatusBadge($inst['status']) ?></td>
                            <td>
                            <a href="<?= BASE_URL ?>/admin/face_registration.php?instructor=<?= $inst['id'] ?>" class="btn btn-sm btn-outline-success" title="Register Face">
                                    <i class="bi bi-camera-fill"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-primary" onclick='editInstructor(<?= json_encode($inst) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $inst['user_id'] ?>">
                                    <input type="hidden" name="status" value="<?= $inst['status']=='active'?'inactive':'active' ?>">
                                    <button class="btn btn-sm btn-outline-<?= $inst['status']=='active'?'warning':'success' ?>">
                                        <i class="bi bi-<?= $inst['status']=='active'?'pause':'play' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline" class="delete-record">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $inst['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="instructorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i><span id="modalTitle">Add Instructor</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="user_id" id="userId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" id="firstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" id="lastName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="employee_id" id="employeeId" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <small class="text-muted">(add only)</small></label>
                            <input type="text" name="password" id="password" class="form-control" placeholder="Default: password123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select name="department_id" id="departmentId" class="form-select">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" id="specialization" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Add Instructor';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('firstName').value = '';
    document.getElementById('lastName').value = '';
    document.getElementById('email').value = '';
    document.getElementById('username').value = '';
    document.getElementById('employeeId').value = '';
    document.getElementById('password').value = '';
    document.getElementById('departmentId').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('specialization').value = '';
    document.getElementById('password').required = false;
}

function editInstructor(data) {
    document.getElementById('modalTitle').textContent = 'Edit Instructor';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = data.user_id;
    document.getElementById('firstName').value = data.first_name;
    document.getElementById('lastName').value = data.last_name;
    document.getElementById('email').value = data.email;
    document.getElementById('username').value = data.username;
    document.getElementById('employeeId').value = data.employee_id;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('departmentId').value = data.department_id || '';
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('specialization').value = data.specialization || '';
    new bootstrap.Modal(document.getElementById('instructorModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
