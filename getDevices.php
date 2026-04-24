<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role='device'");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($devices);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get devices', 'details' => $e->getMessage()]);
    exit;
}
?>