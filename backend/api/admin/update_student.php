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

$student_id = intval($_POST['student_id'] ?? 0);
$first_name = sanitize($_POST['first_name'] ?? '');
$last_name = sanitize($_POST['last_name'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$current_semester = intval($_POST['current_semester'] ?? 1);

if ($student_id == 0 || empty($first_name) || empty($last_name) || empty($email)) {
    sendResponse(false, 'Required fields missing');
}

$sql = "UPDATE students 
        SET first_name = ?, last_name = ?, email = ?, phone = ?, 
            address = ?, current_semester = ?
        WHERE student_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssiii", 
    $first_name, $last_name, $email, $phone, $address, $current_semester, $student_id
);

if (mysqli_stmt_execute($stmt)) {
    sendResponse(true, 'Student updated successfully');
} else {
    sendResponse(false, 'Failed to update student: ' . mysqli_error($conn));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>