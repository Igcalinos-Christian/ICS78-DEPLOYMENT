<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . "/connect.php";

// We don't strictly require a logged-in user here because the device itself
// might send data without a session. But we do check that the device is
// actually assigned to the room.
// If you want to lock it down to logged-in users only, uncomment the next block.
/*
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "msg" => "Unauthorized"]);
    exit;
}
*/

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['room_id'], $data['device_id'], $data['occupants'])) {
    echo json_encode(["status" => "error", "msg" => "Missing parameters"]);
    exit;
}

$room_id = intval($data['room_id']);
$device_id = intval($data['device_id']);
$occupants = intval($data['occupants']);

if ($occupants < 0) {
    echo json_encode(["status" => "error", "msg" => "Invalid occupant count"]);
    exit;
}

try {
    // Verify that this device is assigned to this room
    $stmt = $pdo->prepare("SELECT device_id FROM room_occupants WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["status" => "error", "msg" => "Room not found"]);
        exit;
    }

    if ((int)$row['device_id'] !== $device_id) {
        echo json_encode(["status" => "error", "msg" => "Device not assigned to this room"]);
        exit;
    }

    // Update occupant count
    $update = $pdo->prepare("UPDATE room_occupants SET no_of_occupants = ? WHERE room_id = ?");
    $update->execute([$occupants, $room_id]);

    // Log the reading
    $action = "Reported occupants: $occupants for room $room_id";
    // Use the device's own user_id for logging (triggers the activity_logs -> device_logs trigger)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$device_id, $action, $ip, $ua, $page]);

    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "Database error: " . $e->getMessage()]);
}
?>