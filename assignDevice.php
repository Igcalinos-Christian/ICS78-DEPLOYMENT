<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . "/connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "msg" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data || !isset($data['room_id']) || !isset($data['device_id'])) {
    echo json_encode(["status" => "error", "msg" => "Missing parameters"]);
    exit;
}

$room_id = intval($data['room_id']);
$device_id = intval($data['device_id']);

try {
    // Verify the device is indeed a device account
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$device_id]);
    $device = $check->fetch();
    if (!$device || $device['role'] !== 'device') {
        echo json_encode(["status" => "error", "msg" => "Invalid device"]);
        exit;
    }

    // Update the room_occupants row
    $stmt = $pdo->prepare("UPDATE room_occupants SET device_id = ? WHERE room_id = ?");
    $stmt->execute([$device_id, $room_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["status" => "error", "msg" => "Room not found in occupants table"]);
        exit;
    }

    // Log the assignment
    $current_user = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$current_user, "Assigned device $device_id to room $room_id", $ip, $ua, $page]);

    echo json_encode(["status" => "success"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "msg" => "Database error: " . $e->getMessage()]);
}
?>