<?php
session_start();
include "connect.php";  // Added – needed for role check

// Handle login if posted directly (keep your existing logic)
if(isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if(password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            echo json_encode(["status"=>"success"]);
            exit;
        }
    }
    echo json_encode(["status"=>"error","msg"=>"Invalid credentials"]);
    exit;
}

// --- SESSION AND ROLE CHECK (KEEP THIS) ---
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Verify role – only manager or admin can access this page
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['manager', 'admin'])) {
        session_destroy();
        header("Location: index.php");
        exit;
    }
    // Store role in session for quick checks
    $_SESSION['role'] = $user['role'];
} catch (PDOException $e) {
    // If DB error, deny access
    session_destroy();
    header("Location: index.php");
    exit;
}
// --- END ROLE CHECK ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="managerDash.css">
<body>

<div class="header">
    <span>Campus Building Dashboard</span>
    <div class="header-right">
    <button id="deleteBuildingBtn" style="background-color:red; color:white;" onclick="deleteSelectedBuilding()" disabled>Delete Building</button>
    <button id="assignDeviceBtn" onclick="toggleAssignDeviceForm()">Assign Device</button>
    <button class="btn-add" onclick="showDeviceManager()">Manage Devices</button>
    <button id="showFormBtn" onclick="toggleBuildingForm()">Add Building</button>
    <button class="btn-logout" onclick="location.href='logout.php'">Logout</button>
</div>
</div>

<div class="content">

    <div class="main">
    <div id="map"></div>

    <div class="sidebar">
        <div class="board">
            <h3>Building Information</h3>
            <p><b>Name:</b> <span id="infoName">None</span></p>
            <p><b>Floors:</b> <span id="infoFloors">0</span></p>
            <p><b>Total Rooms:</b> <span id="infoRooms">0</span></p>
            <div id="roomListContainer"></div>
        </div>

        <div id="formContainer" class="hidden">
            <h3>Add Building</h3>
            <form id="buildingForm">
                <input type="text" id="buildingName" placeholder="Building Name" required><br><br>
                <input type="number" id="floorCount" placeholder="Number of Floors" min="1" required><br><br>
                <input type="number" id="latitude" placeholder="latitude" step="any" required readonly>
                <input type="number" id="longitude" placeholder="longitude" step="any" required readonly><br>
                <p class="mapHint" style="font-size: 0.8em; color: gray;">Click the map to set coordinates</p>
                
                <div id="roomInputs"></div>
                <button type="submit">Save to Database</button>
            </form>
        </div>
    </div>
</div>

<div class="logs-section">
    <h3>Device Logs</h3>
    <div id="logsContainer"></div>
</div>

<div class="charts-section">
    <h3>Device Activity Analytics</h3>

    <!-- Wrap charts in a div with a fixed height -->
    <div style="position: relative; height: 250px; width: 100%;">
        <canvas id="logsBarChart"></canvas>
    </div>
    
    <div style="position: relative; height: 250px; width: 100%; margin-top:20px;">
        <canvas id="logsPieChart"></canvas>
    </div>
</div>

<div class="device-management">
    <h3>Manage Devices</h3>

    <h4>Existing Devices</h4>
    <div id="deviceList"></div>
</div>

</div>

<!-- 1. Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- 2. Chart.js MUST BE BEFORE your script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- 3. Your custom script -->
<script src="managerDash.js"></script>
</body>
</html>