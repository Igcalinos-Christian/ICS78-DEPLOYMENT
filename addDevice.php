<?php
session_start();
header('Content-Type: application/json');

// Turn off display_errors so they don't leak into the JSON
ini_set('display_errors', 0); 
error_reporting(E_ALL);

try {
    include __DIR__ . "/connect.php";

    // 1. Check Connection
    if (!isset($pdo)) {
        throw new Exception("Database connection variable (\$pdo) not found. Check connect.php");
    }

    // 2. Auth Check
    if(!isset($_SESSION['user_id'])){
        echo json_encode(["status"=>"error","msg"=>"Unauthorized"]);
        exit;
    }

    // 3. Input Check
    $data = json_decode(file_get_contents("php://input"), true);
    if(!$data || !isset($data['username']) || !isset($data['password'])){
        echo json_encode(["status"=>"error","msg"=>"Missing input"]);
        exit;
    }

    $username = $data['username'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $current_user = $_SESSION['user_id'];

    // 4. Insert User using PDO
    $query = "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'device')";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$username, $password]);
    
    // Get the ID of the inserted device
    $device_id = $pdo->lastInsertId();

    // 5. Log action using PDO
    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $action = "Added device: $username";
    $logStmt->execute([$current_user, $action]);

    echo json_encode(["status"=>"success", "device_id"=>$device_id]);

} catch (PDOException $e) {
    // Check for duplicate username (Error 1062)
    if ($e->errorInfo[1] == 1062) {
        echo json_encode(["status"=>"error", "msg"=>"Username already exists"]);
    } else {
        echo json_encode(["status"=>"error", "msg"=>"Database Error: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    // Catch any other crash and send it as a clean JSON message
    echo json_encode(["status" => "error", "msg" => "Server Error: " . $e->getMessage()]);
}
?>