<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

header('Content-Type: application/json');

$student_id = getStudentId();

// Get student details
$sql = "SELECT student_id, enrollment_id, first_name, last_name, email, phone, 
        department, program, current_semester, profile_photo 
        FROM students WHERE student_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

// Get overall attendance percentage
$sql_attendance = "SELECT 
    AVG(attendance_percentage) as overall_attendance
    FROM attendance 
    WHERE student_id = ? AND semester = ?";

$stmt_att = mysqli_prepare($conn, $sql_attendance);
mysqli_stmt_bind_param($stmt_att, "ii", $student_id, $student['current_semester']);
mysqli_stmt_execute($stmt_att);
$result_att = mysqli_stmt_get_result($stmt_att);
$attendance_data = mysqli_fetch_assoc($result_att);

// Get CGPA (credit-weighted calculation consistent with get_grades.php)
$sql_cgpa = "SELECT 
    SUM(g.grade_points * c.credits) / SUM(c.credits) as cgpa
    FROM grades g
    JOIN courses c ON g.course_id = c.course_id
    WHERE g.student_id = ? AND g.grade_points IS NOT NULL";

$stmt_cgpa = mysqli_prepare($conn, $sql_cgpa);
mysqli_stmt_bind_param($stmt_cgpa, "i", $student_id);
mysqli_stmt_execute($stmt_cgpa);
$result_cgpa = mysqli_stmt_get_result($stmt_cgpa);
$cgpa_data = mysqli_fetch_assoc($result_cgpa);

// Get total courses for current semester
$sql_courses = "SELECT COUNT(*) as total_courses 
    FROM enrollments 
    WHERE student_id = ? AND semester = ?";

$stmt_courses = mysqli_prepare($conn, $sql_courses);
mysqli_stmt_bind_param($stmt_courses, "ii", $student_id, $student['current_semester']);
mysqli_stmt_execute($stmt_courses);
$result_courses = mysqli_stmt_get_result($stmt_courses);
$courses_data = mysqli_fetch_assoc($result_courses);

// Prepare response
$response = [
    'success' => true,
    'data' => [
        'student' => [
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'enrollment_id' => $student['enrollment_id'],
            'email' => $student['email'],
            'phone' => $student['phone'],
            'department' => $student['department'],
            'program' => $student['program'],
            'semester' => $student['current_semester'],
            'profile_photo' => $student['profile_photo']
        ],
        'stats' => [
            'attendance' => round($attendance_data['overall_attendance'] ?? 0, 2),
            'cgpa' => round($cgpa_data['cgpa'] ?? 0, 2),
            'total_courses' => $courses_data['total_courses']
        ]
    ]
];

echo json_encode($response);

mysqli_close($conn);
?>