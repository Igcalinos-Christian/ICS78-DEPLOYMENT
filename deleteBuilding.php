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
    if (!$data || !isset($data['building_id'])) {
        echo json_encode(["status" => "error", "msg" => "Missing building ID"]);
        exit;
    }

    $building_id = intval($data['building_id']);
    $current_user = $_SESSION['user_id'];

    // Role check
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$current_user]);
    $user = $check->fetch();
    if (!$user || !in_array($user['role'], ['manager', 'admin'])) {
        echo json_encode(["status" => "error", "msg" => "Forbidden"]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM buildings WHERE id = ?");
    $stmt->execute([$building_id]);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$current_user, "Deleted building ID: $building_id", $ip, $user_agent, $page]);

    echo json_encode(["status" => "success"]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "msg" => "Database error"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "Server error"]);
}
?>