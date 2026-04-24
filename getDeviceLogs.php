<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "
        SELECT dl.action, dl.created_at, u.username
        FROM device_logs dl
        INNER JOIN users u ON dl.device_id = u.id
        ORDER BY dl.created_at DESC
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