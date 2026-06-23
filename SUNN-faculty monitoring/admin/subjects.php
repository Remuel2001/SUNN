<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Subjects';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $name = sanitize($_POST['name']); $code = sanitize($_POST['code']);
    $dept_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
    $units = $_POST['units'] ?? 3;
    try {
        if ($action === 'add') {
            $db->prepare("INSERT INTO subjects (name, code, department_id, units) VALUES (?,?,?,?)")->execute([$name, $code, $dept_id, $units]);
            $success = 'Subject added';
        } elseif ($action === 'edit') {
            $db->prepare("UPDATE subjects SET name=?, code=?, department_id=?, units=? WHERE id=?")->execute([$name, $code, $dept_id, $units, $_POST['id']]);
            $success = 'Subject updated';
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$_POST['id']]);
            $success = 'Subject deleted';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$subjects = $db->query("SELECT s.*, d.name as dept_name FROM subjects s LEFT JOIN departments d ON s.department_id=d.id ORDER BY s.name")->fetchAll();
$departments = $db->query("SELECT * FROM departments WHERE status='active'")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-book me-2"></i>Subjects</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal" onclick="resetSubjectForm()"><i class="bi bi-plus-lg"></i> Add Subject</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>ID</th><th>Code</th><th>Name</th><th>Department</th><th>Units</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($s['code']) ?></span></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['dept_name'] ?? 'N/A') ?></td>
                        <td><?= $s['units'] ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editSubject(<?= json_encode($s) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" style="display:inline" class="delete-record">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
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

<div class="modal fade" id="subjectModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title"><span id="subModalTitle">Add Subject</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="subAction" value="add"><input type="hidden" name="id" id="subId">
                <div class="mb-3"><label class="form-label">Subject Name</label><input type="text" name="name" id="subName" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" id="subCode" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Department</label><select name="department_id" id="subDept" class="form-select"><option value="">None</option><?php foreach($departments as $d): ?><option value="<?=$d['id']?>"><?=htmlspecialchars($d['name'])?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label">Units</label><input type="number" name="units" id="subUnits" class="form-control" value="3" min="1" max="6"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<script>
function resetSubjectForm() { $('#subAction').val('add'); $('#subId').val(''); $('#subName').val(''); $('#subCode').val(''); $('#subDept').val(''); $('#subUnits').val(3); $('#subModalTitle').text('Add Subject'); }
function editSubject(s) { $('#subAction').val('edit'); $('#subId').val(s.id); $('#subName').val(s.name); $('#subCode').val(s.code); $('#subDept').val(s.department_id); $('#subUnits').val(s.units); $('#subModalTitle').text('Edit Subject'); new bootstrap.Modal(document.getElementById('subjectModal')).show(); }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
