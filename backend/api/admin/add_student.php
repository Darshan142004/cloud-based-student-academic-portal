<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    sendResponse(false, 'Unauthorized access');
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get form data
$enrollment_id = sanitize($_POST['enrollment_id'] ?? '');
$password = $_POST['password'] ?? '';
$first_name = sanitize($_POST['first_name'] ?? '');
$last_name = sanitize($_POST['last_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$date_of_birth = $_POST['date_of_birth'] ?? null;
$gender = $_POST['gender'] ?? 'Male';
$address = sanitize($_POST['address'] ?? '');
$current_semester = intval($_POST['current_semester'] ?? 1);
$admission_year = intval($_POST['admission_year'] ?? date('Y'));

// Validate required fields
if (empty($enrollment_id) || empty($password) || empty($first_name) || empty($last_name) || empty($email)) {
    sendResponse(false, 'All required fields must be filled');
}

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insert student
$sql = "INSERT INTO students (
    enrollment_id, password, first_name, last_name, email, phone,
    date_of_birth, gender, address, current_semester, admission_year
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssssssii", 
    $enrollment_id, $password_hash, $first_name, $last_name, $email, $phone,
    $date_of_birth, $gender, $address, $current_semester, $admission_year
);

if (mysqli_stmt_execute($stmt)) {
    sendResponse(true, 'Student added successfully', [
        'student_id' => mysqli_insert_id($conn)
    ]);
} else {
    if (mysqli_errno($conn) == 1062) {
        sendResponse(false, 'Enrollment ID or Email already exists');
    } else {
        sendResponse(false, 'Failed to add student: ' . mysqli_error($conn));
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>