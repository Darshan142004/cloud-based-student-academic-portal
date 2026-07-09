<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$student_id = getStudentId();
$current_semester = $_SESSION['semester'] ?? 4;

// Get enrolled courses with attendance
$sql = "SELECT 
    c.course_id,
    c.course_code,
    c.course_name,
    c.credits,
    c.semester,
    c.faculty_name,
    c.faculty_email,
    c.description,
    c.syllabus,
    a.total_classes,
    a.classes_attended,
    a.attendance_percentage
FROM enrollments e
JOIN courses c ON e.course_id = c.course_id
LEFT JOIN attendance a ON a.course_id = c.course_id AND a.student_id = e.student_id AND a.semester = e.semester
WHERE e.student_id = ? AND e.semester = ?
ORDER BY c.course_code";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $student_id, $current_semester);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$courses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $courses[] = $row;
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
        'courses' => $courses,
        'semester' => $current_semester,
        'total_courses' => count($courses),
        'total_credits' => array_sum(array_column($courses, 'credits')),
        'student_name' => $student['first_name'] . ' ' . $student['last_name']
    ]
];

echo json_encode($response);

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_student);
mysqli_close($conn);
?>