<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$page_title = 'Face Registration';
$db = getDB();

$instructors = $db->query("
    SELECT u.id as user_id, u.first_name, u.last_name, i.id as instructor_id, i.employee_id,
           d.name as department_name,
           (SELECT COUNT(*) FROM facial_data WHERE instructor_id=i.id AND status='active') as has_face,
           (SELECT MAX(confidence_score) FROM facial_data WHERE instructor_id=i.id AND status='active') as best_confidence
    FROM users u JOIN instructors i ON u.id = i.user_id
    LEFT JOIN departments d ON i.department_id = d.id
    WHERE u.role='instructor' AND u.status='active'
    ORDER BY u.last_name
")->fetchAll();

$instructor_id = $_GET['instructor'] ?? null;
$selected_instructor = null;
if ($instructor_id) {
    foreach ($instructors as $inst) {
        if ($inst['instructor_id'] == $instructor_id) { $selected_instructor = $inst; break; }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 fade-in">
        <div>
            <h4 class="mb-1"><i class="bi bi-camera-fill me-2 text-primary"></i>Face Registration</h4>
            <p class="text-muted mb-0 small">Register facial data for AI-powered instructor attendance</p>
        </div>
        <a href="<?= BASE_URL ?>/admin/instructors.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card fade-in" style="animation-delay:.1s">
                <div class="card-header"><i class="bi bi-person-video3 me-2 text-primary"></i>Select Instructor</div>
                <div class="card-body">
                    <form method="GET" id="selectForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Choose Instructor</label>
                            <select name="instructor" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Select Instructor --</option>
                                <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['instructor_id'] ?>"
                                    <?= $selected_instructor && $selected_instructor['instructor_id'] == $inst['instructor_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['first_name'].' '.$inst['last_name'].' ('.$inst['employee_id'].')') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php if ($selected_instructor): ?>
                    <div class="p-3 rounded-3" style="background:var(--gray-50)">
                        <div class="d-flex align-items-center">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($selected_instructor['first_name'].'+'.$selected_instructor['last_name']) ?>&size=56&background=4f46e5&color=fff"
                                 class="rounded-circle me-3" style="width:56px;height:56px">
                            <div>
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($selected_instructor['first_name'].' '.$selected_instructor['last_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($selected_instructor['employee_id']) ?></small><br>
                                <?php if ($selected_instructor['has_face'] > 0): ?>
                                    <span class="badge bg-success mt-1"><i class="bi bi-check-circle me-1"></i>Face Registered</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark mt-1"><i class="bi bi-exclamation-circle me-1"></i>No Face Registered</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selected_instructor): ?>
            <div class="card mt-3 fade-in" style="animation-delay:.15s">
                <div class="card-header"><i class="bi bi-info-circle me-2 text-info"></i>Registration Tips</div>
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-1-circle text-primary mt-1" style="font-size:.85rem"></i>
                        <small>Ensure good lighting — avoid shadows on your face</small>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-2-circle text-primary mt-1" style="font-size:.85rem"></i>
                        <small>Look directly at the camera with a neutral expression</small>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-3-circle text-primary mt-1" style="font-size:.85rem"></i>
                        <small>Remove glasses, mask, or hat for best accuracy</small>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-4-circle text-primary mt-1" style="font-size:.85rem"></i>
                        <small>Keep your face within the oval guide on screen</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <?php if ($selected_instructor): ?>
            <div class="card fade-in" style="animation-delay:.1s">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-camera me-2 text-primary"></i>Capture Face</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="useFileUpload" onchange="toggleMode()">
                        <label class="form-check-label small" for="useFileUpload">Upload photo</label>
                    </div>
                </div>
                <div class="card-body">
                    <div id="cameraSection">
                        <div class="face-capture-container mb-3">
                            <video id="video" autoplay playsinline muted></video>
                            <canvas id="overlayCanvas" class="position-absolute top-0 start-0 w-100 h-100" style="pointer-events:none"></canvas>
                            <div class="face-overlay"></div>
                        </div>
                        <div id="faceStatus" class="alert alert-info py-2 small d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle"></i>
                            <span>Position face within the circle and click <strong>Scan Face</strong></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button id="btnScan" class="btn btn-primary flex-grow-1" onclick="scanFace()">
                                <i class="bi bi-camera me-1"></i>Scan Face
                            </button>
                            <button id="btnRetake" class="btn btn-outline-secondary" onclick="retake()" style="display:none">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Retake
                            </button>
                        </div>
                        <div id="scanProgress" class="mt-2" style="display:none">
                            <div class="progress" style="height:6px">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">Analyzing face...</small>
                        </div>
                    </div>

                    <div id="fileSection" style="display:none">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Upload a clear photo</label>
                            <input type="file" id="fileInput" class="form-control" accept="image/*" onchange="handleFileUpload(event)">
                        </div>
                        <div id="filePreview" class="text-center mb-3" style="display:none">
                            <img id="filePreviewImg" class="img-fluid rounded-3" style="max-height:300px;box-shadow:var(--card-shadow)">
                        </div>
                    </div>

                    <div id="resultSection" style="display:none" class="mt-3">
                        <hr>
                        <h6 class="fw-semibold mb-3"><i class="bi bi-image me-2 text-primary"></i>Captured Face</h6>
                        <div class="text-center mb-3">
                            <img id="capturedPreview" class="img-fluid rounded-3 border" style="max-height:280px;box-shadow:var(--card-shadow)">
                        </div>
                        <div id="resultStatus" class="alert d-none py-2 small"></div>
                        <div id="encodingInfo" class="small text-muted text-center mb-2 d-none"></div>
                        <button id="btnRegister" class="btn btn-success btn-lg w-100 fw-semibold" onclick="registerFace()" disabled>
                            <i class="bi bi-shield-check me-1"></i>Register Face Data
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mt-3 fade-in" style="animation-delay:.2s">
                <div class="card-header"><i class="bi bi-clock-history me-2 text-muted"></i>Registration History</div>
                <div class="card-body p-0">
                    <?php
                    $history = $db->prepare("SELECT * FROM facial_data WHERE instructor_id=? ORDER BY created_at DESC LIMIT 5");
                    $history->execute([$selected_instructor['instructor_id']]);
                    $history = $history->fetchAll();
                    ?>
                    <?php if (empty($history)): ?>
                    <div class="empty-state py-4"><i class="bi bi-camera-off"></i><p class="mb-0">No face data registered yet</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Date</th><th>Confidence</th><th>Primary</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><small><?= formatDateTime($h['created_at']) ?></small></td>
                                    <td><small class="fw-semibold"><?= number_format($h['confidence_score'] * 100, 1) ?>%</small></td>
                                    <td><?= $h['is_primary'] ? '<span class="badge bg-success bg-opacity-10">Yes</span>' : '<span class="badge bg-secondary bg-opacity-10">No</span>' ?></td>
                                    <td><?= getStatusBadge($h['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="card fade-in" style="animation-delay:.1s">
                <div class="card-body text-center py-5">
                    <div style="width:80px;height:80px;background:var(--primary-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
                        <i class="bi bi-person-video3" style="font-size:2.5rem;color:var(--primary)"></i>
                    </div>
                    <h5 class="text-muted">Select an Instructor</h5>
                    <p class="text-muted small mb-0">Choose an instructor from the left panel to register their facial data.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($selected_instructor): ?>
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
<script src="<?= BASE_URL ?>/assets/js/face.js"></script>
<script>
let capturedImage = null;
let faceDescriptor = null;
let scanAttempts = 0;
let faceDetected = false;
const video = document.getElementById('video');
const overlay = document.getElementById('overlayCanvas');

async function startCamera() {
    try {
        await FACE.startCamera(video, 480, 360);
        setStatus('info', '<i class="bi bi-info-circle me-1"></i>Camera active. Position your face within the oval.');
        setTimeout(detectLoop, 500);
    } catch (e) {
        document.getElementById('useFileUpload').checked = true;
        toggleMode();
        setStatus('warning', '<i class="bi bi-exclamation-triangle me-1"></i>Camera unavailable. Please upload a photo.');
    }
}

async function detectLoop() {
    if (!video.videoWidth || document.getElementById('resultSection').style.display === 'block') {
        if (document.getElementById('resultSection').style.display !== 'block') requestAnimationFrame(detectLoop);
        return;
    }
    try {
        const faces = await FACE.detectFace(video);
        const ctx = overlay.getContext('2d');
        overlay.width = video.videoWidth;
        overlay.height = video.videoHeight;

        if (faces && faces.length > 0) {
            const f = faces[0];
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 3;
            ctx.strokeRect(f.x, f.y, f.width, f.height);
            ctx.fillStyle = 'rgba(16,185,129,0.12)';
            ctx.fillRect(f.x, f.y, f.width, f.height);
            ctx.fillStyle = '#10b981';
            ctx.font = 'bold 13px Inter, sans-serif';
            ctx.fillText('✓ FACE DETECTED', f.x + 6, f.y - 8);
            if (!faceDetected) {
                faceDetected = true;
                document.getElementById('btnScan').disabled = false;
                setStatus('success', '<i class="bi bi-check-circle me-1"></i>Face detected! Click <strong>Scan Face</strong> to capture.');
            }
        } else {
            faceDetected = false;
            document.getElementById('btnScan').disabled = true;
            setStatus('info', '<i class="bi bi-info-circle me-1"></i>Position your face in front of the camera');
        }
    } catch (e) { }
    requestAnimationFrame(detectLoop);
}

function setStatus(type, msg) {
    const el = document.getElementById('faceStatus');
    el.className = 'alert alert-' + type + ' py-2 small d-flex align-items-center gap-2';
    el.innerHTML = msg;
}

function toggleMode() {
    const useFile = document.getElementById('useFileUpload').checked;
    document.getElementById('cameraSection').style.display = useFile ? 'none' : 'block';
    document.getElementById('fileSection').style.display = useFile ? 'block' : 'none';
    resetUI();
    useFile ? stopCamera() : startCamera();
}

function stopCamera() { FACE.stopCamera(); }

function resetUI() {
    document.getElementById('resultSection').style.display = 'none';
    document.getElementById('btnScan').style.display = 'block';
    document.getElementById('btnRetake').style.display = 'none';
    document.getElementById('btnRegister').disabled = true;
    capturedImage = null;
    faceDescriptor = null;
}

function retake() { resetUI(); setStatus('info', '<i class="bi bi-info-circle me-1"></i>Position face within the circle and click <strong>Scan Face</strong>'); }

async function scanFace() {
    if (!FACE.stream) { showToast('Camera not available', 'error'); return; }

    const frame = FACE.captureFrame(video);
    capturedImage = frame.dataUrl;

    document.getElementById('scanProgress').style.display = 'block';
    setStatus('info', '<span class="spinner-border spinner-border-sm me-1"></span>Scanning face...');
    document.getElementById('btnScan').disabled = true;

    const descriptor = await FACE.getDescriptorFromVideo(video);
    document.getElementById('scanProgress').style.display = 'none';

    if (descriptor && descriptor.length > 0) {
        faceDescriptor = descriptor;
        document.getElementById('capturedPreview').src = capturedImage;
        document.getElementById('resultSection').style.display = 'block';
        document.getElementById('btnScan').style.display = 'none';
        document.getElementById('btnRetake').style.display = 'block';

        setStatus('success', '<i class="bi bi-check-circle me-1"></i>Face scanned successfully! <strong>'
            + descriptor.length + '</strong> feature points extracted.');
        document.getElementById('resultStatus').className = 'alert alert-success d-block py-2 small';
        document.getElementById('resultStatus').innerHTML = '<i class="bi bi-check-circle me-1"></i>Face detected and analyzed';
        document.getElementById('encodingInfo').className = 'small text-muted text-center mb-2 d-block';
        document.getElementById('encodingInfo').innerHTML = 'Feature vector: <strong>' + descriptor.length + ' dimensions</strong> · Ready to register';
        document.getElementById('btnRegister').disabled = false;
        showToast('Face scanned! Click Register to save.', 'success');
    } else {
        scanAttempts++;
        setStatus('danger', '<i class="bi bi-x-circle me-1"></i>No face detected. Try adjusting lighting and position.'
            + (scanAttempts >= 3 ? ' Or upload a photo instead.' : ''));
        document.getElementById('resultStatus').className = 'alert alert-danger d-block py-2 small';
        document.getElementById('resultStatus').innerHTML = '<i class="bi bi-x-circle me-1"></i>Could not detect a face in this image';
        document.getElementById('btnScan').disabled = false;

        if (scanAttempts >= 3) {
            document.getElementById('useFileUpload').checked = true;
            toggleMode();
        }
    }
}

async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async function (e) {
        capturedImage = e.target.result;
        document.getElementById('filePreview').style.display = 'block';
        document.getElementById('filePreviewImg').src = capturedImage;
        document.getElementById('capturedPreview').src = capturedImage;
        document.getElementById('resultSection').style.display = 'block';
        setStatus('info', '<span class="spinner-border spinner-border-sm me-1"></span>Analyzing uploaded photo...');

        const descriptor = await FACE.getDescriptor(capturedImage);
        if (descriptor && descriptor.length > 0) {
            faceDescriptor = descriptor;
            setStatus('success', '<i class="bi bi-check-circle me-1"></i>Face analyzed! <strong>' + descriptor.length + '</strong> feature points extracted.');
            document.getElementById('resultStatus').className = 'alert alert-success d-block py-2 small';
            document.getElementById('resultStatus').innerHTML = '<i class="bi bi-check-circle me-1"></i>Face detected in uploaded photo';
            document.getElementById('encodingInfo').className = 'small text-muted text-center mb-2 d-block';
            document.getElementById('encodingInfo').innerHTML = 'Ready to register <strong>' + file.name + '</strong>';
            document.getElementById('btnRegister').disabled = false;
        } else {
            setStatus('danger', '<i class="bi bi-x-circle me-1"></i>No face detected in this photo. Try a clearer image.');
            document.getElementById('btnRegister').disabled = true;
        }
    };
    reader.readAsDataURL(file);
}

async function registerFace() {
    if (!faceDescriptor || !capturedImage) {
        showToast('Please scan a face first', 'error');
        return;
    }

    const btn = document.getElementById('btnRegister');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Registering...';

    const formData = new FormData();
    formData.append('action', 'register');
    formData.append('instructor_id', <?= $selected_instructor['instructor_id'] ?>);
    formData.append('descriptor', JSON.stringify(faceDescriptor));
    formData.append('image', capturedImage);
    formData.append('save_image', '1');

    $.ajax({
        url: BASE_URL + '/api/face_recognition.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (res) {
            if (res.success) {
                setStatus('success', '<i class="bi bi-check-circle-fill me-1"></i> ' + res.message);
                document.getElementById('resultStatus').className = 'alert alert-success d-block py-2 small';
                document.getElementById('resultStatus').innerHTML = '<i class="bi bi-shield-check me-1"></i> Face data registered: ' + res.encoding_length + ' dimensions';
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Registered Successfully!';
                btn.className = 'btn btn-success btn-lg w-100 fw-semibold';
                showToast('Face data saved successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                setStatus('danger', '<i class="bi bi-x-circle me-1"></i> ' + (res.message || 'Registration failed'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-shield-check me-1"></i>Register Face Data';
                showToast(res.message || 'Registration failed', 'error');
            }
        },
        error: function () {
            setStatus('danger', '<i class="bi bi-x-circle me-1"></i> Server error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-check me-1"></i>Register Face Data';
            showToast('Server error', 'error');
        }
    });
}

startCamera();
$(window).on('beforeunload', function () { stopCamera(); });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
