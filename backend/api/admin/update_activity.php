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

$activity_id = intval($_POST['activity_id'] ?? 0);
$activity_type = $_POST['activity_type'] ?? '';
$activity_name = sanitize($_POST['activity_name'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$organization = sanitize($_POST['organization'] ?? '');
$achievement = sanitize($_POST['achievement'] ?? '');
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$status = $_POST['status'] ?? 'Completed';
$points = intval($_POST['points'] ?? 0);

if ($activity_id == 0 || empty($activity_type) || empty($activity_name)) {
    sendResponse(false, 'Required fields missing');
}

// Handle empty dates
if (empty($start_date)) $start_date = null;
if (empty($end_date)) $end_date = null;

$sql = "UPDATE extracurricular_activities 
        SET activity_type = ?, activity_name = ?, description = ?, 
            organization = ?, start_date = ?, end_date = ?, 
            status = ?, achievement = ?, points = ?
        WHERE activity_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssssssii",
    $activity_type, $activity_name, $description, $organization,
    $start_date, $end_date, $status, $achievement, $points, $activity_id
);

if (mysqli_stmt_execute($stmt)) {
    sendResponse(true, 'Activity updated successfully');
} else {
    sendResponse(false, 'Failed to update activity: ' . mysqli_error($conn));
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>