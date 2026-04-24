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
    if (!$data || !isset($data['device_id'])) {
        echo json_encode(["status" => "error", "msg" => "Missing device ID"]);
        exit;
    }

    $device_id = intval($data['device_id']);
    $current_user = $_SESSION['user_id'];

    // Role check – only manager/admin can delete devices
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$current_user]);
    $user = $check->fetch();
    if (!$user || !in_array($user['role'], ['manager', 'admin'])) {
        echo json_encode(["status" => "error", "msg" => "Forbidden"]);
        exit;
    }

    // Get device username for logging
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'device'");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        echo json_encode(["status" => "error", "msg" => "Device not found"]);
        exit;
    }

    // Delete device
    $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'device'");
    $delStmt->execute([$device_id]);

    // Log action
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->execute([$current_user, "Deleted device: " . $device['username']]);

    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "msg" => "Database error"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "Server error"]);
}
?>