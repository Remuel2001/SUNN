<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Departments';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $name = sanitize($_POST['name']);
    $code = sanitize($_POST['code']);
    $description = sanitize($_POST['description'] ?? '');

    try {
        if ($action === 'add') {
            $db->prepare("INSERT INTO departments (name, code, description) VALUES (?,?,?)")->execute([$name, $code, $description]);
            logActivity('Add Department', "Added department: $name");
            $success = 'Department added';
        } elseif ($action === 'edit') {
            $db->prepare("UPDATE departments SET name=?, code=?, description=? WHERE id=?")->execute([$name, $code, $description, $_POST['id']]);
            $success = 'Department updated';
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM departments WHERE id=?")->execute([$_POST['id']]);
            $success = 'Department deleted';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$departments = $db->query("SELECT d.*, (SELECT COUNT(*) FROM instructors WHERE department_id=d.id) as instructor_count FROM departments d ORDER BY name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-building me-2"></i>Departments</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="$('#formAction').val('add'); $('#deptId').val(''); $('#deptName').val(''); $('#deptCode').val(''); $('#deptDesc').val('');">
            <i class="bi bi-plus-lg"></i> Add Department
        </button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>ID</th><th>Name</th><th>Code</th><th>Instructors</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $d): ?>
                    <tr>
                        <td><?= $d['id'] ?></td>
                        <td><a href="javascript:void(0)" class="text-decoration-none fw-semibold" onclick="showInstructors(<?= $d['id'] ?>, <?= htmlspecialchars(json_encode($d['name']), ENT_QUOTES) ?>)"><?= htmlspecialchars($d['name']) ?></a></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($d['code']) ?></span></td>
                        <td><a href="javascript:void(0)" onclick="showInstructors(<?= $d['id'] ?>, <?= htmlspecialchars(json_encode($d['name']), ENT_QUOTES) ?>)"><?= $d['instructor_count'] ?></a></td>
                        <td><?= getStatusBadge($d['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editDept(<?= htmlspecialchars(json_encode($d)) ?>)"><i class="bi bi-pencil"></i></button>
                            <form method="POST" style="display:inline" class="delete-record">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
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

<div class="modal fade" id="deptModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="deptId">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="deptName" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" id="deptCode" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="deptDesc" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="instructorListModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i><span id="deptModalTitle">Instructors</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="instructorListBody">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function editDept(d) {
    $('#formAction').val('edit'); $('#deptId').val(d.id); $('#deptName').val(d.name); $('#deptCode').val(d.code); $('#deptDesc').val(d.description);
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}

function showInstructors(deptId, deptName) {
    $('#deptModalTitle').text(deptName + ' - Instructors');
    $('#instructorListBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
    new bootstrap.Modal(document.getElementById('instructorListModal')).show();
    var tid = setTimeout(function() { $('#instructorListBody').html('<div class="text-center py-5 text-muted"><i class="bi bi-exclamation-circle fs-1 d-block mb-2"></i>Request timed out. Please try again.</div>'); }, 10000);
    $.get(BASE_URL + '/api/get_department_instructors.php?department_id=' + deptId, function(res) {
        clearTimeout(tid);
        if (!res.success) {
            $('#instructorListBody').html('<div class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>' + (res.message || 'Error loading instructors') + '</div>');
            return;
        }
        if (!res.instructors || !res.instructors.length) {
            $('#instructorListBody').html('<div class="text-center py-5 text-muted"><i class="bi bi-info-circle fs-1 d-block mb-2"></i>No instructors in this department</div>');
            return;
        }
        var html = '<table class="table table-hover mb-0"><thead class="table-light"><tr><th>Name</th><th>Email</th><th>Employee ID</th><th>Specialization</th><th>Face</th><th>Status</th></tr></thead><tbody>';
        $.each(res.instructors, function(i, inst) {
            var faceBadge = inst.face_count > 0 ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Registered</span>' : '<span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>None</span>';
            var statusBadge = inst.user_status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
            html += '<tr><td><img src="https://ui-avatars.com/api/?name=' + encodeURIComponent(inst.first_name + '+' + inst.last_name) + '&size=28&background=4e73df&color=fff" class="rounded-circle me-2" style="width:28px;height:28px">' + (inst.first_name || '') + ' ' + (inst.last_name || '') + '</td><td>' + (inst.email || '-') + '</td><td>' + (inst.employee_id || '-') + '</td><td>' + (inst.specialization || '-') + '</td><td>' + faceBadge + '</td><td>' + statusBadge + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#instructorListBody').html(html);
    }).fail(function() {
        clearTimeout(tid);
        $('#instructorListBody').html('<div class="text-center py-5 text-muted"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>Failed to load instructors. Check your connection.</div>');
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
