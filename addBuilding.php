<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    include __DIR__ . "/connect.php";

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        echo json_encode(['status' => 'error', 'msg' => 'No input received']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO buildings (name, latitude, longitude) VALUES (?, ?, ?)");
    $stmt->execute([$data['name'], $data['latitude'], $data['longitude']]);
    $building_id = $pdo->lastInsertId();

    // Log with IP, user agent, page
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page = $_SERVER['REQUEST_URI'] ?? '';
    $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent, page) VALUES (?, ?, ?, ?, ?)");
    $log->execute([$_SESSION['user_id'], "Added new building: " . $data['name'], $ip, $user_agent, $page]);

    foreach ($data['rooms'] as $floorIndex => $numRooms) {
        $floor_no = $floorIndex + 1;
        $stmt = $pdo->prepare("INSERT INTO floors (bldg_id, floor_no) VALUES (?, ?)");
        $stmt->execute([$building_id, $floor_no]);
        $floor_id = $pdo->lastInsertId();

        for ($r = 1; $r <= $numRooms; $r++) {
            $stmt = $pdo->prepare("INSERT INTO rooms (floor_id, room_no) VALUES (?, ?)");
            $stmt->execute([$floor_id, $r]);
            $room_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO room_occupants (room_id, no_of_occupants) VALUES (?, 0)");
            $stmt->execute([$room_id]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'msg' => 'Database error']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'msg' => 'Server error']);
}
?>