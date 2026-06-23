<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Users';
$db = getDB();
$all_permissions = getAllPermissions();
$permission_modules = [];
$has_perms_table = !empty($all_permissions);
foreach ($all_permissions as $p) {
    $permission_modules[$p['module']][] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $username = sanitize($_POST['username']); $email = sanitize($_POST['email']);
    $first_name = sanitize($_POST['first_name']); $last_name = sanitize($_POST['last_name']);
    $role = $_POST['role']; $password = !empty($_POST['new_password']) ? $_POST['new_password'] : 'password123';
    $granted = $_POST['permissions'] ?? [];

    try {
        if ($action === 'add') {
            $db->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?,?,?,?,?,?)")
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email, $first_name, $last_name, $role]);
            $uid = $db->lastInsertId();
            if ($role === 'instructor') {
                $check = $db->prepare("SELECT COUNT(*) FROM instructors WHERE user_id=?");
                $check->execute([$uid]);
                if (!$check->fetchColumn()) {
                    $db->prepare("INSERT INTO instructors (user_id, employee_id) VALUES (?,?)")->execute([$uid, 'EMP-' . str_pad($uid, 4, '0', STR_PAD_LEFT)]);
                }
            }
            if ($role === 'department_head') {
                $check = $db->prepare("SELECT COUNT(*) FROM instructors WHERE user_id=?");
                $check->execute([$uid]);
                if (!$check->fetchColumn()) {
                    $db->prepare("INSERT INTO instructors (user_id, employee_id) VALUES (?,?)")->execute([$uid, 'EMP-' . str_pad($uid, 4, '0', STR_PAD_LEFT)]);
                }
            }
            if ($has_perms_table) syncUserPermissions($uid, $granted);
            $success = 'User added';
        } elseif ($action === 'edit') {
            if (!empty($_POST['new_password'])) {
                $db->prepare("UPDATE users SET username=?, email=?, first_name=?, last_name=?, role=?, password=? WHERE id=?")
                    ->execute([$username, $email, $first_name, $last_name, $role, password_hash($_POST['new_password'], PASSWORD_DEFAULT), $_POST['id']]);
            } else {
                $db->prepare("UPDATE users SET username=?, email=?, first_name=?, last_name=?, role=? WHERE id=?")
                    ->execute([$username, $email, $first_name, $last_name, $role, $_POST['id']]);
            }
            if ($role === 'instructor' || $role === 'department_head') {
                $check = $db->prepare("SELECT COUNT(*) FROM instructors WHERE user_id=?");
                $check->execute([$_POST['id']]);
                if (!$check->fetchColumn()) {
                    $db->prepare("INSERT INTO instructors (user_id, employee_id) VALUES (?,?)")->execute([$_POST['id'], 'EMP-' . str_pad($_POST['id'], 4, '0', STR_PAD_LEFT)]);
                }
            }
            if ($has_perms_table) syncUserPermissions($_POST['id'], $granted);
            $success = 'User updated';
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$_POST['id']]);
            $success = 'User deleted';
        } elseif ($action === 'toggle_status') {
            $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['id']]);
            $success = 'Status updated';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$users = $db->query("SELECT * FROM users ORDER BY role, last_name")->fetchAll();
$user_perm_map = [];
foreach ($users as $u) {
    $user_perm_map[$u['id']] = getUserPermissionKeys($u['id']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-person-badge me-2"></i>Users</h4>
        <button class="btn btn-primary" onclick="openAddModal()"><i class="bi bi-plus-lg"></i> Add User</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card"><div class="card-body p-0">
        <table class="table table-hover datatable mb-0">
            <thead class="table-light">
                <tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><img src="https://ui-avatars.com/api/?name=<?= urlencode($u['first_name'].'+'.$u['last_name']) ?>&size=32&background=4e73df&color=fff" class="rounded-circle me-2"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge bg-<?= $u['role']=='admin'?'danger':($u['role']=='instructor'?'primary':($u['role']=='student'?'success':'info')) ?>"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span></td>
                    <td><?= getStatusBadge($u['status']) ?></td>
                    <td><?= $u['last_login'] ? formatDateTime($u['last_login']) : 'Never' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="status" value="<?= $u['status']=='active'?'inactive':'active' ?>">
                            <button class="btn btn-sm btn-outline-<?= $u['status']=='active'?'warning':'success' ?>" title="Toggle Status"><i class="bi bi-<?= $u['status']=='active'?'pause':'play' ?>"></i></button>
                        </form>
                        <form method="POST" style="display:inline" class="delete-record">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="modal fade" id="userModal" data-bs-backdrop="static"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">
        <div class="modal-header"><h5 class="modal-title" id="modalTitle">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" name="action" id="userAction" value="add">
            <input type="hidden" name="id" id="userId" value="">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" id="ufname" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" id="ulname" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Username</label><input type="text" name="username" id="uusername" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="uemail" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Role</label>
                    <select name="role" id="urole" class="form-select" onchange="applyRolePreset()">
                        <option value="admin">Admin</option><option value="instructor">Instructor</option>
                        <option value="student">Student</option><option value="department_head">Department Head</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Password</label><input type="text" name="new_password" id="upass" class="form-control" placeholder="Default: password123"></div>
            </div>

            <?php if ($has_perms_table): ?>
            <hr class="my-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-semibold mb-0"><i class="bi bi-shield-check me-1 text-primary"></i>Custom Permissions</h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-success me-1" onclick="checkAllPerms()"><i class="bi bi-check-all"></i> All</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="uncheckAllPerms()"><i class="bi bi-x"></i> None</button>
                </div>
            </div>
            <p class="small text-muted mb-2">Select which features this user can access. Unchecked = denied.</p>
            <div class="small text-info mb-2"><i class="bi bi-info-circle me-1"></i>Run <code>database/migrate.php</code> to enable permission editing.</div>

            <div class="row g-2" id="permsContainer">
                <?php foreach ($permission_modules as $module => $perms): ?>
                <div class="col-md-6">
                    <div class="p-2 rounded border mb-2" style="background:var(--gray-50)">
                        <div class="form-check mb-1">
                            <input class="form-check-input module-check" type="checkbox" onchange="toggleModule('<?= $module ?>', this.checked)" id="mod_<?= $module ?>">
                            <label class="form-check-label fw-semibold small" for="mod_<?= $module ?>"><?= htmlspecialchars($module) ?></label>
                        </div>
                        <div class="ms-3" id="mod_<?= $module ?>_perms">
                            <?php foreach ($perms as $p): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="<?= $p['permission_key'] ?>" id="perm_<?= $p['id'] ?>" data-module="<?= $module ?>">
                                <label class="form-check-label small" for="perm_<?= $p['id'] ?>"><?= htmlspecialchars($p['permission_name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
        </div>
    </form>
</div></div></div>

<script>
const permPresets = {
    admin: <?= json_encode(array_column($all_permissions, 'permission_key')) ?>,
    instructor: [
        'dashboard.access', 'attendance.clock', 'attendance.view',
        'schedules.view', 'leave.create'
    ],
    student: [
        'dashboard.access'
    ],
    department_head: [
        'dashboard.access', 'attendance.view', 'instructors.view',
        'reports.view', 'schedules.view', 'leave.approve'
    ]
};

function applyRolePreset() {
    const role = document.getElementById('urole').value;
    const preset = permPresets[role] || [];
    document.querySelectorAll('.perm-check').forEach(cb => {
        cb.checked = preset.includes(cb.value);
    });
    updateModuleStates();
    updateSelectAllBtn();
}

function toggleModule(module, checked) {
    document.querySelectorAll(`.perm-check[data-module="${module}"]`).forEach(cb => { cb.checked = checked; });
    updateSelectAllBtn();
}

function updateModuleStates() {
    document.querySelectorAll('.module-check').forEach(modCb => {
        const module = modCb.id.replace('mod_', '');
        const perms = document.querySelectorAll(`.perm-check[data-module="${module}"]`);
        modCb.checked = perms.length > 0 && Array.from(perms).every(p => p.checked);
    });
}

function checkAllPerms() {
    document.querySelectorAll('.perm-check').forEach(cb => { cb.checked = true; });
    updateModuleStates();
}

function uncheckAllPerms() {
    document.querySelectorAll('.perm-check').forEach(cb => { cb.checked = false; });
    updateModuleStates();
}

function updateSelectAllBtn() {
    updateModuleStates();
}

document.getElementById('permsContainer').addEventListener('change', function(e) {
    if (e.target.classList.contains('perm-check')) {
        updateModuleStates();
    }
});

function openAddModal() {
    document.getElementById('userAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('modalTitle').textContent = 'Add User';
    document.querySelectorAll('.form-control, .form-select').forEach(el => el.value = '');
    document.getElementById('urole').value = 'instructor';
    applyRolePreset();
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function editUser(u) {
    document.getElementById('userAction').value = 'edit';
    document.getElementById('userId').value = u.id;
    document.getElementById('modalTitle').textContent = 'Edit User - ' + u.first_name + ' ' + u.last_name;
    document.getElementById('ufname').value = u.first_name;
    document.getElementById('ulname').value = u.last_name;
    document.getElementById('uusername').value = u.username;
    document.getElementById('uemail').value = u.email;
    document.getElementById('urole').value = u.role;
    document.getElementById('upass').value = '';

    $.get(BASE_URL + '/api/users.php', { action: 'get_permissions', user_id: u.id }, function(res) {
        if (res.success) {
            document.querySelectorAll('.perm-check').forEach(cb => {
                cb.checked = res.permissions.includes(cb.value);
            });
            updateModuleStates();
        }
    });

    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
