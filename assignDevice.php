<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
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
    $current_user = $_SESSION['user_id'];

    // Role check
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$current_user]);
    $user = $check->fetch();
    if (!$user || !in_array($user['role'], ['manager', 'admin'])) {
        echo json_encode(["status" => "error", "msg" => "Forbidden"]);
        exit;
    }

    // Verify that the device is actually a device account
    $devCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $devCheck->execute([$device_id]);
    $device = $devCheck->fetch();
    if (!$device || $device['role'] !== 'device') {
        echo json_encode(["status" => "error", "msg" => "Invalid device"]);
        exit;
    }

    // Check if room_occupants row exists
    $checkRoom = $pdo->prepare("SELECT id FROM room_occupants WHERE room_id = ?");
    $checkRoom->execute([$room_id]);
    if ($checkRoom->fetch()) {
        // Update existing row
        $stmt = $pdo->prepare("UPDATE room_occupants SET device_id = ? WHERE room_id = ?");
        $stmt->execute([$device_id, $room_id]);
    } else {
        // Insert a new row with the room_id and device_id
        $stmt = $pdo->prepare("INSERT INTO room_occupants (room_id, no_of_occupants, device_id) VALUES (?, 0, ?)");
        $stmt->execute([$room_id, $device_id]);
    }

    // Log the assignment
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$current_user, "Assigned device $device_id to room $room_id", $ip, $ua, $page]);

    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "msg" => "Database error"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "Server error"]);
}
?>