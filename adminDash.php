<?php
session_start();
include "connect.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Verify the logged-in user has role 'admin'
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    // Not an admin – destroy session and redirect to login
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="managerDash.css">
</head>
<body>

<div class="header">
    <span>Admin Dashboard</span>
    <div class="header-right">
        <button class="btn-logout" onclick="location.href='logout.php'">Logout</button>
    </div>
</div>

<div class="logs-section">
    <h3>All Activity Logs (Devices Only)</h3>
    <div id="logsContainer" style="max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:10px;"></div>
    <br>
    <form action="export.php" method="post">
        <button type="submit">Get CSV</button>
    </form>
</div>

<div class="charts-section">
    <h3>Activity Analytics</h3>
    <canvas id="logsBarChart" style="max-width:600px; margin-top:20px;"></canvas>
    <canvas id="logsPieChart" style="max-width:600px; margin-top:20px;"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="adminDash.js"></script>
</body>
</html>