<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$student_id = getStudentId();

// Get complete student profile
$sql = "SELECT 
    student_id,
    enrollment_id,
    first_name,
    last_name,
    email,
    phone,
    date_of_birth,
    gender,
    address,
    department,
    program,
    current_semester,
    admission_year,
    profile_photo
FROM students 
WHERE student_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if ($student) {
    $response = [
        'success' => true,
        'data' => $student
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'Student not found'
    ];
}

echo json_encode($response);

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>