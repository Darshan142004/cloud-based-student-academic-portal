<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendResponse(false, 'Unauthorized access');
}

header('Content-Type: application/json');

$sql = "SELECT 
    a.activity_id,
    a.student_id,
    a.activity_type,
    a.activity_name,
    a.description,
    a.organization,
    a.start_date,
    a.end_date,
    a.status,
    a.achievement,
    a.points,
    s.enrollment_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name
FROM extracurricular_activities a
JOIN students s ON a.student_id = s.student_id
ORDER BY a.created_at DESC";

$result = mysqli_query($conn, $sql);
$activities = [];

while ($row = mysqli_fetch_assoc($result)) {
    $activities[] = $row;
}

$response = [
    'success' => true,
    'data' => $activities
];

echo json_encode($response);
mysqli_close($conn);
?>