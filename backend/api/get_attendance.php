<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$student_id = getStudentId();

// Get student details from students table (authoritative source of truth for current_semester)
$sql_student = "SELECT first_name, last_name, current_semester FROM students WHERE student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
$student = mysqli_fetch_assoc($result_student);

if (!$student) {
    sendResponse(false, 'Student record not found');
}

$current_semester = isset($student['current_semester']) && $student['current_semester'] !== null ? (int)$student['current_semester'] : null;
if (!$current_semester || $current_semester <= 0) {
    sendResponse(false, 'Invalid current semester recorded for student');
}

$sql = "SELECT 
    c.course_code,
    c.course_name,
    c.credits,
    a.total_classes,
    a.classes_attended,
    a.attendance_percentage
FROM attendance a
JOIN courses c ON a.course_id = c.course_id
WHERE a.student_id = ? AND a.semester = ?
ORDER BY c.course_name";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $student_id, $current_semester);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$attendance_data = [];
$total_classes_all = 0;
$total_attended_all = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $attendance_data[] = $row;
    $total_classes_all += $row['total_classes'];
    $total_attended_all += $row['classes_attended'];
}

$overall_percentage = $total_classes_all > 0 ? 
    round(($total_attended_all / $total_classes_all) * 100, 2) : 0;

$response = [
    'success' => true,
    'data' => [
        'attendance' => $attendance_data,
        'overall' => [
            'total_classes' => $total_classes_all,
            'attended' => $total_attended_all,
            'percentage' => $overall_percentage
        ],
        'semester' => $current_semester,
        'student_name' => $student['first_name'] . ' ' . $student['last_name']
    ]
];

echo json_encode($response);

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_student);
mysqli_close($conn);
?>