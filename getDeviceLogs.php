<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

try {
    // Query from device_logs instead of activity_logs
    $sql = "
        SELECT action, created_at
        FROM device_logs
        ORDER BY created_at DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get device logs', 'details' => $e->getMessage()]);
    exit;
}
?>