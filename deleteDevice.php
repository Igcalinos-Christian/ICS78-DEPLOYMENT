<?php
session_start();
header('Content-Type: application/json');
error_reporting(0);

try {
    include __DIR__ . "/connect.php";

    if(!isset($_SESSION['user_id'])){
        echo json_encode(["status"=>"error","msg"=>"Unauthorized"]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if(!$data || !isset($data['device_id'])){
        echo json_encode(["status"=>"error","msg"=>"Missing device ID"]);
        exit;
    }

    $device_id = $data['device_id'];
    $current_user = $_SESSION['user_id'];

    // 1. Get device username for logging (PDO Style)
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role='device'");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$device){
        echo json_encode(["status"=>"error","msg"=>"Device not found"]);
        exit;
    }

    // 2. Delete device (PDO Style)
    $delStmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='device'");
    $delStmt->execute([$device_id]);

    // 3. Log action (PDO Style)
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $action = "Deleted device: " . $device['username'];
    $logStmt->execute([$current_user, $action]);

    echo json_encode(["status"=>"success"]);

} catch (PDOException $e) {
    echo json_encode(["status"=>"error","msg"=>"Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["status"=>"error","msg"=>"Server Error: " . $e->getMessage()]);
}
?>