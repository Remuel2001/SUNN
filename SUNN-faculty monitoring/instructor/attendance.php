<?php
require_once __DIR__ . '/../config/config.php';
requireRole('instructor');
$page_title = 'Attendance';
$db = getDB();
$instructor = getInstructorByUserId();
if (!$instructor) { echo '<div class="alert alert-danger m-4">Instructor profile not found. Please contact the admin to set up your instructor record.</div>'; require_once __DIR__ . '/../includes/footer.php'; exit; }
$instructor_id = $instructor['id'];

$today_schedules = getDaySchedule($instructor_id, date('l'));
$current_time = date('H:i:s');
$active_schedule_id = null;
foreach ($today_schedules as $s) {
    if ($current_time >= $s['time_start'] && $current_time <= $s['time_end']) {
        $active_schedule_id = $s['id'];
        break;
    }
}

$schedule_attendance = [];
foreach ($today_schedules as $s) {
    $sid = $s['id'];
    $in = $db->prepare("SELECT id, timestamp, status, image_path FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_in' LIMIT 1");
    $in->execute([$instructor_id, $sid]);
    $s['time_in_record'] = $in->fetch();
    $out = $db->prepare("SELECT id, timestamp, status, image_path FROM attendance_logs WHERE instructor_id=? AND schedule_id=? AND DATE(timestamp)=CURDATE() AND type='time_out' LIMIT 1");
    $out->execute([$instructor_id, $sid]);
    $s['time_out_record'] = $out->fetch();
    $schedule_attendance[] = $s;
}

$monthly_logs = $db->prepare("
    SELECT a.*, sub.name as subject_name
    FROM attendance_logs a
    LEFT JOIN schedules s ON a.schedule_id = s.id
    LEFT JOIN subjects sub ON s.subject_id = sub.id
    WHERE a.instructor_id = ? AND MONTH(a.timestamp) = MONTH(CURDATE()) AND YEAR(a.timestamp) = YEAR(CURDATE())
    ORDER BY a.timestamp DESC
");
$monthly_logs->execute([$instructor_id]);
$monthly_logs = $monthly_logs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 fade-in">
        <div>
            <h4 class="mb-1"><i class="bi bi-clock me-2 text-primary"></i>Attendance</h4>
            <p class="text-muted mb-0 small">Face recognition — look at the camera to auto clock in/out</p>
        </div>
    </div>
    <?php
    $today_special = isSpecialDay();
    if ($today_special):
        $type_labels = ['holiday' => 'Holiday', 'suspension' => 'Classes Suspended', 'no_classes' => 'No Classes'];
        $icons = ['holiday' => 'bi-calendar-heart', 'suspension' => 'bi-exclamation-triangle', 'no_classes' => 'bi-calendar-x'];
    ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 fade-in">
        <i class="bi <?= $icons[$today_special['type']] ?> fs-5"></i>
        <div><strong><?= $type_labels[$today_special['type']] ?></strong> &mdash; <?= htmlspecialchars($today_special['reason'] ?: 'No classes scheduled today.') ?></div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card fade-in border-0 shadow-sm" style="animation-delay:.1s;background:linear-gradient(135deg,#1e1b4b 0%,#3730a3 100%)">
                <div class="card-header bg-transparent border-bottom border-white border-opacity-10">
                    <span class="text-white fw-semibold"><i class="bi bi-camera-fill me-2"></i>Face Scanner</span>
                </div>
                <div class="card-body text-center px-2 py-3">
                    <div class="face-capture-container mb-2 position-relative" style="border-radius:16px;overflow:hidden;border:3px solid rgba(255,255,255,0.15)">
                        <video id="video" autoplay playsinline muted style="width:100%;display:block;min-height:220px;background:#0f0d2e"></video>
                        <div class="face-overlay"></div>
                        <div class="position-absolute bottom-0 start-0 w-100 text-center py-1" style="z-index:3">
                            <small class="text-white-50 small" style="text-shadow:0 1px 4px rgba(0,0,0,.6)"><i class="bi bi-camera-video me-1"></i>Look at the camera to auto clock</small>
                        </div>
                        <canvas id="overlayCanvas" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none;z-index:2"></canvas>
                    </div>
                    <div id="faceStatus" class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-2" style="border-radius:10px">
                        <i class="bi bi-info-circle"></i>
                        <span>Initializing camera...</span>
                    </div>
                    <div id="clockResult" class="d-none alert py-2 small mb-2" style="border-radius:10px"></div>

                    <?php if (empty($today_schedules)): ?>
                    <div class="text-center py-3">
                        <div style="width:56px;height:56px;background:rgba(255,255,255,0.08);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
                            <i class="bi bi-calendar-x" style="font-size:1.8rem;color:rgba(255,255,255,0.4)"></i>
                        </div>
                        <p class="text-white-50 small mb-0">No classes scheduled for today</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($active_schedule_id): ?>
                    <div class="mt-2">
                        <span class="badge bg-success bg-opacity-25 text-white px-3 py-2">
                            <i class="bi bi-play-fill me-1"></i>Class in session — auto clock ready
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($schedule_attendance)): ?>
            <div class="card fade-in mt-3 border-0 shadow-sm" style="animation-delay:.15s">
                <div class="card-header">
                    <span><i class="bi bi-calendar-check me-2 text-primary"></i>Today's Schedule — <?= date('l') ?></span>
                </div>
                <div class="card-body p-3">
                    <?php foreach ($schedule_attendance as $sa):
                        $is_active = $sa['id'] === $active_schedule_id;
                        $has_in = !empty($sa['time_in_record']);
                        $has_out = !empty($sa['time_out_record']);
                        $card_class = $is_active ? 'border-primary border-2' : '';
                        if ($has_out) { $sbadge = 'bg-secondary'; $stxt = 'Completed'; }
                        elseif ($has_in) { $sbadge = 'bg-success'; $stxt = 'Clocked In'; }
                        elseif ($is_active) { $sbadge = 'bg-warning text-dark'; $stxt = 'Now'; }
                        else { $sbadge = 'bg-light text-muted'; $stxt = 'Upcoming'; }
                    ?>
                    <div class="schedule-card rounded-3 border p-3 mb-2 <?= $card_class ?>" style="background:<?= $is_active ? 'var(--primary-bg)' : 'var(--gray-50)' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge <?= $sbadge ?>"><?= $stxt ?></span>
                                    <?php if ($is_active): ?><span class="badge bg-primary"><i class="bi bi-play-fill me-1"></i>Current</span><?php endif; ?>
                                </div>
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($sa['subject_name']) ?></h6>
                                <small class="text-muted d-block">
                                    <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($sa['time_start'])) ?> — <?= date('h:i A', strtotime($sa['time_end'])) ?>
                                    &middot; <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($sa['classroom_name'] ?? $sa['building'] ?? '—') ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($has_in): ?>
                        <div class="mt-2 pt-2 border-top small text-muted d-flex align-items-center gap-3">
                            <span><i class="bi bi-check-circle text-success me-1"></i>In: <?= date('h:i A', strtotime($sa['time_in_record']['timestamp'])) ?></span>
                            <?php if ($has_out): ?>
                            <span><i class="bi bi-check-circle text-secondary me-1"></i>Out: <?= date('h:i A', strtotime($sa['time_out_record']['timestamp'])) ?></span>
                            <?php endif; ?>
                            <?php if ($sa['time_in_record']['image_path']): ?>
                            <a href="#" onclick="viewEvidence('<?= $instructor_id ?>/<?= htmlspecialchars($sa['time_in_record']['image_path']) ?>');return false" class="text-decoration-none"><i class="bi bi-image me-1"></i>Photo</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <div class="card fade-in shadow-sm" style="animation-delay:.15s">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table me-2 text-primary"></i>Monthly Attendance — <?= date('F Y') ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>Date</th><th>Type</th><th>Status</th><th>Subject</th><th>Time</th><th>Evidence</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthly_logs)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No attendance records this month</td></tr>
                                <?php else: ?>
                                <?php foreach ($monthly_logs as $a): ?>
                                <tr>
                                    <td><small><?= formatDate($a['timestamp']) ?></small></td>
                                    <td><span class="badge bg-<?= $a['type']=='time_in'?'primary':'secondary' ?> bg-opacity-10"><?= $a['type'] == 'time_in' ? 'IN' : 'OUT' ?></span></td>
                                    <td><?= getStatusBadge($a['status']) ?></td>
                                    <td><small><?= htmlspecialchars($a['subject_name'] ?? '—') ?></small></td>
                                    <td><small class="text-muted"><?= date('h:i A', strtotime($a['timestamp'])) ?></small></td>
                                    <td>
                                        <?php if ($a['image_path']): ?>
                                        <a href="#" onclick="viewEvidence('<?= $a['instructor_id'] ?>/<?= htmlspecialchars($a['image_path']) ?>');return false">
                                            <img src="<?= BASE_URL ?>/uploads/faces/evidence/<?= $a['instructor_id'] ?>/<?= htmlspecialchars($a['image_path']) ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;border:2px solid var(--primary);cursor:pointer" onerror="this.style.cssText='display:none'">
                                        </a>
                                        <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="clockSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content text-center p-4" style="border-radius:20px;border:2px solid rgba(16,185,129,0.3)">
            <div class="mb-3">
                <div style="width:64px;height:64px;background:rgba(16,185,129,0.12);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:2rem"></i>
                </div>
            </div>
            <h5 class="fw-bold mb-0" id="clockSuccessType">Time In</h5>
            <p class="text-muted small mb-1" id="clockSuccessSubject">Class</p>
            <p class="text-muted small mb-3">Successfully recorded</p>
            <div class="mb-3">
                <img id="clockSuccessImg" class="img-fluid rounded-circle border border-3 border-success" style="width:100px;height:100px;object-fit:cover">
            </div>
            <div class="d-flex justify-content-center gap-3 mb-2">
                <div><small class="text-muted d-block">Date</small><span class="fw-semibold" id="clockSuccessDate"></span></div>
                <div><small class="text-muted d-block">Time</small><span class="fw-semibold" id="clockSuccessTime"></span></div>
            </div>
            <div class="mt-2">
                <span class="badge bg-success bg-opacity-10 text-success px-3 py-2" id="clockSuccessStatus">Present</span>
            </div>
            <div class="mt-3">
                <div class="progress" style="height:4px;border-radius:2px;background:var(--gray-200)">
                    <div class="progress-bar bg-success" style="width:100%;animation:shrink 3s linear forwards"></div>
                </div>
            </div>
            <style>
                @keyframes shrink { from { width:100% } to { width:0% } }
            </style>
        </div>
    </div>
</div>

<!-- Evidence Viewer Modal -->
<div class="modal fade" id="evViewerModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="text-end mb-2"><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <img id="evViewerImg" class="img-fluid rounded shadow">
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
<script src="<?= BASE_URL ?>/assets/js/face.js"></script>
<script>
const hasActiveClass = <?= json_encode($active_schedule_id !== null) ?>;
function viewEvidence(path) {
    let url = path;
    if (path.includes('/')) url = BASE_URL + '/uploads/faces/evidence/' + path;
    else if (!path.startsWith('http') && !path.startsWith('/') && !path.startsWith('..')) url = BASE_URL + '/uploads/faces/evidence/' + path;
    else if (!path.startsWith('http')) url = BASE_URL + '/' + path;
    $('#evViewerImg').attr('src', url);
    $('#evViewerModal').modal('show');
}

// ─── Auto Face Clock ───
let stream = null;
let faceDetected = false;
let autoClockDone = false;
let faceStableFrames = 0;
const video = document.getElementById('video');
const overlay = document.getElementById('overlayCanvas');

async function startCamera() {
    try {
        await FACE.startCamera(video, 480, 360);
        stream = FACE.stream;
        setTimeout(detectLoop, 500);
    } catch (e) {
        $('#faceStatus').html('<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Camera unavailable. ' + e.message + '</span>');
    }
}

async function detectLoop() {
    if (!video.videoWidth) { requestAnimationFrame(detectLoop); return; }
    if (!hasActiveClass) {
        if (!autoClockDone) { autoClockDone = true; $('#faceStatus').html('<span class="text-info"><i class="bi bi-info-circle me-1"></i>No active class right now.</span>'); }
        return;
    }
    try {
        const faces = await FACE.detectFace(video);
        const ctx = overlay.getContext('2d');
        overlay.width = video.videoWidth;
        overlay.height = video.videoHeight;

        if (faces && faces.length > 0 && !autoClockDone) {
            const f = faces[0];
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            ctx.strokeStyle = '#10b981'; ctx.lineWidth = 3;
            ctx.strokeRect(f.x, f.y, f.width, f.height);
            ctx.fillStyle = 'rgba(16,185,129,0.12)';
            ctx.fillRect(f.x, f.y, f.width, f.height);

            faceStableFrames++;
            if (faceStableFrames === 10) {
                $('#faceStatus').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Face stable — auto clocking...</span>');
                autoClock();
                return;
            }
            if (!faceDetected) {
                faceDetected = true;
                $('#faceStatus').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Face detected. Hold still...</span>');
            }
        } else {
            faceDetected = false;
            faceStableFrames = 0;
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            if (!autoClockDone) {
                $('#faceStatus').html('<span class="text-info"><i class="bi bi-info-circle me-1"></i>Look at the camera to auto clock</span>');
            }
        }
    } catch (e) {}
    if (!autoClockDone) requestAnimationFrame(detectLoop);
}

async function autoClock() {
    if (autoClockDone) return;
    autoClockDone = true;

    const frame = FACE.captureFrame(video);
    const capturedImage = frame.dataUrl;

    $('#faceStatus').html('<span class="text-primary"><span class="spinner-border spinner-border-sm me-1"></span>Verifying identity...</span>');

    const descriptor = await FACE.getDescriptorFromVideo(video);
    if (!descriptor || descriptor.length === 0) {
        $('#faceStatus').html('<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Could not extract face features. Try again.</span>');
        return;
    }

    $.ajax({
        url: BASE_URL + '/api/face_recognition.php',
        method: 'POST',
        data: { action: 'verify', descriptor: JSON.stringify(descriptor), instructor_id: <?= $instructor_id ?> },
        success: function (vr) {
            if (!vr.match) {
                $('#faceStatus').html('<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Face not recognized (similarity: ' + vr.similarity + '%).</span>');
                return;
            }
            $('#faceStatus').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Identity confirmed. Checking schedule...</span>');

            $.ajax({
                url: BASE_URL + '/api/attendance.php',
                method: 'POST',
                data: { action: 'detect_schedule' },
                success: function (sr) {
                    if (!sr.success) {
                        $('#faceStatus').html('<span class="text-warning"><i class="bi bi-info-circle me-1"></i>' + (sr.message || 'No active class') + '</span>');
                        return;
                    }
                    if (!sr.can_clock_in && !sr.can_clock_out) {
                        $('#faceStatus').html('<span class="text-info"><i class="bi bi-check-circle me-1"></i>Already completed for ' + sr.schedule.subject_name + '.</span>');
                        return;
                    }
                    const clockType = sr.can_clock_in ? 'clock_in' : 'clock_out';
                    const label = sr.can_clock_in ? 'Time In' : 'Time Out';
                    $('#faceStatus').html('<span class="text-success"><i class="bi bi-check-circle me-1"></i>Auto ' + label + ' for ' + sr.schedule.subject_name + '...</span>');

                    $.ajax({
                        url: BASE_URL + '/api/attendance.php',
                        method: 'POST',
                        data: { action: clockType, schedule_id: sr.schedule.id, face_data_url: capturedImage },
                        success: function (cr) {
                            if (cr.success) {
                                showClockSuccess(label, capturedImage, cr.timestamp, cr.status, cr.subject);
                            } else {
                                $('#faceStatus').html('<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (cr.message || 'Clock failed') + '</span>');
                            }
                        },
                        error: function () {
                            $('#faceStatus').html('<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Server error.</span>');
                        }
                    });
                },
                error: function () {
                    $('#faceStatus').html('<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Schedule check failed.</span>');
                }
            });
        },
        error: function () {
            $('#faceStatus').html('<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Verification server error.</span>');
        }
    });
}

function showClockSuccess(label, img, timestamp, status, subject) {
    document.getElementById('clockSuccessType').textContent = label;
    document.getElementById('clockSuccessSubject').textContent = subject || 'Class';
    document.getElementById('clockSuccessImg').src = img;
    const d = new Date();
    document.getElementById('clockSuccessDate').textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('clockSuccessTime').textContent = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('clockSuccessStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
    const modal = new bootstrap.Modal(document.getElementById('clockSuccessModal'));
    modal.show();
    setTimeout(function () { modal.hide(); location.reload(); }, 3000);
}

startCamera();
$(window).on('beforeunload', function () { FACE.stopCamera(); });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
