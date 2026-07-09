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

if ($student_id == 0) {
    sendResponse(false, 'Invalid student ID');
}

$sql = "DELETE FROM students WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);

if (mysqli_stmt_execute($stmt)) {
    sendResponse(true, 'Student deleted successfully');
} else {
    sendResponse(false, 'Failed to delete student: ' . mysqli_error($conn));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>