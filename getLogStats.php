<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT action, COUNT(*) as count 
            FROM device_logs 
            GROUP BY action";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($stats);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get log stats', 'details' => $e->getMessage()]);
    exit;
}
?>