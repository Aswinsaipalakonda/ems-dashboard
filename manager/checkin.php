<?php
/**
 * Manager - Check-in / Check-out Page
 * Employee Management System
 */

$pageTitle = 'Check In/Out';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (!isManager() && !isHR()) {
    header("Location: " . APP_URL . "/employee/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get today's attendance
$todayAttendance = fetchOne(
    "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
    "i",
    [$userId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = $_POST['action_type'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $photo = $_POST['photo'] ?? null;
    
    if ($actionType === 'checkin') {
        if ($todayAttendance && $todayAttendance['check_in_time']) {
            $message = 'You have already checked in today!';
            $messageType = 'warning';
        } else {
            $photoPath = null;
            if ($photo) {
                $photoData = explode(',', $photo);
                if (count($photoData) > 1) {
                    $photoDecoded = base64_decode($photoData[1]);
                    $photoDir = __DIR__ . '/../uploads/checkin/';
                    if (!is_dir($photoDir)) {
                        mkdir($photoDir, 0777, true);
                    }
                    $photoName = $userId . '_' . date('Y-m-d_H-i-s') . '.jpg';
                    file_put_contents($photoDir . $photoName, $photoDecoded);
                    $photoPath = $photoName;
                }
            }
            
            if ($todayAttendance) {
                $sql = "UPDATE attendance SET check_in_time = NOW(), check_in_photo = ?, check_in_latitude = ?, check_in_longitude = ?, status = 'pending' WHERE id = ?";
                executeQuery($sql, "sddi", [$photoPath, $latitude, $longitude, $todayAttendance['id']]);
            } else {
                $sql = "INSERT INTO attendance (employee_id, date, check_in_time, check_in_photo, check_in_latitude, check_in_longitude, status) VALUES (?, CURDATE(), NOW(), ?, ?, ?, 'pending')";
                executeQuery($sql, "isdd", [$userId, $photoPath, $latitude, $longitude]);
            }
            
            logActivity('checkin', 'Manager/HR checked in');
            $message = 'Check-in successful!';
            $messageType = 'success';
            
            $todayAttendance = fetchOne("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()", "i", [$userId]);
        }
    } elseif ($actionType === 'checkout') {
        if (!$todayAttendance || !$todayAttendance['check_in_time']) {
            $message = 'You must check in first!';
            $messageType = 'danger';
        } elseif ($todayAttendance['check_out_time']) {
            $message = 'You have already checked out today!';
            $messageType = 'warning';
        } else {
            $checkInTime = strtotime($todayAttendance['check_in_time']);
            $totalSeconds = time() - $checkInTime;
            $totalHours = gmdate('H:i:s', $totalSeconds);
            
            $sql = "UPDATE attendance SET check_out_time = NOW(), check_out_latitude = ?, check_out_longitude = ?, total_hours = ? WHERE id = ?";
            executeQuery($sql, "ddsi", [$latitude, $longitude, $totalHours, $todayAttendance['id']]);
            
            logActivity('checkout', 'Manager/HR checked out. Total hours: ' . $totalHours);
            $message = 'Check-out successful! Total hours: ' . $totalHours;
            $messageType = 'success';
            
            $todayAttendance = fetchOne("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()", "i", [$userId]);
        }
    }
}

$showCheckin = !$todayAttendance || !$todayAttendance['check_in_time'];
$showCheckout = $todayAttendance && $todayAttendance['check_in_time'] && !$todayAttendance['check_out_time'];
$completed = $todayAttendance && $todayAttendance['check_out_time'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/manager-sidebar.php';
?>

<div class="main-content">
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle"><i class="bi bi-list"></i></button>
            <h1 class="page-title">Check In/Out</h1>
        </div>
    </header>

    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Status Card -->
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="mb-3">
                            <i class="bi bi-<?php echo $completed ? 'check-circle-fill text-success' : ($showCheckout ? 'clock-fill text-warning' : 'circle text-secondary'); ?>" style="font-size: 4rem;"></i>
                        </div>
                        <h4><?php echo date('l, F j, Y'); ?></h4>
                        <p class="text-muted mb-0">
                            <?php if ($completed): ?>
                                Work completed for today! Total hours: <?php echo $todayAttendance['total_hours']; ?>
                            <?php elseif ($showCheckout): ?>
                                You checked in at <?php echo formatTime($todayAttendance['check_in_time']); ?>
                            <?php else: ?>
                                You haven't checked in yet
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!$completed): ?>
                <!-- Check In/Out Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-<?php echo $showCheckin ? 'box-arrow-in-right' : 'box-arrow-right'; ?> me-2"></i>
                            <?php echo $showCheckin ? 'Check In' : 'Check Out'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="attendanceForm">
                            <input type="hidden" name="action_type" value="<?php echo $showCheckin ? 'checkin' : 'checkout'; ?>">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="photo" id="photo">
                            
                            <?php if ($showCheckin): ?>
                            <!-- Camera Preview -->
                            <div class="mb-4">
                                <label class="form-label">Take a Photo (Optional)</label>
                                <div class="camera-container">
                                    <video id="video" class="w-100 rounded mb-2" autoplay playsinline style="max-height: 300px; background: #000;"></video>
                                    <canvas id="canvas" style="display: none;"></canvas>
                                    <img id="preview" class="w-100 rounded mb-2" style="display: none; max-height: 300px;">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="startCamera">
                                        <i class="bi bi-camera me-1"></i>Start Camera
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="captureBtn" style="display: none;">
                                        <i class="bi bi-camera-fill me-1"></i>Capture
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" id="retakeBtn" style="display: none;">
                                        <i class="bi bi-arrow-repeat me-1"></i>Retake
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Location -->
                            <div class="mb-4">
                                <label class="form-label">Location</label>
                                <div class="d-flex align-items-center p-3 bg-light rounded">
                                    <i class="bi bi-geo-alt text-primary me-2" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <div id="locationStatus">Click to get location...</div>
                                        <small class="text-muted" id="locationCoords"></small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="getLocationBtn">
                                    <i class="bi bi-crosshair me-1"></i>Get Location
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-<?php echo $showCheckin ? 'success' : 'danger'; ?> btn-lg w-100">
                                <i class="bi bi-<?php echo $showCheckin ? 'box-arrow-in-right' : 'box-arrow-right'; ?> me-2"></i>
                                <?php echo $showCheckin ? 'Check In Now' : 'Check Out Now'; ?>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Today's Record -->
                <?php if ($todayAttendance): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Today's Record</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <h6 class="text-muted">Check In</h6>
                                <h5><?php echo $todayAttendance['check_in_time'] ? formatTime($todayAttendance['check_in_time']) : '-'; ?></h5>
                            </div>
                            <div class="col-4">
                                <h6 class="text-muted">Check Out</h6>
                                <h5><?php echo $todayAttendance['check_out_time'] ? formatTime($todayAttendance['check_out_time']) : '-'; ?></h5>
                            </div>
                            <div class="col-4">
                                <h6 class="text-muted">Status</h6>
                                <span class="badge badge-<?php echo $todayAttendance['status']; ?>"><?php echo ucfirst($todayAttendance['status']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let stream = null;
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const preview = document.getElementById('preview');
const startCameraBtn = document.getElementById('startCamera');
const captureBtn = document.getElementById('captureBtn');
const retakeBtn = document.getElementById('retakeBtn');
const photoInput = document.getElementById('photo');

if (startCameraBtn) {
    startCameraBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream;
            video.style.display = 'block';
            startCameraBtn.style.display = 'none';
            captureBtn.style.display = 'inline-block';
        } catch (err) {
            alert('Camera access denied. Photo is optional.');
        }
    });

    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        preview.src = dataUrl;
        photoInput.value = dataUrl;
        video.style.display = 'none';
        preview.style.display = 'block';
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'inline-block';
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    });

    retakeBtn.addEventListener('click', async () => {
        preview.style.display = 'none';
        photoInput.value = '';
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        video.srcObject = stream;
        video.style.display = 'block';
        retakeBtn.style.display = 'none';
        captureBtn.style.display = 'inline-block';
    });
}

document.getElementById('getLocationBtn').addEventListener('click', () => {
    document.getElementById('locationStatus').textContent = 'Getting location...';
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                document.getElementById('locationStatus').textContent = 'Location captured successfully!';
                document.getElementById('locationCoords').textContent = `Lat: ${position.coords.latitude.toFixed(6)}, Lng: ${position.coords.longitude.toFixed(6)}`;
            },
            (error) => {
                document.getElementById('locationStatus').textContent = 'Location access denied';
            }
        );
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
