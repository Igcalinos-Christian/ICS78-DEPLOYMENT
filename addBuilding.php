<?php
session_start();
header('Content-Type: application/json');
include "connect.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'msg' => 'No input received']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert building
    $stmt = $pdo->prepare("INSERT INTO buildings (name, latitude, longitude) VALUES (?, ?, ?)");
    $stmt->execute([$data['name'], $data['latitude'], $data['longitude']]);
    $building_id = $pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], "Added new building: ".$data['name']]);

    // Insert floors and rooms
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
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    exit;
}
?>