<?php

$host = "localhost";
$db   = "u442411629_roomfill";
$user = "u442411629_dev_roomfill";
$pass = "X05s7PY@X!KB";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed', 'details' => $e->getMessage()]);
    exit;
}
?>