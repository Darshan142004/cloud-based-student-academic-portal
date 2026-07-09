<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$student_id = getStudentId();

// Get all extracurricular activities
$sql = "SELECT 
    activity_id,
    activity_type,
    activity_name,
    description,
    organization,
    start_date,
    end_date,
    status,
    achievement,
    certificate_url,
    points,
    created_at
FROM extracurricular_activities
WHERE student_id = ?
ORDER BY 
    CASE WHEN status = 'Ongoing' THEN 0 ELSE 1 END,
    start_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$activities = [];
$total_points = 0;
$activity_counts = [
    'Sports' => 0,
    'Cultural' => 0,
    'Club' => 0,
    'Competition' => 0,
    'Certification' => 0,
    'Volunteer' => 0,
    'Achievement' => 0
];

while ($row = mysqli_fetch_assoc($result)) {
    $activities[] = $row;
    $total_points += $row['points'];
    $activity_counts[$row['activity_type']]++;
}

// Get student name
$sql_student = "SELECT first_name, last_name FROM students WHERE student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
$student = mysqli_fetch_assoc($result_student);

$response = [
    'success' => true,
    'data' => [
        'activities' => $activities,
        'total_activities' => count($activities),
        'total_points' => $total_points,
        'activity_counts' => $activity_counts,
        'student_name' => $student['first_name'] . ' ' . $student['last_name']
    ]
];

echo json_encode($response);

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_student);
mysqli_close($conn);
?>