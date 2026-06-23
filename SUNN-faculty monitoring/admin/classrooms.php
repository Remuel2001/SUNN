<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Manage Classrooms';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $name = sanitize($_POST['name']); $building = sanitize($_POST['building'] ?? '');
    $floor = $_POST['floor'] ?? 1; $capacity = $_POST['capacity'] ?? 30;
    $camera_ip = sanitize($_POST['camera_ip'] ?? '');
    try {
        if ($action === 'add') {
            $db->prepare("INSERT INTO classrooms (name, building, floor, capacity, camera_ip) VALUES (?,?,?,?,?)")->execute([$name, $building, $floor, $capacity, $camera_ip]);
            $success = 'Classroom added';
        } elseif ($action === 'edit') {
            $db->prepare("UPDATE classrooms SET name=?, building=?, floor=?, capacity=?, camera_ip=? WHERE id=?")->execute([$name, $building, $floor, $capacity, $camera_ip, $_POST['id']]);
            $success = 'Classroom updated';
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM classrooms WHERE id=?")->execute([$_POST['id']]);
            $success = 'Classroom deleted';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$classrooms = $db->query("SELECT c.*, (SELECT COUNT(*) FROM schedules WHERE classroom_id=c.id AND status='active') as active_schedules FROM classrooms c ORDER BY building, name")->fetchAll();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="bi bi-door-open me-2"></i>Classrooms</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="$('#roomAction').val('add'); $('#roomId').val(''); $('#roomName').val(''); $('#roomBuilding').val(''); $('#roomFloor').val(1); $('#roomCapacity').val(30); $('#roomCamera').val('');"><i class="bi bi-plus-lg"></i> Add Classroom</button>
    </div>
    <?php if (isset($success)): ?><div class="alert alert-success auto-dismiss"><?= $success ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger auto-dismiss"><?= $error ?></div><?php endif; ?>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover datatable mb-0">
                <thead class="table-light">
                    <tr><th>Name</th><th>Building</th><th>Floor</th><th>Capacity</th><th>Camera IP</th><th>Schedules</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['building']) ?></td>
                        <td><?= $r['floor'] ?></td>
                        <td><?= $r['capacity'] ?></td>
                        <td><code><?= htmlspecialchars($r['camera_ip'] ?: 'N/A') ?></code></td>
                        <td><span class="badge bg-info"><?= $r['active_schedules'] ?></span></td>
                        <td><?= getStatusBadge($r['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editRoom(<?= json_encode($r) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" style="display:inline" class="delete-record">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
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

<div class="modal fade" id="roomModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Classroom</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="roomAction"><input type="hidden" name="id" id="roomId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Room Name</label><input type="text" name="name" id="roomName" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Building</label><input type="text" name="building" id="roomBuilding" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Floor</label><input type="number" name="floor" id="roomFloor" class="form-control" value="1"></div>
                    <div class="col-md-4"><label class="form-label">Capacity</label><input type="number" name="capacity" id="roomCapacity" class="form-control" value="30"></div>
                    <div class="col-md-4"><label class="form-label">Camera IP</label><input type="text" name="camera_ip" id="roomCamera" class="form-control" placeholder="192.168.1.100"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

<script>
function editRoom(r) { $('#roomAction').val('edit'); $('#roomId').val(r.id); $('#roomName').val(r.name); $('#roomBuilding').val(r.building); $('#roomFloor').val(r.floor); $('#roomCapacity').val(r.capacity); $('#roomCamera').val(r.camera_ip); new bootstrap.Modal(document.getElementById('roomModal')).show(); }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
