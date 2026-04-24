<?php
session_start();
header('Content-Type: application/json');
include "connect.php"; // $pdo

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

try {
    // THIS is the difference: We group the actions and COUNT them
    $sql = "SELECT al.action, COUNT(*) as count 
            FROM activity_logs al 
            JOIN users u ON al.user_id = u.id 
            WHERE u.role = 'device' 
            GROUP BY al.action";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($stats);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'msg' => 'Failed to get log stats',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>