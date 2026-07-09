<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

$username = sanitize($_POST['enrollment_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    sendResponse(false, 'Please enter username and password');
}

// First check if admin
$sql_admin = "SELECT admin_id, username, password, full_name, email FROM admin WHERE username = ?";
$stmt_admin = mysqli_prepare($conn, $sql_admin);
mysqli_stmt_bind_param($stmt_admin, "s", $username);
mysqli_stmt_execute($stmt_admin);
$result_admin = mysqli_stmt_get_result($stmt_admin);

if ($admin = mysqli_fetch_assoc($result_admin)) {
    // Admin found
    if (password_verify($password, $admin['password'])) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['email'] = $admin['email'];
        
        sendResponse(true, 'Admin login successful', [
            'user_type' => 'admin',
            'name' => $admin['full_name'],
            'redirect' => 'admin/dashboard.html'
        ]);
    } else {
        sendResponse(false, 'Invalid password');
    }
}

// If not admin, check student
$sql_student = "SELECT student_id, enrollment_id, password, first_name, last_name, email, current_semester, profile_photo 
        FROM students 
        WHERE enrollment_id = ?";

$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "s", $username);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);

if ($student = mysqli_fetch_assoc($result_student)) {
    if (password_verify($password, $student['password'])) {
        $_SESSION['user_type'] = 'student';
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['enrollment_id'] = $student['enrollment_id'];
        $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
        $_SESSION['email'] = $student['email'];
        $_SESSION['semester'] = $student['current_semester'];
        $_SESSION['profile_photo'] = $student['profile_photo'];
        
        sendResponse(true, 'Student login successful', [
            'user_type' => 'student',
            'name' => $_SESSION['student_name'],
            'redirect' => 'dashboard.html'
        ]);
    } else {
        sendResponse(false, 'Invalid password');
    }
} else {
    sendResponse(false, 'User not found');
}

mysqli_stmt_close($stmt_admin);
mysqli_stmt_close($stmt_student);
mysqli_close($conn);
?>