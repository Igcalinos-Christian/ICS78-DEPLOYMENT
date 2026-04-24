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
        SELECT users.username, 
               activity_logs.action, 
               activity_logs.created_at,
               activity_logs.ip_address,
               activity_logs.user_agent,
               activity_logs.page
        FROM activity_logs
        INNER JOIN users ON activity_logs.user_id = users.id
        ORDER BY activity_logs.created_at DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($logs);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get logs', 'details' => $e->getMessage()]);
    exit;
}
?>