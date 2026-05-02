<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . "/connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$building_id = $_GET['building_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT r.id, f.floor_no, r.room_no
        FROM rooms r
        JOIN floors f ON r.floor_id = f.id
        WHERE f.bldg_id = ?
        ORDER BY f.floor_no, r.room_no
    ");
    $stmt->execute([$building_id]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rooms);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}