<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . "/connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$room_id = $_GET['room_id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT f.bldg_id AS building_id FROM rooms r JOIN floors f ON r.floor_id = f.id WHERE r.id = ?");
    $stmt->execute([$room_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row ? $row : []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}