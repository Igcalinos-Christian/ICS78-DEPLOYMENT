<?php
$host = "localhost";
$username = "u442411629_dev_roomfill";
$password = "X05s7PY@X!KB";
$database = "u442411629_roomfill";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="users.csv"');

$output = fopen("php://output", "w");

fputcsv($output, array('id', 'user_id', 'action',  'created_at'));

$query = "SELECT id, user_id, action, created_at FROM activity_logs";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

$conn->close();
fclose($output);
exit;
?>