<?php
/**
 * Check-in / Check-out Page
 * Employee Management System
 */

$pageTitle = 'Check In/Out';
require_once __DIR__ . '/../config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: " . APP_URL . "/admin/dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
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
    $clientTimestamp = $_POST['client_timestamp'] ?? null;
    
    // Use client timestamp if provided, otherwise use server time
    $currentTime = $clientTimestamp ? date('Y-m-d H:i:s', strtotime($clientTimestamp)) : date('Y-m-d H:i:s');
    
    if ($actionType === 'checkin') {
        // Check if already checked in today
        if ($todayAttendance && $todayAttendance['check_in_time']) {
            $message = 'You have already checked in today!';
            $messageType = 'warning';
        } elseif (!$latitude || !$longitude) {
            $message = 'Location is required for check-in! Please enable location access.';
            $messageType = 'danger';
        } else {
            // Save photo if provided
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
                // Update existing record
                $sql = "UPDATE attendance SET 
                        check_in_time = ?, 
                        check_in_photo = ?, 
                        check_in_latitude = ?, 
                        check_in_longitude = ?,
                        status = 'pending'
                        WHERE id = ?";
                executeQuery($sql, "ssddi", [$currentTime, $photoPath, $latitude, $longitude, $todayAttendance['id']]);
            } else {
                // Create new record
                $sql = "INSERT INTO attendance (employee_id, date, check_in_time, check_in_photo, check_in_latitude, check_in_longitude, status) 
                        VALUES (?, CURDATE(), ?, ?, ?, ?, 'pending')";
                executeQuery($sql, "issdd", [$userId, $currentTime, $photoPath, $latitude, $longitude]);
            }
            
            // Get employee details including team lead
            $employee = fetchOne("SELECT e.name, e.team_lead_id, tl.name as team_lead_name FROM employees e LEFT JOIN employees tl ON e.team_lead_id = tl.id WHERE e.id = ?", "i", [$userId]);
            
            // Create notification for admin
            executeQuery(
                "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'admin', ?, ?, 'info', ?)",
                "isss",
                [1, 'New Check-in', $employee['name'] . ' has checked in at ' . formatTime($currentTime, 'h:i A') . ' from location: ' . ($latitude ? round($latitude, 4) . ', ' . round($longitude, 4) : 'Not available'), '/admin/approvals.php']
            );
            
            // Create notification for team lead if assigned
            if ($employee['team_lead_id']) {
                executeQuery(
                    "INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES (?, 'employee', ?, ?, 'info', ?)",
                    "isss",
                    [$employee['team_lead_id'], 'Team Member Check-in', $employee['name'] . ' has checked in at ' . formatTime($currentTime, 'h:i A') . ' from location: ' . ($latitude ? round($latitude, 4) . ', ' . round($longitude, 4) : 'Not available'), '/teamlead/approvals.php']
                );
            }
            
            logActivity('Check-in', 'Employee checked in at ' . formatTime($currentTime, 'h:i:s A'));
            
            $message = 'Check-in successful at ' . formatTime($currentTime, 'h:i:s A') . '! Waiting for admin approval.';
            $messageType = 'success';
            
            // Refresh attendance data
            $todayAttendance = fetchOne(
                "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
                "i",
                [$userId]
            );
        }
    } elseif ($actionType === 'checkout') {
        if (!$todayAttendance || !$todayAttendance['check_in_time']) {
            $message = 'You must check in first!';
            $messageType = 'danger';
        } elseif ($todayAttendance['check_out_time']) {
            $message = 'You have already checked out today!';
            $messageType = 'warning';
        } elseif (!$latitude || !$longitude) {
            $message = 'Location is required for check-out! Please enable location access.';
            $messageType = 'danger';
        } else {
            // Calculate total hours using client timestamp
            $checkInTime = strtotime($todayAttendance['check_in_time']);
            $checkOutTimeStamp = $clientTimestamp ? strtotime($clientTimestamp) : time();
            $totalSeconds = $checkOutTimeStamp - $checkInTime;
            
            // Convert to decimal hours (not TIME format)
            $totalHours = round($totalSeconds / 3600, 2);
            $totalHoursFormatted = gmdate('H:i:s', $totalSeconds); // For display only
            
            $sql = "UPDATE attendance SET 
                    check_out_time = ?, 
                    check_out_latitude = ?, 
                    check_out_longitude = ?,
                    total_hours = ?
                    WHERE id = ?";
            executeQuery($sql, "sddsi", [$currentTime, $latitude, $longitude, $totalHours, $todayAttendance['id']]);
            
            logActivity('Check-out', 'Employee checked out at ' . formatTime($currentTime, 'h:i:s A') . '. Total hours: ' . $totalHours);
            
            $message = 'Check-out successful at ' . formatTime($currentTime, 'h:i:s A') . '! Total hours: ' . $totalHoursFormatted;
            $messageType = 'success';
            
            // Refresh attendance data
            $todayAttendance = fetchOne(
                "SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()",
                "i",
                [$userId]
            );
        }
    }
}

$showCheckin = !$todayAttendance || !$todayAttendance['check_in_time'];
$showCheckout = $todayAttendance && $todayAttendance['check_in_time'] && !$todayAttendance['check_out_time'];
$completed = $todayAttendance && $todayAttendance['check_out_time'];

if ($action === 'checkout' && $showCheckout) {
    $showCheckin = false;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/employee-sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <button class="sidebar-toggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">Check In / Out</h1>
        </div>
        <div class="header-right">
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown">
                    <img src="<?php echo getAvatar($_SESSION['avatar'] ?? ''); ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="user-role">Employee</div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/employee/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Content -->
    <div class="content-wrapper">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'x-circle' : 'exclamation-circle'); ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($completed): ?>
                <!-- Day Complete Card -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 80px;"></i>
                        </div>
                        <h2 class="mb-3">Day Complete!</h2>
                        <p class="text-muted mb-4">You have completed your attendance for today.</p>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1">Check In</h6>
                                    <h4 class="mb-0"><?php echo formatTime($todayAttendance['check_in_time']); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1">Check Out</h6>
                                    <h4 class="mb-0"><?php echo formatTime($todayAttendance['check_out_time']); ?></h4>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-light rounded">
                                    <h6 class="text-muted mb-1">Total Hours</h6>
                                    <h4 class="mb-0"><?php echo $todayAttendance['total_hours']; ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <span class="badge badge-<?php echo $todayAttendance['status']; ?> fs-6 px-3 py-2">
                                <i class="bi bi-<?php echo $todayAttendance['status'] === 'approved' ? 'check-circle' : ($todayAttendance['status'] === 'rejected' ? 'x-circle' : 'clock'); ?> me-1"></i>
                                <?php echo ucfirst($todayAttendance['status']); ?>
                            </span>
                        </div>
                        
                        <a href="<?php echo APP_URL; ?>/employee/dashboard.php" class="btn btn-primary mt-4">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php elseif ($showCheckout): ?>
                <!-- Check Out Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-box-arrow-right me-2"></i>Check Out</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <p class="text-muted mb-2">You checked in at <strong><?php echo formatTime($todayAttendance['check_in_time']); ?></strong></p>
                            <div class="checkin-card mx-auto" style="max-width: 280px; padding: 15px 20px;">
                                <h6 class="mb-1" style="font-size: 0.85rem;">Working Time</h6>
                                <div class="time-display" id="workingTime" style="font-size: 1.5rem;">00:00:00</div>
                                <small class="text-white-50 d-block mt-1" style="font-size: 0.7rem;">Current: <span id="currentTimeDisplay">--:--:--</span></small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Your Location <span class="badge bg-danger">Required</span></h6>
                                <button type="button" class="btn btn-outline-success btn-sm" id="activateCheckoutLocationBtn">
                                    <i class="bi bi-geo-alt me-1"></i>Get Location
                                </button>
                            </div>
                            <div id="locationStatus" class="alert alert-secondary">
                                <i class="bi bi-geo me-2"></i>Location not captured. Click "Get Location" to enable.
                            </div>
                        </div>
                        
                        <form method="POST" id="checkoutForm">
                            <input type="hidden" name="action_type" value="checkout">
                            <input type="hidden" name="latitude" id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                            <input type="hidden" name="client_timestamp" id="client_timestamp">
                            
                            <button type="submit" class="btn btn-danger btn-lg w-100" id="checkoutBtn" disabled>
                                <i class="bi bi-box-arrow-right me-2"></i>Check Out Now
                            </button>
                        </form>
                    </div>
                </div>
                
                <script>
                // Calculate working time
                const checkInTime = new Date('<?php echo $todayAttendance['check_in_time']; ?>').getTime();
                const activateCheckoutLocationBtn = document.getElementById('activateCheckoutLocationBtn');
                
                function updateWorkingTime() {
                    const now = new Date();
                    const diff = now.getTime() - checkInTime;
                    
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    document.getElementById('workingTime').textContent = 
                        String(hours).padStart(2, '0') + ':' + 
                        String(minutes).padStart(2, '0') + ':' + 
                        String(seconds).padStart(2, '0');
                    
                    // Update current time display
                    document.getElementById('currentTimeDisplay').textContent = 
                        now.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true});
                }
                
                setInterval(updateWorkingTime, 1000);
                updateWorkingTime();
                
                // Activate Location Button Click for Checkout
                activateCheckoutLocationBtn.addEventListener('click', function() {
                    activateCheckoutLocationBtn.disabled = true;
                    activateCheckoutLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Getting...';
                    const statusDiv = document.getElementById('locationStatus');
                    statusDiv.className = 'alert alert-info';
                    statusDiv.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Getting your location...';
                    
                    getLocation(function(result) {
                        if (result.success) {
                            document.getElementById('latitude').value = result.latitude;
                            document.getElementById('longitude').value = result.longitude;
                            
                            statusDiv.className = 'alert alert-success';
                            statusDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>Location captured: ' + 
                                result.latitude.toFixed(6) + ', ' + result.longitude.toFixed(6);
                            
                            activateCheckoutLocationBtn.className = 'btn btn-success btn-sm';
                            activateCheckoutLocationBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Location Set';
                            document.getElementById('checkoutBtn').disabled = false;
                        } else {
                            statusDiv.className = 'alert alert-danger';
                            statusDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + result.error + ' - Location is required for check-out!';
                            activateCheckoutLocationBtn.disabled = false;
                            activateCheckoutLocationBtn.innerHTML = '<i class="bi bi-geo-alt me-1"></i>Retry Location';
                        }
                    });
                });
                
                // Set client timestamp on form submit
                document.getElementById('checkoutForm').addEventListener('submit', function(e) {
                    const now = new Date();
                    document.getElementById('client_timestamp').value = now.toISOString();
                });
                </script>
                
                <?php else: ?>
                <!-- Check In Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Check In</h5>
                    </div>
                    <div class="card-body">
                        <!-- Time Display -->
                        <div class="text-center mb-4">
                            <div class="checkin-card mx-auto" style="max-width: 280px; padding: 15px 20px;">
                                <h6 class="mb-1" style="font-size: 0.85rem;">Current Time</h6>
                                <div class="time-display" id="liveTimeDisplay" style="font-size: 1.5rem;">00:00:00</div>
                                <div class="date-display" id="liveDateDisplay" style="font-size: 0.75rem;"></div>
                                <small class="text-white-50 d-block mt-1" style="font-size: 0.7rem;">This time will be recorded</small>
                            </div>
                        </div>
                        
                        <!-- Camera Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-camera me-2"></i>Photo Verification</h6>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="activateCameraBtn">
                                    <i class="bi bi-camera-video me-1"></i>Activate Camera
                                </button>
                            </div>
                            <div id="cameraSection" style="display: none;">
                                <div class="camera-container mb-3">
                                    <video id="camera" autoplay playsinline></video>
                                    <canvas id="canvas" style="display: none;"></canvas>
                                    <div class="camera-overlay">
                                        <button type="button" class="btn btn-light" id="captureBtn">
                                            <i class="bi bi-camera me-2"></i>Capture Photo
                                        </button>
                                    </div>
                                </div>
                                <div id="photoPreview" class="text-center" style="display: none;">
                                    <img id="capturedPhoto" class="img-fluid rounded" style="max-height: 200px;">
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="retakeBtn">
                                        <i class="bi bi-arrow-repeat me-2"></i>Retake Photo
                                    </button>
                                </div>
                            </div>
                            <div id="cameraStatus" class="alert alert-secondary">
                                <i class="bi bi-camera-video-off me-2"></i>Camera not activated. Click "Activate Camera" to enable.
                            </div>
                        </div>
                        
                        <!-- Location Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Location Verification <span class="badge bg-danger">Required</span></h6>
                                <button type="button" class="btn btn-outline-success btn-sm" id="activateLocationBtn">
                                    <i class="bi bi-geo-alt me-1"></i>Get Location
                                </button>
                            </div>
                            <div id="locationStatus" class="alert alert-secondary">
                                <i class="bi bi-geo me-2"></i>Location not captured. Click "Get Location" to enable.
                            </div>
                        </div>
                        
                        <!-- Check In Form -->
                        <form method="POST" id="checkinForm">
                            <input type="hidden" name="action_type" value="checkin">
                            <input type="hidden" name="latitude" id="checkin_latitude">
                            <input type="hidden" name="longitude" id="checkin_longitude">
                            <input type="hidden" name="photo" id="photo">
                            <input type="hidden" name="client_timestamp" id="checkin_timestamp">
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100" id="checkinBtn" disabled>
                                <i class="bi bi-box-arrow-in-right me-2"></i>Check In Now
                            </button>
                        </form>
                    </div>
                </div>
                
                <script>
                let photoTaken = false;
                let locationReady = false;
                let cameraActivated = false;
                const video = document.getElementById('camera');
                const canvas = document.getElementById('canvas');
                const captureBtn = document.getElementById('captureBtn');
                const retakeBtn = document.getElementById('retakeBtn');
                const checkinBtn = document.getElementById('checkinBtn');
                const photoPreview = document.getElementById('photoPreview');
                const capturedPhoto = document.getElementById('capturedPhoto');
                const cameraStatus = document.getElementById('cameraStatus');
                const cameraSection = document.getElementById('cameraSection');
                const activateCameraBtn = document.getElementById('activateCameraBtn');
                const activateLocationBtn = document.getElementById('activateLocationBtn');
                
                // Update live time display
                function updateLiveTime() {
                    const now = new Date();
                    document.getElementById('liveTimeDisplay').textContent = now.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                    document.getElementById('liveDateDisplay').textContent = now.toLocaleDateString('en-US', {
                        weekday: 'short',
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                }
                updateLiveTime();
                setInterval(updateLiveTime, 1000);
                
                // Activate Camera Button Click
                activateCameraBtn.addEventListener('click', function() {
                    if (cameraActivated) return;
                    
                    activateCameraBtn.disabled = true;
                    activateCameraBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Starting...';
                    cameraStatus.className = 'alert alert-info';
                    cameraStatus.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Initializing camera...';
                    
                    startCamera(video).then(() => {
                        cameraActivated = true;
                        cameraSection.style.display = 'block';
                        cameraStatus.className = 'alert alert-success';
                        cameraStatus.innerHTML = '<i class="bi bi-check-circle me-2"></i>Camera ready! Capture your photo.';
                        activateCameraBtn.style.display = 'none';
                    }).catch(error => {
                        cameraStatus.className = 'alert alert-danger';
                        cameraStatus.innerHTML = '<i class="bi bi-x-circle me-2"></i>Camera access denied. Please allow camera permission.';
                        activateCameraBtn.disabled = false;
                        activateCameraBtn.innerHTML = '<i class="bi bi-camera-video me-1"></i>Retry Camera';
                    });
                });
                
                // Activate Location Button Click
                activateLocationBtn.addEventListener('click', function() {
                    activateLocationBtn.disabled = true;
                    activateLocationBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Getting...';
                    const locationStatusDiv = document.getElementById('locationStatus');
                    locationStatusDiv.className = 'alert alert-info';
                    locationStatusDiv.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Getting your location...';
                    
                    getLocation(function(result) {
                        if (result.success) {
                            document.getElementById('checkin_latitude').value = result.latitude;
                            document.getElementById('checkin_longitude').value = result.longitude;
                            
                            locationStatusDiv.className = 'alert alert-success';
                            locationStatusDiv.innerHTML = '<i class="bi bi-check-circle me-2"></i>Location captured: ' + 
                                result.latitude.toFixed(6) + ', ' + result.longitude.toFixed(6) +
                                ' (Accuracy: ' + Math.round(result.accuracy) + 'm)';
                            
                            activateLocationBtn.className = 'btn btn-success btn-sm';
                            activateLocationBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Location Set';
                            locationReady = true;
                        } else {
                            locationStatusDiv.className = 'alert alert-danger';
                            locationStatusDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + result.error + ' - Location is required for check-in!';
                            activateLocationBtn.disabled = false;
                            activateLocationBtn.innerHTML = '<i class="bi bi-geo-alt me-1"></i>Retry Location';
                            locationReady = false; // Location is mandatory
                        }
                        
                        updateCheckinButton();
                    });
                });
                
                // Capture photo
                captureBtn.addEventListener('click', function() {
                    const photoData = capturePhoto(video, canvas);
                    document.getElementById('photo').value = photoData;
                    capturedPhoto.src = photoData;
                    
                    video.style.display = 'none';
                    photoPreview.style.display = 'block';
                    captureBtn.parentElement.style.display = 'none';
                    photoTaken = true;
                    
                    updateCheckinButton();
                });
                
                // Retake photo
                retakeBtn.addEventListener('click', function() {
                    video.style.display = 'block';
                    photoPreview.style.display = 'none';
                    captureBtn.parentElement.style.display = 'flex';
                    photoTaken = false;
                    document.getElementById('photo').value = '';
                    
                    updateCheckinButton();
                });
                
                function updateCheckinButton() {
                    // Require both photo and location for check-in
                    checkinBtn.disabled = !(photoTaken && locationReady);
                }
                
                // Set client timestamp on form submit
                document.getElementById('checkinForm').addEventListener('submit', function(e) {
                    const now = new Date();
                    document.getElementById('checkin_timestamp').value = now.toISOString();
                });
                
                // Stop camera when leaving page
                window.addEventListener('beforeunload', function() {
                    stopCamera();
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
