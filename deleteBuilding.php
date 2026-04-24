<?php
session_start();
header('Content-Type: application/json');

// Turn off display_errors so they don't break the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    include __DIR__ . "/connect.php"; // This brings in $pdo

    // 1. Auth Check
    if(!isset($_SESSION['user_id'])){
        echo json_encode(["status"=>"error","msg"=>"Unauthorized"]);
        exit;
    }

    // 2. Input Check
    $data = json_decode(file_get_contents("php://input"), true);
    if(!$data || !isset($data['building_id'])){
        echo json_encode(["status"=>"error","msg"=>"Missing building_id"]);
        exit;
    }

    $building_id = intval($data['building_id']);
    $current_user = $_SESSION['user_id'];

    // 3. Delete building using PDO (CASCADE removes floors, rooms, etc.)
    $stmt = $pdo->prepare("DELETE FROM buildings WHERE id=?");
    $stmt->execute([$building_id]);

    // 4. Log the deletion using PDO
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $action = "Deleted building ID: $building_id";
    $logStmt->execute([$current_user, $action]);

    echo json_encode(["status"=>"success"]);

} catch (PDOException $e) {
    // Catch database errors cleanly
    echo json_encode(["status"=>"error", "msg"=>"Database Error: " . $e->getMessage()]);
} catch (Exception $e) {
    // Catch server errors cleanly
    echo json_encode(["status"=>"error", "msg"=>"Server Error: " . $e->getMessage()]);
}
?>