<?php
// getBuildings.php - returns all buildings + floors + rooms
header('Content-Type: application/json');
include "connect.php";

try {
    $buildings = [];

    // Fetch all buildings
    $stmt = $pdo->query("SELECT * FROM buildings");
    $allBuildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$allBuildings) {
        echo json_encode([]); // Return an empty array if no buildings found
        exit;
    }

    foreach ($allBuildings as $b) {
        $b_id = $b['id'];
        $floors = [];

        // Fetch floors for this building
        $floorStmt = $pdo->prepare("SELECT * FROM floors WHERE bldg_id = ? ORDER BY floor_no");
        $floorStmt->execute([$b_id]);
        $allFloors = $floorStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allFloors as $f) {
            $rooms = [];

            // Fetch rooms for this floor
            $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE floor_id = ? ORDER BY room_no");
            $roomStmt->execute([$f['id']]);
            $allRooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allRooms as $r) {
                // Count occupants for each room
                $occupantStmt = $pdo->prepare("SELECT no_of_occupants FROM room_occupants WHERE room_id = ?");
                $occupantStmt->execute([$r['id']]);
                $occupants = $occupantStmt->fetchColumn() ?: 0;

                $rooms[] = $occupants;
            }

            $floors[] = $rooms;
        }

        $buildings[] = [
            "id" => $b['id'],
            "name" => $b['name'],
            "lat" => $b['latitude'],
            "lng" => $b['longitude'],
            "floors" => count($floors),
            "rooms" => $floors
        ];
    }

    echo json_encode($buildings);

} catch (PDOException $e) {
    // Ensure that we return an empty array in case of an error
    echo json_encode([]);
    error_log("Error fetching buildings: " . $e->getMessage());
    exit;
}
?>