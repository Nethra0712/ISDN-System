<?php
header('Content-Type: application/json');
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';

// Only logistics staff can update their location
if ($_SESSION['role'] !== 'logistics_staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$delivery_id = (int)($data['delivery_id'] ?? 0);
$lat = (float)($data['lat'] ?? 0);
$lng = (float)($data['lng'] ?? 0);
$uid = $_SESSION['user_id'];

if (!$delivery_id || !$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Ensure the staff member is assigned to this delivery
$stmt = mysqli_prepare($conn, "UPDATE deliveries SET current_lat=?, current_lng=? WHERE id=? AND assigned_to=?");
mysqli_stmt_bind_param($stmt, "ddii", $lat, $lng, $delivery_id, $uid);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB error']);
}
