<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if admin is logged in
$admin_id = requireAdminApi();

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

// Get total assignments
$sql_assignments = "SELECT COUNT(*) as total FROM assignments";
$result_assignments = mysqli_query($conn, $sql_assignments);
$total_assignments = $result_assignments ? (int) mysqli_fetch_assoc($result_assignments)['total'] : 0;

// Get weighted average attendance across all recorded classes
$sql_attendance = "SELECT SUM(classes_attended) AS attended, SUM(total_classes) AS total FROM attendance";
$result_attendance = mysqli_query($conn, $sql_attendance);
$att_row = mysqli_fetch_assoc($result_attendance);
$avg_attendance = ((int) ($att_row['total'] ?? 0) > 0)
    ? round(((int) $att_row['attended'] / (int) $att_row['total']) * 100, 1)
    : 0;

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
        'total_assignments' => $total_assignments,
        'avg_attendance' => $avg_attendance,
        'recent_students' => $recent_students,
        'admin_name' => $admin_name
    ]
];

echo json_encode($response);
mysqli_close($conn);
?>