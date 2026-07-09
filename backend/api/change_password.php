<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$student_id = getStudentId();

// Get form data
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    sendResponse(false, 'All fields are required');
}

if ($new_password !== $confirm_password) {
    sendResponse(false, 'New passwords do not match');
}

if (strlen($new_password) < 6) {
    sendResponse(false, 'Password must be at least 6 characters');
}

// Get current password from database
$sql = "SELECT password FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    sendResponse(false, 'Current password is incorrect');
}

// Hash new password
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Update password
$sql_update = "UPDATE students SET password = ? WHERE student_id = ?";
$stmt_update = mysqli_prepare($conn, $sql_update);
mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $student_id);

if (mysqli_stmt_execute($stmt_update)) {
    sendResponse(true, 'Password changed successfully');
} else {
    sendResponse(false, 'Failed to change password');
}

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_update);
mysqli_close($conn);
?>