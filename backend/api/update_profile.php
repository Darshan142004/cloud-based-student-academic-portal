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
$phone = sanitize($_POST['phone'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$address = sanitize($_POST['address'] ?? '');

// Validate
if (empty($email) || empty($phone)) {
    sendResponse(false, 'Email and phone are required');
}

// Update profile
$sql = "UPDATE students 
        SET phone = ?, email = ?, address = ?
        WHERE student_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssi", $phone, $email, $address, $student_id);

if (mysqli_stmt_execute($stmt)) {
    // Update session email if changed
    $_SESSION['email'] = $email;
    
    sendResponse(true, 'Profile updated successfully', [
        'phone' => $phone,
        'email' => $email,
        'address' => $address
    ]);
} else {
    sendResponse(false, 'Failed to update profile');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>