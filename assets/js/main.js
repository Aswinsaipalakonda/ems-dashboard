/**
 * Main JavaScript
 * Employee Management System
 */

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const icons = {
        success: 'bi-check-circle-fill text-success',
        danger: 'bi-x-circle-fill text-danger',
        warning: 'bi-exclamation-circle-fill text-warning',
        info: 'bi-info-circle-fill text-info'
    };
    
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast fade-in" role="alert">
            <div class="toast-body">
                <i class="bi ${icons[type] || icons.info}"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 4000 });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    }
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
    
    // Auto-update time display
    const timeDisplay = document.querySelector('.time-display');
    if (timeDisplay) {
        updateTime();
        setInterval(updateTime, 1000);
    }
});

// Update time display
function updateTime() {
    const timeDisplay = document.querySelector('.time-display');
    const dateDisplay = document.querySelector('.date-display');
    
    if (timeDisplay) {
        const now = new Date();
        timeDisplay.textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
    }
    
    if (dateDisplay) {
        const now = new Date();
        dateDisplay.textContent = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}

// Get geolocation
function getLocation(callback) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                callback({
                    success: true,
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                });
            },
            (error) => {
                let message = 'Unable to get location';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Location permission denied';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Location information unavailable';
                        break;
                    case error.TIMEOUT:
                        message = 'Location request timed out';
                        break;
                }
                callback({ success: false, error: message });
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        callback({ success: false, error: 'Geolocation not supported' });
    }
}

// Camera functions
let cameraStream = null;

function startCamera(videoElement) {
    return new Promise((resolve, reject) => {
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }
        })
        .then(stream => {
            cameraStream = stream;
            videoElement.srcObject = stream;
            videoElement.play();
            resolve(stream);
        })
        .catch(error => {
            reject(error);
        });
    });
}

function capturePhoto(videoElement, canvasElement) {
    const context = canvasElement.getContext('2d');
    canvasElement.width = videoElement.videoWidth;
    canvasElement.height = videoElement.videoHeight;
    context.drawImage(videoElement, 0, 0);
    return canvasElement.toDataURL('image/jpeg', 0.8);
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
}

// Form validation helper
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// AJAX helper
function ajaxRequest(url, method, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    callback(null, response);
                } catch (e) {
                    callback(null, xhr.responseText);
                }
            } else {
                callback(new Error('Request failed'));
            }
        }
    };
    
    if (method === 'POST' && data) {
        const params = new URLSearchParams(data).toString();
        xhr.send(params);
    } else {
        xhr.send();
    }
}

// Confirm dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format time
function formatTime(time) {
    return new Date('1970-01-01T' + time).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

// Notification polling (check every 30 seconds)
function startNotificationPolling() {
    setInterval(() => {
        ajaxRequest(APP_URL + '/api/notifications.php?action=count', 'GET', null, (err, response) => {
            if (!err && response.count > 0) {
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.textContent = response.count;
                    badge.style.display = 'flex';
                }
            }
        });
    }, 30000);
}

// Session timeout warning
let sessionWarningShown = false;

function checkSessionTimeout() {
    const warningTime = 5 * 60 * 1000; // 5 minutes before timeout
    
    setInterval(() => {
        ajaxRequest(APP_URL + '/api/session.php?action=check', 'GET', null, (err, response) => {
            if (!err && response.remaining < warningTime && !sessionWarningShown) {
                sessionWarningShown = true;
                showToast('Your session will expire soon. Please save your work.', 'warning');
            }
        });
    }, 60000);
}

// Export functions for global use
window.showToast = showToast;
window.getLocation = getLocation;
window.startCamera = startCamera;
window.capturePhoto = capturePhoto;
window.stopCamera = stopCamera;
window.validateForm = validateForm;
window.ajaxRequest = ajaxRequest;
window.confirmAction = confirmAction;
