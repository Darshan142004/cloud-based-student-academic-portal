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
$activity_type = $_POST['activity_type'] ?? '';
$activity_name = sanitize($_POST['activity_name'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$organization = sanitize($_POST['organization'] ?? '');
$achievement = sanitize($_POST['achievement'] ?? '');
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$status = $_POST['status'] ?? 'Completed';
$points = intval($_POST['points'] ?? 0);

if ($student_id == 0 || empty($activity_type) || empty($activity_name)) {
    sendResponse(false, 'Required fields missing');
}

$sql = "INSERT INTO extracurricular_activities (
    student_id, activity_type, activity_name, description, organization,
    start_date, end_date, status, achievement, points
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "issssssssi",
    $student_id, $activity_type, $activity_name, $description, $organization,
    $start_date, $end_date, $status, $achievement, $points
);

if (mysqli_stmt_execute($stmt)) {
    sendResponse(true, 'Activity added successfully', [
        'activity_id' => mysqli_insert_id($conn)
    ]);
} else {
    sendResponse(false, 'Failed to add activity: ' . mysqli_error($conn));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>