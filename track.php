<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';

$did = (int)($_GET['id'] ?? 0);
if (!$did) { header("Location: " . BASE_URL . "dashboard.php"); exit(); }

// Fetch delivery and order details
$stmt = mysqli_prepare($conn, 
    "SELECT d.*, o.order_number, o.customer_id, o.status AS order_status, u.province AS customer_province
     FROM deliveries d
     JOIN orders o ON d.order_id = o.id
     JOIN users u ON o.customer_id = u.id
     WHERE d.id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $did);
mysqli_stmt_execute($stmt);
$delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$delivery) { die("Delivery not found."); }

// Security Check: Customer owner OR Admin/HO OR relevant RDC Staff
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];
$authorized = false;

if ($role === 'customer' && $delivery['customer_id'] == $uid) {
    $authorized = true;
} elseif (in_array($role, ['admin', 'ho_manager'])) {
    $authorized = true;
} elseif ($role === 'rdc_staff' && $_SESSION['province'] === $delivery['customer_province']) {
    $authorized = true;
} elseif ($role === 'logistics_staff' && $delivery['assigned_to'] == $uid) {
    $authorized = true;
}

if (!$authorized) {
    header("Location: " . BASE_URL . "includes/unauthorized.php");
    exit();
}

// Check if this is an AJAX request for coordinates
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'lat' => (float)$delivery['current_lat'],
        'lng' => (float)$delivery['current_lng'],
        'status' => $delivery['status']
    ]);
    exit();
}

$page_title = "Track Delivery - " . $delivery['delivery_number'];
include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="page-card">
    <div class="page-card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-geo-alt-fill me-2"></i>Live Tracking: <?= htmlspecialchars($delivery['delivery_number']) ?></h5>
        <span id="status-badge" class="badge badge-<?= $delivery['status'] ?>"><?= ucfirst(str_replace('_',' ',$delivery['status'])) ?></span>
    </div>
    
    <div id="map" style="height: 500px; border-radius: 8px; border: 1px solid #ddd; margin: 15px;"></div>

    <div class="p-3 bg-light border-top">
        <div class="row text-center">
            <div class="col-md-4">
                <p class="text-muted small mb-0">Order Number</p>
                <p class="fw-bold mb-0">#<?= htmlspecialchars($delivery['order_number']) ?></p>
            </div>
            <div class="col-md-4">
                <p class="text-muted small mb-0">Driver / Vehicle</p>
                <p class="fw-bold mb-0"><?= htmlspecialchars($delivery['driver_name']) ?> (<?= htmlspecialchars($delivery['vehicle_number']) ?>)</p>
            </div>
            <div class="col-md-4">
                <p class="text-muted small mb-0">Last Location Update</p>
                <p id="update-time" class="fw-bold mb-0">Waiting...</p>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map, marker;
let deliveryId = <?= $did ?>;

function initMap() {
    // Default to center of Sri Lanka if no coords
    const initialLat = <?= $delivery['current_lat'] ?: 7.8731 ?>;
    const initialLng = <?= $delivery['current_lng'] ?: 80.7718 ?>;

    map = L.map('map').setView([initialLat, initialLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    marker = L.marker([initialLat, initialLng]).addTo(map)
        .bindPopup('Logistics Officer is here')
        .openPopup();
}

function pollLocation() {
    fetch(`track.php?id=${deliveryId}&ajax=1`)
        .then(response => response.json())
        .then(data => {
            if (data.lat && data.lng) {
                const newPos = [data.lat, data.lng];
                marker.setLatLng(newPos);
                map.panTo(newPos);
                document.getElementById('update-time').textContent = new Date().toLocaleTimeString();
                
                // Update status badge
                const badge = document.getElementById('status-badge');
                badge.className = `badge badge-${data.status}`;
                badge.textContent = data.status.replace(/_/g, ' ').charAt(0).toUpperCase() + data.status.replace(/_/g, ' ').slice(1);
            }
        })
        .catch(err => console.error('Polling Error:', err));
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    setInterval(pollLocation, 5000); // 5 seconds
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
