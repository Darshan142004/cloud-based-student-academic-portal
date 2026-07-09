<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendResponse(false, 'Unauthorized access');
}

header('Content-Type: application/json');

// Get total students
$sql_students = "SELECT COUNT(*) as total FROM students";
$result_students = mysqli_query($conn, $sql_students);
$total_students = mysqli_fetch_assoc($result_students)['total'];

// Get total activities
$sql_activities = "SELECT COUNT(*) as total FROM extracurricular_activities";
$result_activities = mysqli_query($conn, $sql_activities);
$total_activities = mysqli_fetch_assoc($result_activities)['total'];

// Get total courses
$sql_courses = "SELECT COUNT(*) as total FROM courses";
$result_courses = mysqli_query($conn, $sql_courses);
$total_courses = mysqli_fetch_assoc($result_courses)['total'];

// Get average attendance
$sql_attendance = "SELECT AVG(attendance_percentage) as avg_attendance FROM attendance";
$result_attendance = mysqli_query($conn, $sql_attendance);
$avg_attendance = mysqli_fetch_assoc($result_attendance)['avg_attendance'];

// Get recent students (last 5)
$sql_recent = "SELECT student_id, enrollment_id, first_name, last_name, email, current_semester, program 
               FROM students 
               ORDER BY created_at DESC 
               LIMIT 5";
$result_recent = mysqli_query($conn, $sql_recent);
$recent_students = [];
while ($row = mysqli_fetch_assoc($result_recent)) {
    $recent_students[] = $row;
}

// Get admin name
$admin_name = $_SESSION['admin_name'] ?? 'Administrator';

$response = [
    'success' => true,
    'data' => [
        'total_students' => $total_students,
        'total_activities' => $total_activities,
        'total_courses' => $total_courses,
        'avg_attendance' => round($avg_attendance ?? 0, 1),
        'recent_students' => $recent_students,
        'admin_name' => $admin_name
    ]
];

echo json_encode($response);
mysqli_close($conn);
?>