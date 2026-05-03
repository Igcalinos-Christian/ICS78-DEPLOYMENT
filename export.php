<?php
session_start();
include __DIR__ . "/connect.php";

// Optional: restrict to logged‑in users
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$sql = "SELECT 
            a.id                   AS activity_id,
            a.user_id,
            u.username,
            a.action               AS activity_action,
            a.ip_address,
            a.user_agent,
            a.page,
            a.created_at           AS activity_created_at,
            d.id                   AS device_log_id,
            d.device_id            AS device_log_device_id,
            d.action               AS device_log_action,
            d.created_at           AS device_log_created_at
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN device_logs d ON a.user_id = d.device_id
        ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="logs_export.csv"');

$output = fopen('php://output', 'w');

// Column names (matching the SELECT aliases)
fputcsv($output, [
    'Activity ID',
    'User ID',
    'Username',
    'Activity Action',
    'IP Address',
    'User Agent',
    'Page',
    'Activity Created At',
    'Device Log ID',
    'Device Log Device ID',
    'Device Log Action',
    'Device Log Created At'
]);

foreach ($logs as $row) {
    fputcsv($output, [
        $row['activity_id'],
        $row['user_id'],
        $row['username'],
        $row['activity_action'],
        $row['ip_address'] ?? '',
        $row['user_agent'] ?? '',
        $row['page'] ?? '',
        $row['activity_created_at'],
        $row['device_log_id']         ?? '',
        $row['device_log_device_id']  ?? '',
        $row['device_log_action']     ?? '',
        $row['device_log_created_at'] ?? ''
    ]);
}

fclose($output);
exit;